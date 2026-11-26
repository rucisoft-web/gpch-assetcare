<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\DocumentType;
use App\Models\Attachment;
use App\Models\User;
use App\Support\Fmt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Register dokumen: berkas berversi (hash SHA-256), perpanjangan, penggantian, pencabutan, pengingat. */
class DocumentService
{
    public const DISK = 'local';

    /** Simpan berkas yang sudah diunggah ke disk privat sebagai versi berikutnya. */
    public function addFile(AssetDocument $doc, string $path, ?string $originalName, ?User $by, ?string $note = null): Attachment
    {
        $disk = Storage::disk(self::DISK);
        $version = ((int) $doc->files()->max('file_version')) + 1;

        return $doc->files()->create([
            'kind' => 'document',
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $originalName ?: basename($path),
            'mime' => $disk->mimeType($path),
            'size' => $disk->size($path),
            'checksum' => hash('sha256', $disk->get($path)),
            'file_version' => $version,
            'note' => $note,
            'visibility' => 'internal',
            'uploaded_by' => $by?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  field kartu dokumen + 'asset_ids' + 'files' (path => nama asli)
     */
    public function create(array $data, ?User $by): AssetDocument
    {
        return DB::transaction(function () use ($data, $by) {
            $assetIds = array_filter((array) ($data['asset_ids'] ?? []));
            $files = (array) ($data['files'] ?? []);
            unset($data['asset_ids'], $data['files']);
            $doc = AssetDocument::create($data + ['created_by' => $by?->id]);
            if ($assetIds) {
                $doc->assets()->sync(Asset::whereIn('id', $assetIds)->pluck('id'));
            }
            foreach ($files as $path => $name) {
                $this->addFile($doc, $path, $name, $by);
            }

            return $doc;
        });
    }

    /**
     * Unggah cepat dokumen legalitas: banyak berkas sekaligus, sebagai satu dokumen (semua berkas = versi/lampiran)
     * atau satu dokumen per berkas (judul dari nama berkas). Semua ditautkan ke aset yang dipilih.
     *
     * @param  array<string, string>  $files  path => nama asli
     * @return list<AssetDocument>
     */
    public function quickUpload(array $assetIds, int $typeId, array $files, bool $perFile, array $card, ?User $by): array
    {
        $type = DocumentType::findOrFail($typeId);
        $base = $card + [
            'document_type_id' => $type->id,
            'classification' => $card['classification'] ?? $type->default_classification ?? 'internal',
            'pic_id' => $card['pic_id'] ?? $by?->id,
            'no_expiry' => (bool) ($card['no_expiry'] ?? ! $type->has_expiry),
        ];
        if ($base['no_expiry']) {
            $base['valid_until'] = null;
        }
        if (! $perFile) {
            return [$this->create($base + ['title' => $card['title'] ?? $type->name, 'asset_ids' => $assetIds, 'files' => $files], $by)];
        }
        $out = [];
        foreach ($files as $path => $name) {
            $title = trim(preg_replace('/[_\-]+/', ' ', pathinfo((string) $name, PATHINFO_FILENAME))) ?: $type->name;
            $out[] = $this->create($base + ['title' => \Illuminate\Support\Str::limit($title, 255, ''), 'asset_ids' => $assetIds, 'files' => [$path => $name]], $by);
        }

        return $out;
    }

    public function startRenewal(AssetDocument $doc, array $data): AssetDocument
    {
        $doc->fill([
            'renewal_submitted_at' => $data['renewal_submitted_at'] ?? now(Fmt::tz())->toDateString(),
            'renewal_ref' => $data['renewal_ref'] ?? null,
            'renewal_cost' => $data['renewal_cost'] ?? null,
        ]);
        $doc->auditReason = 'Perpanjangan diajukan';
        $doc->save();

        return $doc;
    }

    /**
     * Dokumen baru terbit: dibuat sebagai kartu baru dengan tautan aset yang sama; dokumen lama → Diganti (riwayat tetap).
     *
     * @param  array<string, mixed>  $data
     */
    public function replace(AssetDocument $old, array $data, ?User $by): AssetDocument
    {
        return DB::transaction(function () use ($old, $data, $by) {
            $base = $old->only(['document_type_id', 'title', 'issuer', 'pic_id', 'backup_pic_id', 'original_location', 'original_location_note',
                'vendor_id', 'location_id', 'classification', 'notice_days', 'contract_value', 'premium', 'sum_insured']);
            $new = $this->create(array_merge($base, $data, ['asset_ids' => $old->assets()->pluck('assets.id')->all()]), $by);
            $old->replaced_by_id = $new->id;
            $old->auditReason = 'Diganti oleh '.$new->register_no;
            $old->save();

            return $new;
        });
    }

    public function revoke(AssetDocument $doc, string $reason): AssetDocument
    {
        $doc->fill(['revoked_at' => now(), 'revoke_reason' => $reason]);
        $doc->auditReason = $reason;
        $doc->save();

        return $doc;
    }

    /** Hitung ulang status semua dokumen hidup (dijalankan harian; status juga dihitung saat disimpan). */
    public function refreshStatuses(?CarbonImmutable $today = null): int
    {
        $n = 0;
        AssetDocument::withoutGlobalScopes()->whereNull('revoked_at')->whereNull('replaced_by_id')->chunkById(200, function ($docs) use ($today, &$n) {
            foreach ($docs as $d) {
                $status = $d->computeStatus($today);
                if ($status !== $d->status) {
                    AssetDocument::withoutGlobalScopes()->whereKey($d->id)->update(['status' => $status]);
                    $n++;
                }
            }
        });

        return $n;
    }

    /**
     * Kirim pengingat masa berlaku (bagian 4.1–4.3): PIC & cadangan pada setiap titik pengingat;
     * pemegang izin document.escalation ikut pada H-30 ke bawah dan saat kedaluwarsa. Deduplikasi per titik.
     */
    public function sendReminders(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now(Fmt::tz())->startOfDay();
        $notifier = app(NotificationService::class);
        $escalation = User::withPermission('document.escalation');
        $sent = 0;
        AssetDocument::withoutGlobalScopes()->with('type', 'pic', 'backupPic', 'assets')
            ->whereIn('status', ['valid', 'expiring', 'expired', 'renewing'])->whereNotNull('valid_until')->where('no_expiry', false)
            ->chunkById(200, function ($docs) use ($today, $notifier, $escalation, &$sent) {
                foreach ($docs as $d) {
                    $left = $d->daysLeft($today);
                    $offsets = $d->reminderOffsets();
                    if ($left === null || $left > $offsets[0] || ($d->status === 'renewing' && $left >= 0)) {
                        continue; // perpanjangan sudah diajukan: pengingat berhenti sampai dokumen benar-benar lewat
                    }
                    // titik pengingat yang sedang berlaku (terkecil yang >= sisa hari); lewat = 'expired'
                    $point = $left < 0 ? 'expired' : collect($offsets)->filter(fn ($o) => $o >= $left)->min();
                    $recipients = collect([$d->pic, $d->backupPic])->filter();
                    $escalate = $left <= 30;
                    if ($escalate) {
                        $recipients = $recipients->merge($escalation);
                    }
                    if ($recipients->isEmpty()) {
                        $recipients = User::withPermission('document.manage');
                    }
                    $assets = $d->assets->take(3)->pluck('asset_code')->join(', ').($d->assets->count() > 3 ? ' +'.($d->assets->count() - 3) : '');
                    $title = $left < 0
                        ? 'Dokumen kedaluwarsa: '.$d->title
                        : ($left === 0 ? 'Dokumen berakhir hari ini: ' : 'Dokumen berakhir H-'.$left.': ').$d->title;
                    $body = $d->register_no.' · '.($d->type?->name ?? '').' · berlaku s.d. '.$d->valid_until->format('d M Y')
                        .($assets ? ' · aset '.$assets : '').($d->type?->is_k3 && $left < 0 ? ' · Aset terkait TIDAK BOLEH DIOPERASIKAN sampai riksa uji diperbarui.' : '')
                        .($d->notice_days ? ' · batas pemberitahuan '.$d->notice_days.' hari sebelum berakhir' : '');
                    $url = \App\Filament\Resources\AssetDocuments\AssetDocumentResource::getUrl('view', ['record' => $d], panel: 'admin');
                    $key = 'doc:'.$d->id.':'.$d->valid_until->toDateString().':'.$point;
                    if ($notifier->send($recipients, $title, $body, $url, $key, $left < 0 ? 'danger' : ($escalate ? 'warning' : 'info'))) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Kekurangan dokumen wajib per aset (bagian 3.2). Hanya dokumen hidup (bukan diganti/dicabut) yang dihitung;
     * dokumen wajib berlaku (mis. riksa uji) yang kedaluwarsa juga dilaporkan.
     *
     * @return Collection<int, array{asset: Asset, class: string, missing: array<int, string>, expired: array<int, string>}>
     */
    public function missingDocuments(): Collection
    {
        $classes = \App\Models\DepreciationClass::with(['requirements' => fn ($q) => $q->where('is_mandatory', true)->with('documentType')])->get()->keyBy('id');
        $out = collect();
        Asset::query()->whereIn('lifecycle_status', ['draft', 'active'])->with(['category', 'financial', 'location', 'documents' => fn ($q) => $q->whereIn('asset_documents.status', AssetDocument::LIVE)])
            ->orderBy('asset_code')->chunk(300, function ($assets) use ($classes, $out) {
                foreach ($assets as $a) {
                    $class = $classes->get($a->financial?->depreciation_class_id ?? $a->category?->depreciation_class_id);
                    if (! $class || $class->requirements->isEmpty()) {
                        continue;
                    }
                    $byType = $a->documents->groupBy('document_type_id');
                    $missing = [];
                    $expired = [];
                    foreach ($class->requirements as $req) {
                        $docs = $byType->get($req->document_type_id);
                        if (! $docs) {
                            $missing[] = $req->documentType->code.' '.$req->documentType->name;
                        } elseif ($docs->every(fn ($d) => $d->status === 'expired')) {
                            $expired[] = $req->documentType->code.' '.$req->documentType->name;
                        }
                    }
                    if ($missing || $expired) {
                        $out->push(['asset' => $a, 'class' => $class->code, 'missing' => $missing, 'expired' => $expired]);
                    }
                }
            });

        return $out;
    }
}
