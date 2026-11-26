<?php

namespace App\Filament\Pages;

use App\Filament\Resources\GenerationBatches\GenerationBatchResource;
use App\Filament\Support\F;
use App\Models\GenerationBatch;
use App\Models\Location;
use App\Models\RoomAssetTemplate;
use App\Services\RoomTemplateService;
use Filament\Pages\Page;
use Illuminate\Support\Str;

/**
 * Wizard pembuatan aset massal (bagian 25):
 * pilih template → pilih kamar → preview selisih → cocokkan aset lama → buat kandidat draft → cetak label.
 */
class GenerateAssets extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengendalian Aset';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Generate aset kamar';

    protected static ?string $title = 'Generate aset untuk banyak kamar';

    protected string $view = 'filament.pages.generate-assets';

    public ?int $templateId = null;

    public ?int $floorId = null;

    /** @var array<int|string> */
    public array $roomIds = [];

    public bool $allowMismatch = false;

    public ?array $preview = null;

    /** "roomId|item_key|pos" => asset_id | '' */
    public array $decisions = [];

    public string $idempotencyKey = '';

    public ?int $batchId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('generation.run');
    }

    public function mount(): void
    {
        $this->templateId = request()->integer('template') ?: null;
    }

    public function updated($name): void
    {
        if (in_array($name, ['templateId', 'allowMismatch'], true) || str_starts_with($name, 'roomIds')) {
            $this->preview = null;
            $this->batchId = null;
        }
    }

    public function getTemplatesProperty()
    {
        return RoomAssetTemplate::where('status', 'published')->with('roomType')->orderBy('code')->get();
    }

    public function getTemplateProperty(): ?RoomAssetTemplate
    {
        return $this->templateId ? RoomAssetTemplate::with('items', 'roomType')->find($this->templateId) : null;
    }

    public function getFloorsProperty()
    {
        return Location::whereIn('location_type', ['floor', 'building', 'zone'])->orderBy('sort_key')->get();
    }

    public function getRoomsProperty()
    {
        return Location::whereIn('location_type', ['room', 'venue'])->where('is_active', true)
            ->when($this->floorId && ($f = Location::find($this->floorId)), fn ($q) => $q->where('full_path', 'like', $f->full_path.' %'))
            ->with('roomType')->orderBy('sort_key')->get();
    }

    public function selectSameType(): void
    {
        $t = $this->template;
        $this->roomIds = $this->rooms->when($t, fn ($c) => $c->where('room_type_id', $t->room_type_id))->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $this->preview = null;
    }

    public function clearRooms(): void
    {
        $this->roomIds = [];
        $this->preview = null;
    }

    public function runPreview(): void
    {
        if (! $this->template || ! $this->roomIds) {
            \Filament\Notifications\Notification::make()->title('Pilih template dan minimal satu kamar')->warning()->send();

            return;
        }
        $this->preview = app(RoomTemplateService::class)->preview($this->template, array_map('intval', $this->roomIds), $this->allowMismatch);
        $this->decisions = [];
        foreach ($this->preview as $room) {
            foreach ($room['rows'] as $r) {
                if ($r['state'] === 'candidate') {
                    $this->decisions[$room['room_id'].'|'.$r['item_key'].'|'.$r['unit_position']] = '';
                }
            }
        }
        $this->idempotencyKey = (string) Str::uuid();
        $this->batchId = null;
    }

    public function getTotalsProperty(): array
    {
        $t = ['rooms' => 0, 'standard' => 0, 'mapped' => 0, 'candidate' => 0, 'create' => 0, 'excess' => 0, 'not_available' => 0, 'blocked' => 0];
        foreach ($this->preview ?? [] as $p) {
            $t['rooms']++;
            if (! $p['allowed']) {
                $t['blocked']++;

                continue;
            }
            foreach ($p['counts'] as $k => $v) {
                $t[$k] += $v;
            }
        }

        return $t;
    }

    public function commit(): void
    {
        if (! $this->preview) {
            return;
        }
        $svc = app(RoomTemplateService::class);
        $batch = null;
        F::attempt(function () use ($svc, &$batch) {
            $batch = $svc->createBatch($this->template, array_map('intval', array_keys($this->preview)), $this->preview, auth()->user(), $this->idempotencyKey);
            $decisions = array_map(fn ($v) => $v === '' || $v === null ? null : (int) $v, $this->decisions);
            $batch = $svc->commit($batch, array_filter($decisions), auth()->user(), $this->allowMismatch);
        });
        if ($batch) {
            $this->batchId = $batch->id;
            $n = \Filament\Notifications\Notification::make()->title('Batch '.$batch->number.': '.GenerationBatch::STATUSES[$batch->status])
                ->body($batch->created_assets.' kandidat dibuat, '.$batch->matched_assets.' aset lama dicocokkan.');
            $batch->status === 'completed' ? $n->success()->send() : $n->warning()->persistent()->send();
        }
    }

    public function getBatchProperty(): ?GenerationBatch
    {
        return $this->batchId ? GenerationBatch::with('results')->find($this->batchId) : null;
    }

    protected function getViewData(): array
    {
        return ['batchUrl' => $this->batchId ? GenerationBatchResource::getUrl('view', ['record' => $this->batchId]) : null];
    }
}
