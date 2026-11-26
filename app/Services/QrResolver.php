<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLabel;
use App\Models\AssetQuantityGroup;

/** Validasi host & format payload QR. QR asing tidak dibuka otomatis. */
class QrResolver
{
    public function allowedHosts(): array
    {
        $hosts = [parse_url(config('assetcare.qr_base_url'), PHP_URL_HOST), parse_url(config('app.url'), PHP_URL_HOST)];

        return array_values(array_unique(array_filter(array_merge($hosts, config('assetcare.qr_extra_hosts', [])))));
    }

    /**
     * @return array{type:string, label?:AssetLabel, asset?:Asset, group?:AssetQuantityGroup, message?:string}
     *   type: label | asset | group | foreign | invalid | revoked | unknown
     */
    public function resolve(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return ['type' => 'invalid', 'message' => 'Isian kosong.'];
        }
        if (preg_match('#^https?://#i', $input)) {
            $host = parse_url($input, PHP_URL_HOST);
            $path = (string) parse_url($input, PHP_URL_PATH);
            if (! in_array($host, $this->allowedHosts(), true)) {
                return ['type' => 'foreign', 'message' => 'QR bukan label '.\App\Support\Brand::panelName().' ('.$host.'). Tidak dibuka.'];
            }
            if (! preg_match('#/q/([A-Za-z0-9_-]{20,64})$#', $path, $m)) {
                return ['type' => 'invalid', 'message' => 'Format QR tidak dikenal.'];
            }

            return $this->fromToken($m[1]);
        }
        if (preg_match('/^[A-Z0-9]{2,10}-(AST|QTY)-\d{4,8}$/i', $input)) {
            $code = strtoupper($input);
            if (str_contains($code, '-QTY-')) {
                $g = AssetQuantityGroup::where('group_code', $code)->first();

                return $g ? ['type' => 'group', 'group' => $g, 'label' => $g->activeLabel] : ['type' => 'unknown', 'message' => 'Kode kelompok tidak ditemukan.'];
            }
            $a = Asset::where('asset_code', $code)->first();

            return $a ? ['type' => 'asset', 'asset' => $a, 'label' => $a->activeLabel] : ['type' => 'unknown', 'message' => 'Kode aset tidak ditemukan.'];
        }
        // kode aset berbasis lokasi (mis. MB-08-819-TV-01), termasuk kode lama yang sudah diganti
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{3,49}$/', $input)) {
            $code = strtoupper($input);
            $a = Asset::where('asset_code', $code)->first()
                ?? Asset::where('former_codes', 'like', '%'.$code.'%')->get()->first(fn ($x) => in_array($code, explode(';', (string) $x->former_codes), true));
            if ($a) {
                return ['type' => 'asset', 'asset' => $a, 'label' => $a->activeLabel];
            }
        }
        if (preg_match('/^[A-Za-z0-9_-]{20,64}$/', $input)) {
            return $this->fromToken($input);
        }

        return ['type' => 'invalid', 'message' => 'Kode tidak ditemukan. Masukkan URL label atau kode aset seperti MB-08-819-TV-01.'];
    }

    public function fromToken(string $token): array
    {
        $label = AssetLabel::findByToken($token);
        if (! $label) {
            return ['type' => 'unknown', 'message' => 'Label tidak dikenal.'];
        }
        if ($label->status !== 'active') {
            return ['type' => 'revoked', 'label' => $label, 'message' => 'Label ini sudah tidak berlaku.'];
        }

        return $label->asset_id
            ? ['type' => 'asset', 'asset' => $label->asset, 'label' => $label]
            : ['type' => 'group', 'group' => $label->quantityGroup, 'label' => $label];
    }
}
