<?php

namespace App\Services;

use App\Models\AssetLabel;
use App\Models\InstallationEvent;
use App\Models\LabelPrintBatch;
use App\Models\LabelPrintItem;
use App\Models\Location;
use App\Models\User;
use App\Support\Sequence;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * QR & label (bagian 6 & 26): QR Code model 2, koreksi kesalahan Q, quiet zone 4 modul, hitam di atas putih.
 * Cetak ulang memakai token sama (alasan dicatat); rotasi menonaktifkan token lama.
 */
class LabelService
{
    public function qrPng(string $text, int $px = 600): string
    {
        return (new Writer(new GDLibRenderer($px, 4)))->writeString($text, 'ISO-8859-1', ErrorCorrectionLevel::Q());
    }

    public function qrSvg(string $text, int $px = 220): string
    {
        $r = new ImageRenderer(new RendererStyle($px, 4), new SvgImageBackEnd);

        return (new Writer($r))->writeString($text, 'ISO-8859-1', ErrorCorrectionLevel::Q());
    }

    /**
     * Pilih label aktif menurut filter. Urutan: gedung, lantai, nomor kamar natural, kategori, posisi unit.
     *
     * @param  array{room_ids?:array,floor_id?:int,category_ids?:array,generation_batch_id?:int,include_unverified?:bool,print_state?:string,include_groups?:bool,asset_ids?:array}  $f
     */
    public function select(array $f): Collection
    {
        $q = AssetLabel::query()->where('status', 'active')
            ->with(['asset.location', 'asset.category', 'asset.slotAssignment.slot', 'quantityGroup.location', 'quantityGroup.category', 'latestInstallation'])
            ->withCount('printItems');

        $roomIds = array_filter((array) ($f['room_ids'] ?? []));
        if (! empty($f['floor_id'])) {
            $floor = Location::find($f['floor_id']);
            $roomIds = array_merge($roomIds, Location::where('sort_key', 'like', ($floor?->sort_key ?? '-').'/%')->pluck('id')->all(), [$f['floor_id']]);
        }
        $q->where(function ($w) use ($f, $roomIds) {
            $w->whereHas('asset', function ($a) use ($f, $roomIds) {
                $a->whereNotIn('lifecycle_status', ['disposed', 'lost']);
                if (empty($f['include_unverified'])) {
                    $a->where('verification_status', 'verified');
                }
                if ($roomIds) {
                    $a->whereIn('location_id', $roomIds);
                }
                if (! empty($f['category_ids'])) {
                    $a->whereIn('category_id', $f['category_ids']);
                }
                if (! empty($f['generation_batch_id'])) {
                    $a->where('generation_batch_id', $f['generation_batch_id']);
                }
                if (! empty($f['asset_ids'])) {
                    $a->whereIn('id', $f['asset_ids']);
                }
            });
            if (! empty($f['include_groups']) && empty($f['asset_ids'])) {
                $w->orWhereHas('quantityGroup', function ($g) use ($f, $roomIds) {
                    $g->where('status', '!=', 'retired');
                    if ($roomIds) {
                        $g->whereIn('location_id', $roomIds);
                    }
                    if (! empty($f['category_ids'])) {
                        $g->whereIn('category_id', $f['category_ids']);
                    }
                    if (! empty($f['generation_batch_id'])) {
                        $g->where('generation_batch_id', $f['generation_batch_id']);
                    }
                });
            }
        });

        $labels = $q->get();
        $state = $f['print_state'] ?? 'all';
        if ($state === 'never_printed') {
            $labels = $labels->where('print_items_count', 0);
        } elseif ($state === 'not_installed') {
            $labels = $labels->reject(fn ($l) => $l->currentInstallationStatus() === 'installed');
        }

        return $labels->sortBy(fn (AssetLabel $l) => implode('|', [
            $this->targetLocation($l)?->sort_key ?? 'zzz',
            str_pad((string) ($l->asset?->category?->code ?? $l->quantityGroup?->category?->code ?? ''), 20),
            str_pad((string) ($l->asset?->slotAssignment?->slot?->item_key ?? ''), 40),
            str_pad((string) ($l->asset?->slotAssignment?->slot?->unit_position ?? 0), 4, '0', STR_PAD_LEFT),
            $l->asset?->asset_code ?? $l->quantityGroup?->group_code,
        ]))->values();
    }

    public function targetLocation(AssetLabel $l): ?Location
    {
        return $l->asset?->location ?? $l->quantityGroup?->location;
    }

    /** Hitung tata letak lembar sebelum PDF dibuat. */
    public const CALIBRATION_FIELDS = ['margin_left', 'margin_top', 'gap_x', 'gap_y', 'cols', 'rows'];

    /** Profil media + kalibrasi tersimpan (geser posisi/jarak sesuai printer & lembar label yang dipakai). */
    public static function profile(?string $key): array
    {
        $profiles = config('assetcare.label_profiles');
        $key = isset($profiles[$key]) ? $key : 'a4-50x30';
        $p = $profiles[$key];
        $cal = json_decode((string) \App\Models\AppSetting::get('label_cal.'.$key, ''), true);
        if (is_array($cal) && $p['type'] === 'a4') {
            foreach (self::CALIBRATION_FIELDS as $f) {
                if (isset($cal[$f]) && is_numeric($cal[$f])) {
                    $p[$f] = in_array($f, ['cols', 'rows'], true) ? max(1, (int) $cal[$f]) : (float) $cal[$f];
                }
            }
        }

        return $p + ['key' => $key];
    }

    public static function saveCalibration(string $key, array $values, User $user): void
    {
        if (! $user->hasPermission('label.print')) {
            throw WorkflowException::forbidden();
        }
        $base = config('assetcare.label_profiles.'.$key);
        if (! $base || $base['type'] !== 'a4') {
            throw WorkflowException::invalid('Profil ini tidak perlu kalibrasi.');
        }
        $clean = [];
        foreach (self::CALIBRATION_FIELDS as $f) {
            if (isset($values[$f]) && is_numeric($values[$f])) {
                $v = (float) $values[$f];
                $clean[$f] = in_array($f, ['cols', 'rows'], true) ? max(1, min(12, (int) $v)) : max(0, min(150, round($v, 1)));
            }
        }
        // label harus muat di kertas
        $p = array_merge($base, $clean);
        if ($p['margin_left'] + $p['cols'] * $p['label_w'] + ($p['cols'] - 1) * $p['gap_x'] > $p['page_w'] + 0.01
            || $p['margin_top'] + $p['rows'] * $p['label_h'] + ($p['rows'] - 1) * $p['gap_y'] > $p['page_h'] + 0.01) {
            throw WorkflowException::invalid('Ukuran melebihi kertas. Kurangi margin, jarak, atau jumlah kolom/baris.');
        }
        \App\Models\AppSetting::put(['label_cal.'.$key => json_encode($clean)], $user->id);
    }

    /** PDF lembar uji: garis tiap label + contoh isi, dicetak di kertas biasa lalu ditumpuk dengan lembar label. */
    public function testSheet(string $key): \Barryvdh\DomPDF\PDF
    {
        $p = self::profile($key);
        $per = $p['cols'] * $p['rows'];
        $qr = 'data:image/png;base64,'.base64_encode($this->qrPng(url('/q/CONTOH-UJI-LABEL-QR-0000')));
        $items = collect(range(1, $per))->map(fn ($n) => ['qr' => $qr, 'item' => (object) [
            'cell_no' => $n, 'sequence' => $n, 'code_snapshot' => 'MB-08-819-TV-0'.(($n - 1) % 9 + 1),
            'name_snapshot' => 'Contoh label sel '.$n, 'location_snapshot' => 'Kamar 819 · Lt 8',
        ]]);
        $batch = (object) ['number' => 'UJI-'.strtoupper($key)];

        return Pdf::loadView('labels.pdf', ['batch' => $batch, 'p' => $p, 'sheets' => collect([1 => $items]), 'guides' => true, 'test' => true])
            ->setPaper([0, 0, $p['page_w'] * 72 / 25.4, $p['page_h'] * 72 / 25.4])
            ->setOption(['dpi' => 300, 'isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    }

    public function layout(int $count, string $profileKey, int $startCell = 1, int $copies = 1): array
    {
        $p = self::profile($profileKey);
        $per = max(1, $p['cols'] * $p['rows']);
        $start = $p['type'] === 'a4' ? max(1, min($startCell, $per)) : 1;
        $total = $count * max(1, $copies);
        $sheets = (int) ceil(($total + $start - 1) / $per);

        return ['per_sheet' => $per, 'start' => $start, 'labels' => $total, 'sheets' => max($sheets, $total ? 1 : 0), 'profile' => $p];
    }

    public function createBatch(Collection $labels, string $profileKey, int $startCell, User $user, array $filters = [], string $purpose = 'new', ?string $reason = null, int $copies = 1): LabelPrintBatch
    {
        if (! $user->hasPermission('label.print')) {
            throw WorkflowException::forbidden();
        }
        if ($labels->isEmpty()) {
            throw WorkflowException::invalid('Tidak ada label yang cocok dengan filter.');
        }
        if ($purpose === 'reprint' && blank($reason)) {
            throw WorkflowException::invalid('Cetak ulang wajib disertai alasan.');
        }
        $lay = $this->layout($labels->count(), $profileKey, $startCell, $copies);

        return DB::transaction(function () use ($labels, $profileKey, $user, $filters, $purpose, $reason, $lay, $copies) {
            $first = $labels->first();
            $batch = LabelPrintBatch::create([
                'property_id' => $first->property_id, 'number' => Sequence::next('LBL', $first->property_id),
                'template_version' => config('assetcare.label_template_version'), 'media_profile' => $profileKey, 'start_cell' => $lay['start'],
                'filters' => $filters, 'purpose' => $purpose, 'reprint_reason' => $reason, 'label_count' => $lay['labels'], 'sheet_count' => $lay['sheets'],
                'status' => 'ready', 'generated_at' => now(), 'operator_id' => $user->id,
            ]);
            $slot = $lay['start'] - 1;
            $seq = 0;
            foreach ($labels as $l) {
                for ($c = 1; $c <= max(1, $copies); $c++) {
                    $loc = $this->targetLocation($l);
                    LabelPrintItem::create([
                        'batch_id' => $batch->id, 'asset_label_id' => $l->id, 'asset_id' => $l->asset_id, 'quantity_group_id' => $l->quantity_group_id,
                        'location_id' => $loc?->id, 'location_snapshot' => $loc?->labelText(),
                        'name_snapshot' => mb_substr($l->asset?->name ?? $l->quantityGroup?->name ?? '-', 0, 255),
                        'code_snapshot' => $l->asset?->asset_code ?? $l->quantityGroup?->group_code,
                        'template_version' => $batch->template_version,
                        'sheet_no' => intdiv($slot, $lay['per_sheet']) + 1, 'cell_no' => ($slot % $lay['per_sheet']) + 1,
                        'copy_no' => $c, 'reprint_reason' => $purpose === 'reprint' ? $reason : null, 'sequence' => ++$seq,
                    ]);
                    $slot++;
                }
            }

            return $batch;
        });
    }

    /** PDF berdimensi aktual (cetak pada skala 100%). */
    public function pdf(LabelPrintBatch $batch, bool $guides = false): \Barryvdh\DomPDF\PDF
    {
        $batch->loadMissing('items.label');
        $p = $batch->profile();
        $items = $batch->items->map(fn ($i) => [
            'item' => $i,
            'qr' => 'data:image/png;base64,'.base64_encode($this->qrPng($i->label->url())),
        ]);
        $sheets = $items->groupBy(fn ($x) => $x['item']->sheet_no);

        return Pdf::loadView('labels.pdf', ['batch' => $batch, 'p' => $p, 'sheets' => $sheets, 'guides' => $guides])
            ->setPaper([0, 0, $p['page_w'] * 72 / 25.4, $p['page_h'] * 72 / 25.4])
            ->setOption(['dpi' => 300, 'isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    }

    public function markDownloaded(LabelPrintBatch $batch): void
    {
        if (! $batch->downloaded_at) {
            $batch->update(['downloaded_at' => now(), 'status' => $batch->status === 'ready' ? 'downloaded' : $batch->status]);
        }
    }

    public function confirmPrinted(LabelPrintBatch $batch, User $user): void
    {
        $batch->update(['printed_confirmed_at' => now(), 'printed_confirmed_by' => $user->id, 'status' => 'printed']);
    }

    /** Rotasi token: label lama tidak berlaku, label baru perlu dicetak & dipasang ulang. */
    public function rotate(AssetLabel $label, string $reason, User $user): AssetLabel
    {
        if (! $user->hasPermission('label.rotate')) {
            throw WorkflowException::forbidden();
        }
        if (blank($reason)) {
            throw WorkflowException::invalid('Alasan rotasi wajib diisi.');
        }

        return DB::transaction(function () use ($label, $reason, $user) {
            $label = AssetLabel::whereKey($label->id)->lockForUpdate()->first();
            if ($label->status !== 'active') {
                throw WorkflowException::invalid('Label sudah tidak aktif.');
            }
            $label->auditReason = $reason;
            $label->update(['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => $reason, 'revoked_by' => $user->id, 'active_target_key' => null]);
            $new = AssetLabel::issueFor($label->asset, $label->quantityGroup);
            InstallationEvent::create([
                'asset_label_id' => $new->id, 'asset_id' => $new->asset_id, 'quantity_group_id' => $new->quantity_group_id,
                'location_id' => $this->targetLocation($new)?->id, 'status' => 'needs_relabel', 'note' => 'Rotasi token: '.$reason,
                'actor_id' => $user->id, 'occurred_at' => now(),
            ]);
            $this->syncInstallStatus($new);

            return $new;
        });
    }

    public function syncInstallStatus(AssetLabel $label): void
    {
        $status = $label->installationEvents()->first()?->status ?? 'pending';
        $label->asset?->updateQuietly(['installation_status' => $status]);
        $label->quantityGroup?->updateQuietly(['installation_status' => $status]);
    }

    /** Mutasi: tulisan lokasi pada label terakhir tidak sesuai → peringatan needs_relabel. */
    public function flagRelabelIfLocationChanged(\App\Models\Asset $asset, User $user): bool
    {
        $label = $asset->activeLabel;
        $last = $label?->printItems()->latest('id')->first();
        if (! $label || ! $last || $last->location_snapshot === $asset->location?->labelText()) {
            return false;
        }
        InstallationEvent::create([
            'asset_label_id' => $label->id, 'asset_id' => $asset->id, 'location_id' => $asset->location_id, 'print_item_id' => $last->id,
            'status' => 'needs_relabel', 'note' => 'Lokasi berubah: label tertulis "'.$last->location_snapshot.'"', 'actor_id' => $user->id, 'occurred_at' => now(),
        ]);
        $this->syncInstallStatus($label);

        return true;
    }
}
