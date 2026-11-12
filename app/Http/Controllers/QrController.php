<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\RequestType;
use App\Models\Asset;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Halaman publik hasil pindai QR: identitas aset dan formulir lapor kerusakan tanpa login.
 * Token QR acak (40 karakter), bukan ID basis data.
 */
class QrController extends Controller
{
    protected function findAsset(string $token): Asset
    {
        $asset = Asset::withoutGlobalScopes()
            ->with(['location', 'company', 'category'])
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->first();

        abort_if(! $asset || ! $asset->company?->is_active, 404);

        return $asset;
    }

    public function show(string $token): View
    {
        return view('qr.show', ['asset' => $this->findAsset($token), 'token' => $token]);
    }

    public function report(Request $request, string $token): View
    {
        $asset = $this->findAsset($token);

        $data = $request->validate([
            'request_type' => ['required', Rule::enum(RequestType::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'reporter_name' => ['nullable', 'string', 'max:100'],
            'reporter_contact' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'max:'.config('asset.uploads.max_kb')],
            'website' => ['prohibited'], // perangkap bot
        ]);

        $maintenance = app(CurrentCompany::class)->runAs($asset->company, function () use ($asset, $data, $request) {
            return MaintenanceRequest::create([
                'asset_id' => $asset->id,
                'request_type' => $data['request_type'],
                'priority' => $data['priority'],
                'description' => '[Laporan QR] '.trim($data['description']),
                'reporter_id' => $request->user() instanceof User && $request->user()->activeMembership($asset->company_id) ? $request->user()->id : null,
                'reporter_name' => $data['reporter_name'] ?? null,
                'reporter_contact' => $data['reporter_contact'] ?? null,
                'via_qr' => true,
                'photo_before' => $request->file('photo')?->store('permintaan', 'local'),
            ]);
        });

        return view('qr.thanks', ['asset' => $asset, 'request' => $maintenance, 'token' => $token]);
    }

    /**
     * Label QR siap cetak (A4, 2 kolom). Hanya aset dari perusahaan tempat pengguna menjadi anggota.
     * Pilih aset lewat ?ids=1,2,3 atau ?location=ID (lokasi beserta seluruh turunannya).
     */
    public function labels(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))->map(fn ($id) => (int) $id)->filter()->take(500);
        $companyIds = $request->user()->memberships()->where('is_active', true)->pluck('company_id');

        $locationIds = null;
        if ($request->filled('location')) {
            $location = Location::withoutGlobalScopes()->whereIn('company_id', $companyIds)->find((int) $request->query('location'));
            abort_unless($location, 404);
            $locationIds = [$location->id, ...$location->descendantIds()];
        }

        $assets = Asset::withoutGlobalScopes()
            ->with(['location', 'company'])
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds)->where('is_active', true), fn ($q) => $q->whereIn('id', $ids))
            ->whereIn('company_id', $companyIds)
            ->limit(500)
            ->orderBy('code')
            ->get();

        abort_if($assets->isEmpty(), 404);

        return view('qr.labels', ['assets' => $assets]);
    }
}
