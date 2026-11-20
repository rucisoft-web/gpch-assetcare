<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Location;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Fmt;
use Carbon\CarbonImmutable;

/** Laporan tabel (layar & CSV). Kolom biaya hanya untuk pengguna berizin keuangan. */
class ReportService
{
    public const REPORTS = [
        'backlog' => 'Backlog work order',
        'sla' => 'Kepatuhan SLA laporan',
        'wo_selesai' => 'Work order selesai & MTTR',
        'aset' => 'Register aset',
        'label' => 'Status label per lokasi',
        'biaya' => 'Biaya pemeliharaan',
        'pm_kepatuhan' => 'Kepatuhan preventive maintenance',
        'pm_jadwal' => 'Jadwal PM 60 hari ke depan',
        'kondisi' => 'Pembacaan kondisi di luar batas',
        'riwayat_aset' => 'Riwayat pemeliharaan per aset',
        'penyusutan' => 'Penyusutan komersial (SAK EP) per aset',
        'fiskal' => 'Penyusutan fiskal per kelompok (PMK 72/2023)',
        'rekonsiliasi' => 'Rekonsiliasi komersial vs fiskal & pajak tangguhan',
        'mutasi' => 'Mutasi aset tetap per kelas',
        'kapitalisasi' => 'Pemeriksaan batas kapitalisasi',
        'dok_kurang' => 'Kekurangan dokumen wajib per aset',
        'dok_berakhir' => 'Dokumen akan/sudah berakhir',
        'tanpa_asuransi' => 'Aset bernilai tanpa polis asuransi hidup',
    ];

    /** Laporan yang memerlukan izin keuangan. */
    public const FINANCIAL = ['biaya', 'penyusutan', 'fiskal', 'rekonsiliasi', 'mutasi', 'kapitalisasi', 'tanpa_asuransi'];

    /** Laporan yang memerlukan izin document.view. */
    public const DOCUMENT = ['dok_kurang', 'dok_berakhir', 'tanpa_asuransi'];

    public function available(User $u): array
    {
        return array_filter(self::REPORTS, fn ($k) => $this->allowed($k, $u), ARRAY_FILTER_USE_KEY);
    }

    public function allowed(string $report, User $u): bool
    {
        if (in_array($report, self::FINANCIAL, true) && ! $u->canSeeFinancials()) {
            return false;
        }
        if (in_array($report, self::DOCUMENT, true) && ! $u->hasPermission('document.view')) {
            return false;
        }

        return true;
    }

    /** @return array{headers:array, rows:array} */
    public function build(string $report, User $u, string $from, string $to): array
    {
        $tz = config('assetcare.display_timezone');
        $f = CarbonImmutable::parse($from, $tz)->startOfDay()->utc();
        $t = CarbonImmutable::parse($to, $tz)->endOfDay()->utc();
        $fin = $u->canSeeFinancials();
        if (isset(self::REPORTS[$report]) && ! $this->allowed($report, $u)) {
            throw WorkflowException::forbidden();
        }
        $year = (int) CarbonImmutable::parse($to, $tz)->year;

        return match ($report) {
            'pm_kepatuhan', 'pm_jadwal', 'kondisi', 'riwayat_aset' => app(MaintenanceReports::class)->build($report, $f, $t),
            'penyusutan', 'fiskal', 'rekonsiliasi', 'mutasi', 'kapitalisasi' => app(FixedAssetReports::class)->build($report, $year),
            'dok_kurang', 'dok_berakhir', 'tanpa_asuransi' => app(FixedAssetReports::class)->build($report, $year, $u),
            'backlog' => [
                'headers' => ['Nomor', 'Prioritas', 'Status', 'Pekerjaan', 'Lokasi', 'Aset', 'PIC', 'Diterima', 'Target pulih', 'Umur (hari)', 'Lewat target'],
                'rows' => WorkOrder::whereIn('status', WorkOrder::OPEN)->with('location', 'asset', 'pic')->orderBy('received_at')->get()
                    ->map(fn ($w) => [$w->number, $w->priority, WorkOrder::STATUSES[$w->status], $w->title, $w->location?->full_path, $w->asset?->asset_code, $w->pic?->name,
                        Fmt::dt($w->received_at), Fmt::dt($w->due_at), (int) $w->created_at->diffInDays(now()), $w->due_at && ! $w->restored_at && $w->due_at->isPast() ? 'Ya' : ''])->all(),
            ],
            'sla' => [
                'headers' => ['Nomor', 'Prioritas', 'Kategori', 'Lokasi', 'Diterima', 'Batas respons', 'Direspons', 'Menit respons', 'Sesuai SLA', 'Status'],
                'rows' => ServiceRequest::whereBetween('received_at', [$f, $t])->with('location')->orderBy('received_at')->get()
                    ->map(fn ($r) => [$r->number, $r->priority, ServiceRequest::CATEGORIES[$r->category] ?? $r->category, $r->location?->full_path, Fmt::dt($r->received_at),
                        Fmt::dt($r->response_due_at), Fmt::dt($r->response_at), $r->response_at ? (int) $r->received_at->diffInMinutes($r->response_at) : '',
                        $r->response_due_at && $r->response_due_at->isFuture() && ! $r->response_at ? 'belum jatuh tempo' : ($r->responseOverdue() ? 'Tidak' : 'Ya'),
                        ServiceRequest::STATUSES[$r->status]])->all(),
            ],
            'wo_selesai' => [
                'headers' => array_merge(['Nomor', 'Jenis', 'Prioritas', 'Aset', 'Mode kerusakan', 'Diterima', 'Pulih', 'Durasi bruto (menit)', 'Hold (menit)', 'Durasi bersih (menit)', 'Uji fungsi', 'Verifikator'], $fin ? ['Biaya total', 'Status biaya'] : []),
                'rows' => WorkOrder::whereBetween('restored_at', [$f, $t])->with('asset', 'verifier', 'costLines')->orderBy('restored_at')->get()
                    ->map(function ($w) use ($fin) {
                        $gross = (int) $w->received_at->diffInMinutes($w->restored_at);

                        return array_merge([$w->number, WorkOrder::TYPES[$w->type] ?? $w->type, $w->priority, $w->asset?->asset_code, $w->failure_mode, Fmt::dt($w->received_at), Fmt::dt($w->restored_at),
                            $gross, $w->hold_minutes, max(0, $gross - $w->hold_minutes), WorkOrder::TEST_RESULTS[$w->function_test_result] ?? '', $w->verifier?->name],
                            $fin ? [$w->totalCost(), $w->cost_status === 'final' ? 'final' : 'sementara'] : []);
                    })->all(),
            ],
            'aset' => [
                'headers' => array_merge(['Kode', 'Nama', 'Kategori', 'Merek', 'Model', 'No. seri', 'Lokasi', 'Departemen', 'Siklus', 'Operasional', 'Kondisi', 'Verifikasi', 'Label', 'Garansi s.d.'], $fin ? ['Biaya perolehan'] : []),
                'rows' => Asset::with('category', 'location', 'department', 'financial')->whereIn('lifecycle_status', ['draft', 'active', 'retired'])->get()
                    ->sortBy(fn ($a) => ($a->location?->sort_key ?? 'zzz').'|'.$a->asset_code)
                    ->map(fn ($a) => array_merge([$a->asset_code, $a->name, $a->category?->name, $a->brand, $a->model, $a->serial_number, $a->location?->full_path, $a->department?->name,
                        Asset::LIFECYCLE[$a->lifecycle_status] ?? '', Asset::OPERATIONAL[$a->operational_status] ?? '', $a->condition_grade, Asset::VERIFICATION[$a->verification_status] ?? '',
                        \App\Models\InstallationEvent::STATUSES[$a->installation_status] ?? '', $a->warranty_until?->format('d/m/Y')], $fin ? [$a->financial?->acquisition_cost] : []))->values()->all(),
            ],
            'label' => [
                'headers' => ['Lokasi', 'Aset aktif/draft', 'Terpasang', 'Belum', 'Rusak/hilang', 'Perlu label ulang'],
                'rows' => Asset::whereIn('lifecycle_status', ['draft', 'active'])->with('location')->get()->groupBy('location_id')
                    ->map(fn ($g) => [$g->first()->location?->full_path, $g->count(), $g->where('installation_status', 'installed')->count(), $g->where('installation_status', 'pending')->count(),
                        $g->whereIn('installation_status', ['damaged', 'missing'])->count(), $g->where('installation_status', 'needs_relabel')->count()])
                    ->sortBy(0)->values()->all(),
            ],
            'biaya' => $fin ? [
                'headers' => ['WO', 'Periode', 'Jenis biaya', 'Uraian', 'Jumlah', 'Harga satuan', 'Total', 'Kas keluar', 'Status', 'Ref.'],
                'rows' => \App\Models\WoCostLine::whereBetween('created_at', [$f, $t])->with('workOrder')->orderBy('created_at')->get()
                    ->map(fn ($c) => [$c->workOrder?->number, $c->period, \App\Models\WoCostLine::TYPES[$c->type] ?? $c->type, $c->description, (float) $c->quantity, (float) $c->unit_cost, (float) $c->amount,
                        $c->is_cash_out ? 'Ya' : 'Tidak (alokasi)', $c->cost_status === 'final' ? 'final' : 'sementara', $c->invoice_ref])->all(),
            ] : throw WorkflowException::forbidden(),
            default => throw WorkflowException::invalid('Laporan tidak dikenal.'),
        };
    }
}
