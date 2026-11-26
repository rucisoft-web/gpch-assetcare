<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\DepreciationClass;
use App\Models\Location;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Peta aset: pohon lokasi (klik lokasi → aset di lokasi itu & sub-lokasinya) dan
 * sebaran per jenis aset (klik kelas / kategori → di lokasi mana saja).
 */
class PetaAset extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Peta aset';

    protected static ?string $title = 'Peta aset';

    protected string $view = 'filament.pages.peta-aset';

    #[Url]
    public string $mode = 'lokasi';

    #[Url(as: 'lokasi')]
    public ?int $locationId = null;

    #[Url(as: 'kelas')]
    public ?int $classId = null;

    #[Url(as: 'kategori')]
    public ?int $categoryId = null;

    #[Url(as: 'cari')]
    public string $search = '';

    #[Url(as: 'semua')]
    public bool $includeInactive = false;

    #[Url(as: 'ringkas')]
    public bool $hideEmpty = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('asset.view');
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['lokasi', 'jenis'], true) ? $mode : 'lokasi';
    }

    public function pickLocation(?int $id): void
    {
        $this->locationId = $id;
    }

    public function pickClass(?int $id): void
    {
        $this->classId = $id;
        $this->categoryId = null;
    }

    public function pickCategory(?int $id): void
    {
        $this->categoryId = $id;
        if ($id) {
            $this->classId = AssetCategory::whereKey($id)->value('depreciation_class_id') ?? $this->classId;
        }
    }

    /** Buka lokasi tertentu di tampilan pohon dengan filter jenis yang sedang dipilih tetap berlaku. */
    public function showInTree(int $locationId): void
    {
        $this->mode = 'lokasi';
        $this->locationId = $locationId;
    }

    public function clearType(): void
    {
        $this->classId = null;
        $this->categoryId = null;
    }

    protected function statuses(): array
    {
        return $this->includeInactive ? ['draft', 'active', 'retired'] : ['draft', 'active'];
    }

    /** Aset sesuai filter jenis & status (tanpa filter lokasi). */
    protected function baseQuery(): Builder
    {
        return Asset::query()->whereIn('lifecycle_status', $this->statuses())
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when(! $this->categoryId && $this->classId, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('financial', fn ($f) => $f->where('depreciation_class_id', $this->classId))
                ->orWhere(fn ($o) => $o->whereDoesntHave('financial', fn ($f) => $f->whereNotNull('depreciation_class_id'))
                    ->whereHas('category', fn ($c) => $c->where('depreciation_class_id', $this->classId)))));
    }

    /**
     * Pohon lokasi bersarang dengan jumlah aset langsung & total termasuk sub-lokasi.
     *
     * @return array{roots: array, byId: Collection, total: array<int,int>}
     */
    protected function tree(array $direct): array
    {
        $locs = Location::query()->where('is_active', true)->orderBy('sort_key')->get(['id', 'parent_id', 'code', 'name', 'location_type', 'full_path']);
        $children = $locs->groupBy(fn ($l) => $l->parent_id ?? 0);
        $total = [];
        $walk = function (int $id) use (&$walk, &$total, $children, $direct): int {
            $sum = $direct[$id] ?? 0;
            foreach ($children->get($id, []) as $c) {
                $sum += $walk($c->id);
            }

            return $total[$id] = $sum;
        };
        foreach ($children->get(0, []) as $root) {
            $walk($root->id);
        }

        return ['children' => $children, 'byId' => $locs->keyBy('id'), 'total' => $total];
    }

    protected function descendantIds(int $id, Collection $children): array
    {
        $ids = [$id];
        foreach ($children->get($id, []) as $c) {
            array_push($ids, ...$this->descendantIds($c->id, $children));
        }

        return $ids;
    }

    protected function getViewData(): array
    {
        $u = auth()->user();
        $fin = $u->canSeeFinancials();
        $direct = $this->baseQuery()->toBase()->selectRaw('location_id, count(*) n')->groupBy('location_id')->pluck('n', 'location_id')->map(fn ($n) => (int) $n)->all();
        $tree = $this->tree($direct);
        $selected = $this->locationId ? $tree['byId']->get($this->locationId) : null;
        $open = $selected ? collect($selected->ancestors())->pluck('id')->push($selected->id)->all() : [];

        $scope = $this->baseQuery();
        if ($this->mode === 'lokasi' && $selected) {
            $scope->whereIn('location_id', $this->descendantIds($selected->id, $tree['children']));
        }
        $listQuery = (clone $scope)->when(filled($this->search), fn ($q) => $q->where(fn ($w) => $w->where('asset_code', 'like', '%'.$this->search.'%')
            ->orWhere('name', 'like', '%'.$this->search.'%')->orWhere('serial_number', 'like', '%'.$this->search.'%')));
        $count = (clone $listQuery)->count();
        $assets = $listQuery->with('category', 'location', 'financial')->get()
            ->sortBy(fn ($a) => ($a->location?->sort_key ?? 'zzz').'|'.$a->asset_code)->take(300)->values();

        // ringkasan per kategori pada cakupan terpilih
        $byCategory = (clone $scope)->toBase()->selectRaw('category_id, count(*) n')->groupBy('category_id')->pluck('n', 'category_id');
        $categories = AssetCategory::with('depreciationClass')->whereIn('id', $byCategory->keys())->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'class' => $c->depreciationClass?->code, 'n' => (int) $byCategory[$c->id]])
            ->sortByDesc('n')->values();

        // tampilan per jenis: kelas → kategori dengan jumlah, dan sebaran lokasi untuk pilihan aktif
        $classes = collect();
        $spread = collect();
        if ($this->mode === 'jenis') {
            $catCounts = Asset::query()->whereIn('lifecycle_status', $this->statuses())->toBase()
                ->selectRaw('category_id, count(*) n')->groupBy('category_id')->pluck('n', 'category_id');
            $classes = DepreciationClass::with(['categories' => fn ($q) => $q->orderBy('name')])->orderBy('sort')->get()
                ->map(fn ($k) => ['id' => $k->id, 'code' => $k->code, 'name' => $k->name,
                    'cats' => $k->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'n' => (int) ($catCounts[$c->id] ?? 0)])->filter(fn ($c) => $c['n'] > 0)->values(),
                ])->map(fn ($k) => $k + ['n' => $k['cats']->sum('n')])->filter(fn ($k) => $k['n'] > 0)->values();
            $noClass = AssetCategory::whereNull('depreciation_class_id')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'n' => (int) ($catCounts[$c->id] ?? 0)])->filter(fn ($c) => $c['n'] > 0)->values();
            if ($noClass->isNotEmpty()) {
                $classes->push(['id' => null, 'code' => '—', 'name' => 'Tanpa kelas', 'cats' => $noClass, 'n' => $noClass->sum('n')]);
            }
            if ($this->classId || $this->categoryId) {
                $spread = collect($direct)->map(fn ($n, $locId) => ['location' => $tree['byId']->get($locId), 'n' => $n])
                    ->filter(fn ($r) => $r['n'] > 0)->sortBy(fn ($r) => $r['location']?->full_path ?? 'zzz')->values();
            }
        }

        return [
            'fin' => $fin,
            'tree' => $tree,
            'open' => $open,
            'selected' => $selected,
            'assets' => $assets,
            'count' => $count,
            'costTotal' => $fin ? (float) \App\Models\AssetFinancial::whereIn('asset_id', (clone $listQuery)->select('assets.id'))->sum('acquisition_cost') : null,
            'categories' => $categories,
            'classes' => $classes,
            'spread' => $spread,
            'typeLabel' => $this->categoryId ? AssetCategory::whereKey($this->categoryId)->value('name')
                : ($this->classId ? DepreciationClass::whereKey($this->classId)->get()->map(fn ($k) => $k->code.' '.$k->name)->first() : null),
            'grandTotal' => array_sum($direct),
            'unplaced' => $direct[''] ?? 0,
        ];
    }
}
