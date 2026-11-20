<?php

namespace App\Filament\Pages;

use App\Filament\Resources\LabelPrintBatches\LabelPrintBatchResource;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetQuantityGroup;
use App\Models\GenerationBatch;
use App\Models\Location;
use App\Services\LabelService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** M03 Cetak label QR massal: filter → preview urutan & jumlah lembar → PDF ukuran aktual. */
class CetakLabel extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengendalian Aset';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Cetak label QR';

    protected static ?string $title = 'Cetak label QR';

    protected string $view = 'filament.pages.cetak-label';

    public array $roomIds = [];

    public ?int $floorId = null;

    public array $categoryIds = [];

    /** Matikan penyaringan daftar kamar oleh lantai / kategori oleh lokasi. */
    public bool $allRooms = false;

    public bool $allCategories = false;

    public ?int $generationBatchId = null;

    public string $assetIds = '';

    public bool $includeUnverified = true;

    public bool $includeGroups = true;

    public string $printState = 'all';

    public string $profile = 'tj-103';

    /** Kalibrasi posisi untuk profil lembar (A4 / Tom & Jerry). */
    public array $cal = [];

    public int $startCell = 1;

    public int $copies = 1;

    public string $reason = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('label.print');
    }

    public function mount(): void
    {
        $this->loadCal();
        $this->assetIds = (string) request('assets', '');
        if ($b = request()->integer('batch')) {
            $this->generationBatchId = $b;
            $this->includeUnverified = true;
            $this->printState = 'never_printed';
        }
        if ($this->assetIds !== '') {
            $this->includeUnverified = true;
        }
    }

    protected function filters(): array
    {
        return array_filter([
            // kamar dipilih → cetak kamar itu saja; lantai hanya menyaring daftar kamar
            'room_ids' => array_map('intval', $this->roomIds), 'floor_id' => $this->roomIds ? null : $this->floorId, 'category_ids' => array_map('intval', $this->categoryIds),
            'generation_batch_id' => $this->generationBatchId, 'include_unverified' => $this->includeUnverified, 'include_groups' => $this->includeGroups,
            'print_state' => $this->printState, 'asset_ids' => array_values(array_filter(array_map('intval', explode(',', $this->assetIds)))),
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    public function updatedProfile(): void
    {
        $this->loadCal();
    }

    protected function loadCal(): void
    {
        $p = LabelService::profile($this->profile);
        $this->cal = $p['type'] === 'a4' ? array_intersect_key($p, array_flip(LabelService::CALIBRATION_FIELDS)) : [];
    }

    public function saveCal(): void
    {
        F::attempt(fn () => LabelService::saveCalibration($this->profile, $this->cal, auth()->user()), 'Kalibrasi disimpan. Cetak lembar uji untuk memeriksa.');
        $this->loadCal();
    }

    public function resetCal(): void
    {
        F::attempt(fn () => \App\Models\AppSetting::put(['label_cal.'.$this->profile => null], auth()->id()), 'Kalibrasi dikembalikan ke bawaan.');
        $this->loadCal();
    }

    public function getLabelsProperty(): Collection
    {
        return app(LabelService::class)->select($this->filters());
    }

    public function getLayoutProperty(): array
    {
        return app(LabelService::class)->layout($this->labels->count(), $this->profile, $this->startCell, $this->copies);
    }

    /** Id lantai/gedung terpilih beserta semua turunannya; null bila belum memilih. */
    protected function floorScopeIds(): ?array
    {
        if (! $this->floorId || ! ($floor = Location::find($this->floorId))) {
            return null;
        }

        return Location::where('sort_key', 'like', $floor->sort_key.'/%')->pluck('id')->push($floor->id)->all();
    }

    /** Lokasi yang menentukan kategori: kamar terpilih (beserta turunannya), atau lantai terpilih. */
    protected function categoryScopeIds(): ?array
    {
        $rooms = array_map('intval', $this->roomIds);
        if ($rooms) {
            $keys = Location::whereIn('id', $rooms)->pluck('sort_key');
            $q = Location::query()->whereIn('id', $rooms);
            foreach ($keys as $k) {
                $q->orWhere('sort_key', 'like', $k.'/%');
            }

            return $q->pluck('id')->all();
        }

        return $this->floorScopeIds();
    }

    public function getRoomsProperty()
    {
        $scope = $this->allRooms ? null : $this->floorScopeIds();

        return Location::whereIn('location_type', ['room', 'venue', 'area'])->where('is_active', true)
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->orderBy('sort_key')->get();
    }

    public function getFloorsProperty()
    {
        return Location::whereIn('location_type', ['building', 'zone', 'floor'])->orderBy('sort_key')->get();
    }

    /** Kategori aktif; bila lantai/kamar dipilih, hanya kategori yang punya aset/kelompok jumlah di sana, dengan jumlah asetnya. */
    public function getCategoriesProperty()
    {
        $scope = $this->allCategories ? null : $this->categoryScopeIds();
        $q = AssetCategory::where('is_active', true)->orderBy('name');
        if ($scope === null) {
            return $q->get();
        }
        $assetCounts = Asset::whereIn('location_id', $scope)->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');
        $groupCats = AssetQuantityGroup::whereIn('location_id', $scope)->distinct()->pluck('category_id');
        $ids = $assetCounts->keys()->merge($groupCats)->unique()->all();

        return $q->whereIn('id', $ids)->get()->each(fn ($c) => $c->setAttribute('scope_count', (int) ($assetCounts[$c->id] ?? 0)));
    }

    public function updatedFloorId(): void
    {
        $allowed = $this->rooms->pluck('id')->map(fn ($i) => (string) $i)->all();
        $this->roomIds = array_values(array_filter($this->roomIds, fn ($id) => in_array((string) $id, $allowed, true)));
        $this->pruneCategories();
    }

    public function updatedRoomIds(): void
    {
        $this->pruneCategories();
    }

    public function updatedAllRooms(): void
    {
        $this->updatedFloorId();
    }

    public function updatedAllCategories(): void
    {
        $this->pruneCategories();
    }

    /** Buang kategori terpilih yang tidak ada di lokasi yang sedang dipilih. */
    protected function pruneCategories(): void
    {
        $allowed = $this->categories->pluck('id')->map(fn ($i) => (string) $i)->all();
        $this->categoryIds = array_values(array_filter($this->categoryIds, fn ($id) => in_array((string) $id, $allowed, true)));
    }

    public function getBatchesProperty()
    {
        return GenerationBatch::latest()->limit(30)->get();
    }

    public function resetFilters(): void
    {
        $this->reset(['roomIds', 'floorId', 'categoryIds', 'allRooms', 'allCategories', 'generationBatchId', 'assetIds', 'includeUnverified', 'printState']);
    }

    public function generate(): void
    {
        $labels = $this->labels;
        $reprint = $labels->contains(fn ($l) => $l->print_items_count > 0);
        $batch = null;
        F::attempt(function () use ($labels, $reprint, &$batch) {
            $batch = app(LabelService::class)->createBatch($labels, $this->profile, $this->startCell, auth()->user(), $this->filters(),
                $reprint ? 'reprint' : 'new', $reprint ? trim($this->reason) : null, max(1, min(5, $this->copies)));
        });
        if ($batch) {
            \Filament\Notifications\Notification::make()->title('Batch cetak '.$batch->number.' siap')
                ->body($batch->label_count.' label · '.$batch->sheet_count.' lembar. Cetak pada skala 100% (bukan "fit to page").')->success()->send();
            $this->redirect(LabelPrintBatchResource::getUrl('view', ['record' => $batch]));
        }
    }
}
