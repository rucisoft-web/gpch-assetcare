<?php

namespace App\Filament\App\Pages;

use App\Enums\IdentificationSource;
use App\Enums\InspectionResult;
use App\Enums\Priority;
use App\Enums\RequestType;
use App\Enums\VerificationStatus;
use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\Concerns\InteractsWithTenant;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Inspection;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Support\LabelSize;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Mode Lapangan: satu layar untuk petugas keliling dengan HP/handheld.
 * Pindai QR (kamera atau tombol scanner handheld) atau pilih lokasi, lalu verifikasi,
 * catat inspeksi, lapor kerusakan, tambah aset yang belum terdaftar, dan cetak label.
 */
class ModeLapangan extends Page
{
    use InteractsWithTenant;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Identifikasi Awal';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Mode Lapangan';

    protected static ?string $title = 'Mode Lapangan';

    protected static ?string $slug = 'mode-lapangan';

    protected string $view = 'filament.pages.mode-lapangan';

    #[Url(as: 'aset')]
    public ?int $assetId = null;

    #[Url(as: 'lokasi')]
    public ?int $locationId = null;

    #[Url(as: 'label')]
    public string $labelSize = '50x30';

    public string $scan = '';

    public function mount(): void
    {
        $this->labelSize = LabelSize::normalize(request()->query('label', request()->cookie('label_size', $this->labelSize)));
    }

    public function updatedLabelSize(): void
    {
        $this->labelSize = LabelSize::normalize($this->labelSize);
        Cookie::queue('label_size', $this->labelSize, 60 * 24 * 365);
    }

    public function getAsset(): ?Asset
    {
        return $this->assetId ? Asset::with(['location', 'category', 'department', 'verifiedBy'])->find($this->assetId) : null;
    }

    public function getLocation(): ?Location
    {
        return $this->locationId ? Location::find($this->locationId) : null;
    }

    /** @return array<int, string> */
    public function getLocationOptions(): array
    {
        return Location::query()->where('is_active', true)->orderBy('complete_name')->pluck('complete_name', 'id')->all();
    }

    /** Aset di lokasi terpilih (lokasi itu sendiri, tanpa sub-lokasi, agar daftar per ruang tetap ringkas). */
    public function getLocationAssets(): Collection
    {
        if (! $this->locationId) {
            return collect();
        }

        return Asset::query()->with('category')->where('location_id', $this->locationId)->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Location> sub-lokasi langsung, untuk turun dari lantai ke kamar. */
    public function getChildLocations(): Collection
    {
        return $this->locationId
            ? Location::query()->where('parent_id', $this->locationId)->where('is_active', true)->withCount('assets')->orderBy('name')->get()
            : collect();
    }

    public function canWrite(): bool
    {
        return static::canOperate();
    }

    /** Terima isi QR (URL /qr/{token}), token saja, atau kode aset (mis. AST/2026/00012). */
    public function submitScan(): void
    {
        $input = trim($this->scan);
        $this->scan = '';

        if ($input === '') {
            return;
        }

        $token = preg_match('#/qr/([A-Za-z0-9]{20,64})#', $input, $m) ? $m[1] : (preg_match('/^[A-Za-z0-9]{20,64}$/', $input) ? $input : null);

        $asset = Asset::query()
            ->where(fn ($q) => $q->when($token, fn ($q) => $q->where('qr_token', $token))->orWhere('code', $input))
            ->first();

        if (! $asset) {
            Notification::make()->title('Aset tidak ditemukan')->body('QR bukan milik properti ini, sudah dibuat ulang, atau kode salah.')->danger()->send();
            $this->dispatch('lapangan-focus');

            return;
        }

        $this->openAsset($asset->id);
    }

    public function openAsset(int $id): void
    {
        $asset = Asset::find($id);

        if ($asset) {
            $this->assetId = $asset->id;
            $this->locationId = $asset->location_id;
        }
    }

    public function closeAsset(): void
    {
        $this->assetId = null;
        $this->dispatch('lapangan-focus');
    }

    public function chooseLocation(?int $id): void
    {
        $this->locationId = $id ?: null;
        $this->assetId = null;
    }

    public function updatedLocationId(): void
    {
        $this->assetId = null;
    }

    public function mark(int $id, string $status): void
    {
        abort_unless($this->canWrite() && in_array($status, ['verified', 'missing'], true), 403);
        $asset = Asset::findOrFail($id);
        $value = VerificationStatus::from($status);
        $asset->markVerification($value);

        Notification::make()->title("{$asset->name}: {$value->getLabel()}")->success()->send();
    }

    public function labelUrl(array $query): string
    {
        return route('qr.labels', $query + ['size' => $this->labelSize]);
    }

    protected function currentAsset(): Asset
    {
        return Asset::findOrFail($this->assetId);
    }

    public function inspectAction(): Action
    {
        return Action::make('inspect')->label('Catat inspeksi')->icon(Heroicon::OutlinedClipboardDocumentCheck)->color('primary')
            ->visible(fn () => $this->canWrite() && $this->assetId)
            ->modalHeading(fn () => 'Inspeksi · '.$this->getAsset()?->name)
            ->schema([
                Select::make('result')->label('Hasil')->options(InspectionResult::class)->default(InspectionResult::Pass)->required(),
                TextInput::make('condition_score')->label('Skor kondisi (0–100)')->numeric()->integer()->minValue(0)->maxValue(100)
                    ->default(fn () => $this->getAsset()?->physical_condition ?? 80)->required(),
                Textarea::make('finding')->label('Temuan')->rows(2),
                Textarea::make('recommendation')->label('Rekomendasi')->rows(2),
                FileUpload::make('photo')->label('Foto')->image()->disk('local')->directory('inspeksi')->visibility('private')
                    ->maxSize(config('asset.uploads.max_kb')),
                Toggle::make('update_condition')->label('Perbarui kondisi fisik aset dengan skor ini')->default(true),
            ])
            ->action(function (array $data) {
                $asset = $this->currentAsset();
                $inspection = Inspection::create([
                    'asset_id' => $asset->id,
                    'result' => $data['result'],
                    'condition_score' => $data['condition_score'],
                    'finding' => $data['finding'] ?? null,
                    'recommendation' => $data['recommendation'] ?? null,
                    'photo' => $data['photo'] ?? null,
                ]);

                if ($data['update_condition'] ?? false) {
                    $asset->update(['physical_condition' => $data['condition_score']]);
                }

                Notification::make()->title("Inspeksi {$inspection->number} tersimpan.")->success()->send();
            });
    }

    public function reportAction(): Action
    {
        return Action::make('report')->label('Lapor kerusakan')->icon(Heroicon::OutlinedWrenchScrewdriver)->color('danger')
            ->visible(fn () => $this->canWrite() && $this->assetId)
            ->modalHeading(fn () => 'Permintaan pemeliharaan · '.$this->getAsset()?->name)
            ->schema([
                Select::make('request_type')->label('Jenis')->options(RequestType::class)->default(RequestType::Corrective)->required(),
                Select::make('priority')->label('Prioritas')->options(Priority::class)->default(Priority::Medium)->required(),
                Textarea::make('description')->label('Deskripsi masalah')->required()->minLength(5)->rows(3),
                FileUpload::make('photo_before')->label('Foto')->image()->disk('local')->directory('permintaan')->visibility('private')
                    ->maxSize(config('asset.uploads.max_kb')),
            ])
            ->action(function (array $data) {
                $request = MaintenanceRequest::create(['asset_id' => $this->assetId] + $data);
                Notification::make()->title("Permintaan {$request->number} dibuat.")->body('Menunggu verifikasi supervisor sebelum menjadi work order.')->success()->send();
            });
    }

    public function addAssetAction(): Action
    {
        return Action::make('addAsset')->label('Tambah aset di sini')->icon(Heroicon::OutlinedPlusCircle)->color('gray')
            ->visible(fn () => $this->canWrite() && $this->locationId)
            ->modalHeading(fn () => 'Aset baru · '.$this->getLocation()?->name)
            ->modalDescription('Aset yang ditemukan di lapangan tetapi belum terdaftar. Tercatat sebagai aset tambahan dan langsung bisa dicetak labelnya.')
            ->schema([
                TextInput::make('name')->label('Nama aset')->required()->maxLength(255),
                Select::make('asset_category_id')->label('Kategori')->required()->searchable()
                    ->options(fn () => AssetCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                Select::make('department_id')->label('Departemen')
                    ->options(fn () => Department::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id')),
                TextInput::make('brand')->label('Merek'),
                TextInput::make('model')->label('Model'),
                TextInput::make('serial_no')->label('Nomor seri'),
            ])
            ->action(function (array $data) {
                $asset = Asset::create($data + [
                    'location_id' => $this->locationId,
                    'verification_status' => VerificationStatus::Additional,
                    'identification_source' => IdentificationSource::Additional,
                    'verified_by_id' => auth()->id(),
                    'verified_at' => now(),
                ]);
                $this->assetId = $asset->id;
                Notification::make()->title("Aset {$asset->code} ditambahkan.")->body('Cetak labelnya lalu tempel di aset.')->success()->send();
            });
    }

    public function assetUrl(Asset $asset): string
    {
        return AssetResource::getUrl('view', ['record' => $asset]);
    }
}
