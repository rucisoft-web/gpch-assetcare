<?php

namespace App\Http\Controllers\Field;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLabel;
use App\Models\AssetQuantityGroup;
use App\Models\Attachment;
use App\Models\InstallationEvent;
use App\Models\Location;
use App\Models\ScanLog;
use App\Models\User;
use App\Services\AssetService;
use App\Services\InstallationService;
use App\Services\LabelService;
use App\Services\QrResolver;
use App\Services\WorkflowException;
use App\Support\Fmt;
use App\Support\Help\RoleGuide;
use App\Support\ImageCompressor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Menu Lapangan (/lapangan): tampilan ponsel untuk petugas identifikasi aset.
 * Terpisah dari panel admin: login sendiri, menu sendiri (Beranda, Scan, Lokasi, Cari, Akun).
 */
class FieldController extends Controller
{
    // ---------------- login ----------------
    public function loginForm(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('field.home') : view('field.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'field-login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.'])->onlyInput('email');
        }
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'E-mail atau kata sandi salah.'])->onlyInput('email');
        }
        if (filled($user->app_authentication_secret)) {
            // akun dengan 2FA memakai halaman login utama yang meminta kode autentikator
            return redirect('/admin/login')->with('status', 'Akun Anda memakai verifikasi dua langkah. Silakan login di halaman ini.');
        }
        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('field.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('field.login');
    }

    public function passwordForm(): View
    {
        return view('field.password');
    }

    public function passwordSave(Request $request): RedirectResponse
    {
        $request->validate(['current' => 'required|current_password', 'password' => ['required', 'confirmed', Password::defaults()]],
            ['current.current_password' => 'Kata sandi lama salah.']);
        $request->user()->forceFill(['password' => $request->input('password'), 'must_change_password' => false, 'password_changed_at' => now()])->save();

        return redirect()->route('field.home')->with('ok', 'Kata sandi diganti.');
    }

    // ---------------- beranda ----------------
    public function home(Request $request): View
    {
        $base = Asset::whereIn('lifecycle_status', ['draft', 'active']);
        $total = (clone $base)->count();
        $verified = (clone $base)->where('verification_status', 'verified')->count();
        $mine = Asset::where('verified_by', $request->user()->id)->where('verified_at', '>=', now(Fmt::tz())->startOfDay()->utc());

        return view('field.home', [
            'total' => $total, 'verified' => $verified,
            'discrepancy' => (clone $base)->where('verification_status', 'discrepancy')->count(),
            'mineToday' => (clone $mine)->count(),
            'recent' => Asset::where('verified_by', $request->user()->id)->with('location')->latest('verified_at')->limit(8)->get(),
            'buildings' => $this->children(null),
        ]);
    }

    // ---------------- scan ----------------
    public function guide(): View
    {
        return view('field.guide', ['g' => RoleGuide::get('field-officer')]);
    }

    public function scan(): View
    {
        return view('field.scan');
    }

    public function resolve(Request $request, QrResolver $resolver): RedirectResponse
    {
        $kode = trim((string) $request->input('kode'));
        $r = $resolver->resolve($kode);
        ScanLog::create([
            'user_id' => $request->user()->id, 'asset_label_id' => $r['label']->id ?? null, 'asset_id' => $r['asset']->id ?? null,
            'result' => match ($r['type']) {
                'asset' => 'ok', 'group' => 'group', 'revoked' => 'revoked', 'foreign' => 'foreign', default => 'unknown'
            },
            'channel' => 'field', 'ip_address' => $request->ip(), 'scanned_at' => now(),
        ]);

        return match ($r['type']) {
            'asset' => redirect()->route('field.asset', $r['asset']),
            'group' => redirect()->route('field.group', $r['group']),
            'revoked' => redirect()->route('field.scan')->with('err', 'Label ini sudah tidak berlaku. Laporkan ke Asset Controller agar label diganti.'),
            default => redirect()->route('field.scan')->with('err', $r['message'] ?? 'Label tidak dikenal.'),
        };
    }

    // ---------------- lokasi ----------------
    public function location(Request $request, ?Location $location = null): View
    {
        $assets = collect();
        $groups = collect();
        if ($location) {
            $q = Asset::where('location_id', $location->id)->whereIn('lifecycle_status', ['draft', 'active'])->with('category');
            $filter = $request->query('f', 'semua');
            if ($filter === 'belum') {
                $q->where('verification_status', '!=', 'verified');
            }
            $assets = $q->orderByRaw("verification_status = 'verified'")->orderBy('name')->get();
            $groups = AssetQuantityGroup::where('location_id', $location->id)->whereNotIn('status', ['retired', 'disposed'])->orderBy('name')->get();
        }

        return view('field.location', [
            'location' => $location, 'children' => $this->children($location?->id), 'assets' => $assets, 'groups' => $groups,
            'trail' => $location ? $this->trail($location) : [], 'filter' => $request->query('f', 'semua'),
            'stat' => $location ? $this->counts()[$location->id] ?? ['t' => 0, 'v' => 0] : null,
        ]);
    }

    public function search(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $assets = $rooms = collect();
        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $assets = Asset::whereIn('lifecycle_status', ['draft', 'active'])
                ->where(fn ($w) => $w->where('asset_code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('serial_number', 'like', $like)->orWhere('legacy_code', 'like', $like)->orWhere('former_codes', 'like', $like))
                ->with('location')->orderBy('name')->limit(40)->get();
            $rooms = Location::where('is_active', true)->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('room_ref', 'like', $like))
                ->orderBy('sort_key')->limit(15)->get();
        }

        return view('field.search', compact('q', 'assets', 'rooms'));
    }

    // ---------------- identifikasi aset ----------------
    public function asset(Asset $asset): View
    {
        $asset->load('category', 'location', 'verifier');

        return view('field.asset', [
            'asset' => $asset,
            'next' => $this->nextUnverified($asset),
        ]);
    }

    public function identify(Request $request, Asset $asset, AssetService $svc): RedirectResponse
    {
        $d = $request->validate([
            'found' => 'required|in:1,0',
            'condition_grade' => 'nullable|in:'.implode(',', array_keys(Asset::CONDITIONS)),
            'operational_status' => 'nullable|in:'.implode(',', array_keys(Asset::OPERATIONAL)),
            'brand' => 'nullable|string|max:100', 'model' => 'nullable|string|max:100', 'serial_number' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:1000',
            'photos' => 'nullable|array|max:4', 'photos.*' => 'image|max:20480',
        ]);
        if ($d['found'] === '0' && blank($d['note'] ?? null)) {
            return back()->withErrors(['note' => 'Tulis keterangan bila aset tidak ditemukan.'])->withInput();
        }
        if ($d['found'] === '1' && blank($d['condition_grade'] ?? null)) {
            return back()->withErrors(['condition_grade' => 'Pilih kondisi aset.'])->withInput();
        }
        $user = $request->user();
        try {
            DB::transaction(function () use ($asset, $d, $user, $svc, $request) {
                $svc->verify($asset, ['found' => $d['found'] === '1', 'note' => $d['note'] ?? null,
                    'brand' => $d['brand'] ?? null, 'model' => $d['model'] ?? null, 'serial_number' => $d['serial_number'] ?? null,
                    'condition_grade' => $d['condition_grade'] ?? null], $user);
                $extra = [];
                if (! empty($d['operational_status'])) {
                    $extra['operational_status'] = $d['operational_status'];
                }
                if (filled($d['note'] ?? null)) {
                    $extra['notes'] = trim(($asset->notes ? $asset->notes."\n" : '').'['.now(Fmt::tz())->format('d/m/Y H:i').' '.$user->name.' · lapangan] '.$d['note']);
                }
                if ($extra) {
                    $asset->auditReason = 'Identifikasi lapangan';
                    $asset->fill($extra)->save();
                }
                foreach ($request->file('photos', []) as $i => $file) {
                    $this->storePhoto($asset, $file, $user, $i === 0);
                }
            });
        } catch (WorkflowException $e) {
            return back()->withErrors(['found' => $e->getMessage()])->withInput();
        }

        if ($request->boolean('pasang') && $asset->location_id) {
            return redirect()->route('field.install', $asset->location_id)->with('ok', $asset->asset_code.' tersimpan. Scan label berikutnya.');
        }
        $next = $this->nextUnverified($asset->fresh());

        return $next
            ? redirect()->route('field.asset', $next)->with('ok', $asset->asset_code.' tersimpan. Lanjut aset berikutnya di lokasi yang sama.')
            : redirect()->route('field.location', $asset->location_id)->with('ok', $asset->asset_code.' tersimpan. Semua aset di lokasi ini sudah diidentifikasi.');
    }

    // ---------------- aset temuan (belum terdaftar) ----------------
    public function newForm(Location $location): View
    {
        return view('field.new', ['location' => $location, 'categories' => AssetCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id')]);
    }

    public function newSave(Request $request, Location $location): RedirectResponse
    {
        $d = $request->validate([
            'name' => 'required|string|max:200', 'category_id' => 'required|exists:asset_categories,id',
            'brand' => 'nullable|string|max:100', 'model' => 'nullable|string|max:100', 'serial_number' => 'nullable|string|max:100',
            'condition_grade' => 'required|in:'.implode(',', array_keys(Asset::CONDITIONS)),
            'operational_status' => 'required|in:'.implode(',', array_keys(Asset::OPERATIONAL)),
            'note' => 'nullable|string|max:1000', 'photos' => 'nullable|array|max:4', 'photos.*' => 'image|max:20480',
        ]);
        $user = $request->user();
        $asset = DB::transaction(function () use ($d, $location, $user, $request) {
            $cat = AssetCategory::find($d['category_id']);
            $a = new Asset([
                'property_id' => $location->property_id, 'name' => $d['name'], 'category_id' => $cat->id, 'location_id' => $location->id,
                'brand' => $d['brand'] ?? null, 'model' => $d['model'] ?? null, 'serial_number' => $d['serial_number'] ?? null,
                'condition_grade' => $d['condition_grade'], 'operational_status' => $d['operational_status'], 'criticality' => $cat->default_criticality,
                'lifecycle_status' => 'draft', 'verification_status' => 'verified', 'verified_by' => $user->id, 'verified_at' => now(), 'last_inspected_at' => now(),
                'ownership' => 'milik', 'migration_source' => 'Temuan lapangan',
                'notes' => '['.now(Fmt::tz())->format('d/m/Y H:i').' '.$user->name.' · lapangan] Aset temuan, belum ada di register.'.(filled($d['note'] ?? null) ? ' '.$d['note'] : ''),
            ]);
            $a->auditReason = 'Aset temuan saat identifikasi lapangan';
            $a->save();
            AssetLabel::issueFor($a);
            foreach ($request->file('photos', []) as $i => $file) {
                $this->storePhoto($a, $file, $user, $i === 0);
            }

            return $a;
        });

        return redirect()->route('field.location', $location)->with('ok', 'Aset temuan '.$asset->asset_code.' ditambahkan (draft). Label QR bisa dicetak oleh Asset Controller.');
    }

    // ---------------- kelompok jumlah ----------------
    public function group(AssetQuantityGroup $group): View
    {
        return view('field.group', ['group' => $group->load('location', 'category')]);
    }

    public function groupCount(Request $request, AssetQuantityGroup $group): RedirectResponse
    {
        $d = $request->validate(['qty' => 'required|numeric|min:0', 'reason' => 'nullable|string|max:500']);
        if ($group->expected_qty !== null && (float) $d['qty'] !== (float) $group->expected_qty && blank($d['reason'] ?? null)) {
            return back()->withErrors(['reason' => 'Jumlah berbeda dari standar; isi catatan selisih.'])->withInput();
        }
        $group->recordCount((float) $d['qty'], trim('Hitung lapangan. '.($d['reason'] ?? '')));

        if ($request->boolean('pasang')) {
            return redirect()->route('field.install', $group->location_id)->with('ok', $group->name.': '.Fmt::num($d['qty'], 0).' tercatat. Scan label berikutnya.');
        }

        return redirect()->route('field.location', $group->location_id)->with('ok', $group->name.': '.Fmt::num($d['qty'], 0).' tercatat.');
    }

    // ---------------- pasang label QR (per ruang) ----------------
    /** Daftar label yang harus ditempel di ruang ini + pemindai untuk konfirmasi pemasangan. */
    public function install(Location $location, LabelService $labels): View
    {
        $items = $labels->select(['room_ids' => [$location->id], 'include_unverified' => true, 'include_groups' => true])
            ->filter(fn (AssetLabel $l) => (int) ($l->asset?->location_id ?? $l->quantityGroup?->location_id) === (int) $location->id)->values();
        $done = $items->filter(fn (AssetLabel $l) => $l->currentInstallationStatus() === 'installed')->count();

        return view('field.install', ['location' => $location, 'items' => $items, 'done' => $done, 'trail' => $this->trail($location)]);
    }

    /** Hasil scan di mode pasang: catat terpasang (dicek harus milik ruang ini), lalu buka form pengecekan aset. */
    public function installScan(Request $request, Location $location, InstallationService $svc): RedirectResponse
    {
        $kode = mb_substr(trim((string) $request->input('kode')), 0, 500);
        if ($kode === '') {
            return back()->with('err', 'Kode kosong.');
        }
        try {
            $r = $svc->confirm($location, $kode, $request->user());
        } catch (WorkflowException $e) {
            return back()->with('err', $e->getMessage());
        }
        if (! $r['ok']) {
            return redirect()->route('field.install', $location)->with('err', $r['message']);
        }
        $label = $r['event']->label ?? (app(QrResolver::class)->resolve($kode)['label'] ?? null);
        $asset = $label?->asset;
        if ($asset && $asset->verification_status !== 'verified') {
            return redirect()->route('field.asset', [$asset, 'pasang' => 1])->with('ok', 'Label terpasang. Sekarang periksa kondisi aset, ambil foto, lalu simpan.');
        }
        if ($label?->quantityGroup && $label->quantityGroup->on_hand === null) {
            return redirect()->route('field.group', [$label->quantityGroup, 'pasang' => 1])->with('ok', 'Label terpasang. Hitung jumlahnya lalu simpan.');
        }

        return redirect()->route('field.install', $location)->with('ok', $r['message']);
    }

    /** Label tidak bisa dipasang: rusak, hilang, atau perlu dicetak ulang. Masuk antrean cetak ulang di ruang IT. */
    public function installMark(Request $request, Location $location, AssetLabel $label, LabelService $labels): RedirectResponse
    {
        $d = $request->validate(['status' => 'required|in:damaged,missing,needs_relabel', 'note' => 'nullable|string|max:300']);
        $target = $label->asset ?? $label->quantityGroup;
        if (! $target || (int) $target->location_id !== (int) $location->id) {
            abort(404);
        }
        InstallationEvent::create([
            'asset_label_id' => $label->id, 'asset_id' => $label->asset_id, 'quantity_group_id' => $label->quantity_group_id,
            'location_id' => $location->id, 'status' => $d['status'], 'note' => trim('Lapangan. '.($d['note'] ?? '')),
            'actor_id' => $request->user()->id, 'occurred_at' => now(),
        ]);
        $labels->syncInstallStatus($label);

        return redirect()->route('field.install', $location)->with('ok', ($target->asset_code ?? $target->group_code).': '.InstallationEvent::STATUSES[$d['status']].'. Label akan masuk daftar cetak ulang.');
    }

    // ---------------- bantu ----------------
    /** Jumlah aset (t) & terverifikasi (v) per lokasi, termasuk turunan. @return array<int,array{t:int,v:int}> */
    private function counts(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $parents = Location::pluck('parent_id', 'id')->all();
        $rows = Asset::whereIn('lifecycle_status', ['draft', 'active'])->whereNotNull('location_id')
            ->selectRaw("location_id, count(*) t, sum(verification_status = 'verified') v")->groupBy('location_id')->get();
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->location_id;
            $guard = 0;
            while ($id && $guard++ < 10) {
                $out[$id]['t'] = ($out[$id]['t'] ?? 0) + (int) $r->t;
                $out[$id]['v'] = ($out[$id]['v'] ?? 0) + (int) $r->v;
                $id = (int) ($parents[$id] ?? 0);
            }
        }

        return $memo = $out;
    }

    private function children(?int $parentId)
    {
        $c = $this->counts();

        return Location::where('is_active', true)->where('parent_id', $parentId)->orderBy('sort_key')->get()
            ->map(fn ($l) => ['loc' => $l, 't' => $c[$l->id]['t'] ?? 0, 'v' => $c[$l->id]['v'] ?? 0])
            ->filter(fn ($x) => $x['t'] > 0 || $x['loc']->location_type === 'room' || Location::where('parent_id', $x['loc']->id)->exists())
            ->values();
    }

    private function trail(Location $l): array
    {
        $out = [];
        $guard = 0;
        while ($l && $guard++ < 10) {
            array_unshift($out, $l);
            $l = $l->parent_id ? Location::find($l->parent_id) : null;
        }

        return $out;
    }

    private function nextUnverified(Asset $asset): ?Asset
    {
        return $asset->location_id ? Asset::where('location_id', $asset->location_id)->whereKeyNot($asset->id)
            ->whereIn('lifecycle_status', ['draft', 'active'])->where('verification_status', 'unverified')->orderBy('name')->first() : null;
    }

    /** Foto apa pun dijadikan JPEG < 500 KB (maks. 1600 px) agar hemat ruang & cepat diunggah. */
    private function storePhoto(Asset $asset, UploadedFile $file, $user, bool $primary): void
    {
        $raw = file_get_contents($file->getRealPath());
        $jpg = ImageCompressor::toJpeg($raw) ?? $raw;
        $mime = 'image/jpeg';
        $sum = hash('sha256', $jpg);
        $path = 'assets/photos/lapangan/'.substr($sum, 0, 2).'/'.$sum.'.jpg';
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            $disk->put($path, $jpg);
        }
        Attachment::create([
            'attachable_type' => $asset->getMorphClass(), 'attachable_id' => $asset->id, 'kind' => 'photo', 'disk' => 'local', 'path' => $path,
            'original_name' => $file->getClientOriginalName() ?: 'foto.jpg', 'mime' => $mime, 'size' => strlen($jpg), 'checksum' => $sum,
            'note' => 'Foto identifikasi lapangan', 'visibility' => 'internal', 'uploaded_by' => $user->id,
        ]);
        if ($primary) {
            $asset->forceFill(['photo_path' => $path])->saveQuietly();
        }
    }
}
