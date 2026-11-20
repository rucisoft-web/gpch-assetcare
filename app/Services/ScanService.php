<?php

namespace App\Services;

use App\Filament\Resources\AssetQuantityGroups\AssetQuantityGroupResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\ScanLog;
use App\Models\User;

/** Hasil scan → tujuan halaman + catatan scan_logs (scan biasa tidak mengubah status apa pun). */
class ScanService
{
    public function __construct(protected QrResolver $resolver) {}

    /** @return array{url:?string, message:?string, level:string} */
    public function handle(string $input, ?User $user, string $channel = 'scanner'): array
    {
        $r = $this->resolver->resolve($input);
        $result = match ($r['type']) {
            'asset' => 'ok', 'group' => 'group', 'revoked' => 'revoked', 'foreign' => 'foreign', default => 'unknown',
        };
        $canAsset = $user?->hasPermission('asset.view') || $user?->hasPermission('request.create');
        if (in_array($r['type'], ['asset', 'group'], true) && ! $canAsset) {
            $result = 'forbidden';
        }
        ScanLog::create([
            'user_id' => $user?->id, 'asset_label_id' => $r['label']->id ?? null, 'asset_id' => $r['asset']->id ?? null,
            'result' => $result, 'channel' => $channel, 'ip_address' => request()->ip(), 'scanned_at' => now(),
        ]);

        return match (true) {
            $result === 'forbidden' => ['url' => null, 'message' => 'Anda tidak memiliki akses ke aset ini.', 'level' => 'danger'],
            $r['type'] === 'asset' && $user?->hasPermission('asset.view') => ['url' => AssetResource::getUrl('view', ['record' => $r['asset']]), 'message' => null, 'level' => 'success'],
            // pelapor tanpa akses register langsung ke formulir lapor kerusakan
            $r['type'] === 'asset' => ['url' => \App\Filament\Resources\ServiceRequests\ServiceRequestResource::getUrl('create', ['asset' => $r['asset']->id]), 'message' => null, 'level' => 'success'],
            $r['type'] === 'group' => ['url' => AssetQuantityGroupResource::getUrl('view', ['record' => $r['group']]), 'message' => null, 'level' => 'success'],
            // label dicabut: pesan umum, tanpa membocorkan aset baru
            $r['type'] === 'revoked' => ['url' => null, 'message' => 'Label ini sudah tidak berlaku. Laporkan ke Engineering agar label diganti.', 'level' => 'danger'],
            default => ['url' => null, 'message' => $r['message'] ?? 'Label tidak dikenal.', 'level' => 'danger'],
        };
    }
}
