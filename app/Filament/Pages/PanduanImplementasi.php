<?php

namespace App\Filament\Pages;

use App\Filament\Resources;
use App\Models;
use Filament\Pages\Page;
use Throwable;

/** Panduan implementasi bertahap mulai dari data awal, dengan status setiap langkah dihitung dari data nyata. */
class PanduanImplementasi extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Umum';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Panduan implementasi';

    protected static ?string $title = 'Panduan implementasi';

    protected static ?string $slug = 'panduan-implementasi';

    protected string $view = 'filament.pages.panduan-implementasi';

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    protected function getViewData(): array
    {
        $n = fn (callable $q) => rescue(fn () => (int) $q(), 0, false);
        $url = function (callable $f) {
            try {
                return $f();
            } catch (Throwable) {
                return null;
            }
        };
        $assets = $n(fn () => Models\Asset::count());
        $verified = $n(fn () => Models\Asset::where('verification_status', 'verified')->count());
        $active = $n(fn () => Models\Asset::where('lifecycle_status', 'active')->count());
        $labelsPrinted = $n(fn () => Models\LabelPrintItem::query()->distinct('asset_id')->count('asset_id'));
        $installed = $n(fn () => Models\Asset::where('installation_status', 'installed')->count());
        $assumed = $n(fn () => Models\Asset::where('notes', 'like', '%Diasumsikan saat data awal%')->count());
        $pct = fn ($a, $b) => $b ? (int) round($a / $b * 100) : 0;
        $st = fn (bool $done, bool $started = false) => $done ? 'selesai' : ($started ? 'berjalan' : 'belum');

        $phases = [
            ['Fase 1 · Persiapan', 'Pastikan aplikasi siap dipakai dan siapa saja penggunanya.', [
                ['Identitas perusahaan', 'Isi nama, badan hukum, alamat, logo dan warna. Tampil di laporan & label QR.', $st(\App\Support\Brand::companyName() !== 'Perusahaan Anda'), \App\Support\Brand::companyName(), $url(fn () => IdentitasPerusahaan::getUrl()), 'pages.identitas-perusahaan'],
                ['Pengguna & peran', 'Buat akun Chief Engineer, teknisi, Asset Controller, HOD, GM, Finance. Setiap pengguna mengganti sandi & memasang 2FA saat login pertama.', $st(($u = $n(fn () => Models\User::where('is_active', true)->count())) >= 5, $u > 1), $u.' pengguna aktif', $url(fn () => Resources\Users\UserResource::getUrl('index')), 'users'],
                ['Departemen', 'Periksa daftar departemen & cost center.', $st(($d = $n(fn () => Models\Department::count())) > 0), $d.' departemen', $url(fn () => Resources\Departments\DepartmentResource::getUrl('index')), 'departments'],
                ['Target SLA & batas persetujuan', 'Sahkan target respons P1–P4 dan nominal batas persetujuan WO oleh manajemen.', $st(($a = $n(fn () => Models\ApprovalLimit::count())) > 0), $a.' batas persetujuan', $url(fn () => Resources\ApprovalLimits\ApprovalLimitResource::getUrl('index')), 'approval-limits'],
            ]],
            ['Fase 2 · Struktur & data awal', 'Data awal sudah dimuat dari 7 file inventaris departemen. Tugas tim: memeriksa & melengkapi.', [
                ['Pohon lokasi', 'Gedung → lantai → kamar/ruang. Data awal membuat Main Building & West Wing. Tambahkan ruang yang belum ada.', $st(($l = $n(fn () => Models\Location::count())) > 20, $l > 0), $l.' lokasi', $url(fn () => Resources\Locations\LocationResource::getUrl('index')), 'locations'],
                ['Kategori aset', 'Periksa kategori, mode pelacakan, interval PM usulan, dan kelas penyusutan.', $st(($c = $n(fn () => Models\AssetCategory::count())) > 0), $c.' kategori', $url(fn () => Resources\AssetCategories\AssetCategoryResource::getUrl('index')), 'asset-categories'],
                ['Muat data awal aset', 'Paket lengkap: bash deploy/deploy-cpanel.sh install-data. Atau workbook: php artisan assetcare:import-initial (lihat PANDUAN-CPANEL). Semua aset masuk sebagai Draft.', $st($assets > 0), number_format($assets, 0, ',', '.').' aset', $url(fn () => Resources\Assets\AssetResource::getUrl('index')), 'assets'],
                ['Periksa asumsi data awal', 'Aset dengan catatan "Diasumsikan saat data awal" berisi isian yang ditebak (kondisi, merek, unit). Koreksi saat identifikasi.', $st($assets > 0 && $assumed === 0, $assumed > 0), number_format($assumed, 0, ',', '.').' aset berisi asumsi', $url(fn () => Resources\Assets\AssetResource::getUrl('index', ['search' => 'Diasumsikan'])), 'assets'],
                ['Kelompok jumlah', 'Linen, kursi banquet, kabel, tabung gas: satu QR per lokasi dengan jumlah standar.', $st(($g = $n(fn () => Models\AssetQuantityGroup::count())) > 0), $g.' kelompok', $url(fn () => Resources\AssetQuantityGroups\AssetQuantityGroupResource::getUrl('index')), 'asset-quantity-groups'],
            ]],
            ['Fase 3 · Identifikasi fisik & label', 'Tim aset GPCH berkeliling per lantai/ruang: cocokkan fisik, tempel label, koreksi data, verifikasi.', [
                ['Cetak label QR', 'Cetak per lantai/ruang agar mudah ditempel. Uji 1 lembar dulu.', $st($assets > 0 && $labelsPrinted >= $assets, $labelsPrinted > 0), number_format($labelsPrinted, 0, ',', '.').' aset sudah dicetak labelnya', $url(fn () => CetakLabel::getUrl()), 'pages.cetak-label'],
                ['Tempel & konfirmasi pemasangan', 'Tempel label di posisi yang mudah di-scan, lalu konfirmasi per kamar/ruang.', $st($assets > 0 && $installed >= $assets, $installed > 0), number_format($installed, 0, ',', '.').' terpasang', $url(fn () => KonfirmasiPemasangan::getUrl()), 'pages.konfirmasi-pemasangan'],
                ['Lengkapi data & foto', 'Di kartu aset: merek, model, nomor seri, spesifikasi, foto nameplate, kondisi sebenarnya.', $st($assets > 0 && $assumed === 0, $assumed < $assets), 'lihat Register aset', $url(fn () => Resources\Assets\AssetResource::getUrl('index')), 'assets'],
                ['Verifikasi fisik', 'Tombol "Verifikasi fisik" di kartu aset setelah dilihat langsung. Aset tidak ditemukan ditandai Selisih.', $st($assets > 0 && $verified >= $assets, $verified > 0), $pct($verified, $assets).'% ('.number_format($verified, 0, ',', '.').' aset)', $url(fn () => Resources\Assets\AssetResource::getUrl('index', ['filters' => ['verification_status' => ['value' => 'unverified']]])), 'assets'],
                ['Aktifkan aset', 'Aset yang lengkap & terverifikasi diaktifkan; masuk perhitungan KPI & penyusutan.', $st($assets > 0 && $active >= $assets, $active > 0), $pct($active, $assets).'% aktif', $url(fn () => Resources\Assets\AssetResource::getUrl('index')), 'assets'],
            ]],
            ['Fase 4 · Operasional pemeliharaan', 'Mulai memakai alur laporan kerusakan, work order, dan pemeliharaan terencana.', [
                ['Laporan kerusakan', 'Semua departemen melapor lewat aplikasi (scan QR → Lapor kerusakan), bukan lewat telepon/WA saja.', $st(($sr = $n(fn () => Models\ServiceRequest::count())) >= 10, $sr > 0), $sr.' laporan', $url(fn () => Resources\ServiceRequests\ServiceRequestResource::getUrl('index')), 'service-requests'],
                ['Work order', 'Engineering menugaskan, mengerjakan, mencatat biaya, memverifikasi, dan menutup WO.', $st(($wo = $n(fn () => Models\WorkOrder::where('status', 'closed')->count())) >= 10, $wo > 0), $wo.' WO ditutup', $url(fn () => Resources\WorkOrders\WorkOrderResource::getUrl('index', ['tab' => 'semua'])), 'work-orders'],
                ['Rencana PM', 'Buat dari interval kategori, lalu sunting checklist & PIC. WO preventive dibuat otomatis.', $st(($pm = $n(fn () => Models\PmPlan::count())) >= 5, $pm > 0), $pm.' rencana', $url(fn () => Resources\PmPlans\PmPlanResource::getUrl('index')), 'pm-plans'],
                ['Pemantauan kondisi', 'Catat suhu chiller/freezer, tekanan boiler, jam genset secara rutin.', $st(($cr = $n(fn () => Models\ConditionReading::count())) >= 10, $cr > 0), $cr.' pembacaan', $url(fn () => Resources\ConditionReadings\ConditionReadingResource::getUrl('index')), 'condition-readings'],
                ['Mutasi aset', 'Perpindahan aset antar-ruang/departemen lewat pengajuan mutasi.', $st(false, ($mt = $n(fn () => Models\AssetTransfer::count())) > 0), $mt.' mutasi', $url(fn () => Resources\AssetTransfers\AssetTransferResource::getUrl('index')), 'asset-transfers'],
            ]],
            ['Fase 5 · Aset tetap & dokumen legalitas', 'Bersama Finance & Legal.', [
                ['Kebijakan & kelas penyusutan', 'FC menyetujui kebijakan aset tetap, umur manfaat & kelompok fiskal.', $st(false, true), 'lihat kelas aset', $url(fn () => Resources\DepreciationClasses\DepreciationClassResource::getUrl('index')), 'depreciation-classes'],
                ['Unggah dokumen legalitas', 'Sertifikat, izin, riksa uji K3, BPKB, polis, kontrak: unggah cepat banyak berkas dan tautkan ke aset.', $st(($doc = $n(fn () => Models\AssetDocument::count())) >= 20, $doc > 0), $doc.' dokumen', $url(fn () => Resources\AssetDocuments\AssetDocumentResource::getUrl('index')), 'asset-documents'],
            ]],
            ['Fase 6 · Pemantauan & keputusan', 'Pimpinan memantau lewat dashboard dan laporan.', [
                ['Dashboard pimpinan', 'Grafik kondisi, verifikasi, tren WO, biaya, dokumen. Klik untuk detail.', 'selesai', 'tersedia', $url(fn () => DashboardPimpinan::getUrl()), 'pages.dashboard-pimpinan'],
                ['Laporan & KPI', 'Unduh laporan SLA, kepatuhan PM, riwayat aset, penyusutan.', 'selesai', 'tersedia', $url(fn () => Laporan::getUrl()), 'pages.laporan'],
            ]],
        ];
        $all = collect($phases)->flatMap(fn ($p) => $p[2]);

        return ['phases' => $phases, 'done' => $all->where(2, 'selesai')->count(), 'total' => $all->count()];
    }
}
