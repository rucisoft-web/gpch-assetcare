<?php

namespace App\Filament\Pages;

use App\Models\AssetLabel;
use App\Models\InstallationEvent;
use App\Models\Location;
use App\Services\InstallationService;
use App\Services\LabelService;
use App\Services\WorkflowException;
use Filament\Pages\Page;

/** Konfirmasi pemasangan label per kamar (bagian 26). Scan biasa tidak menyatakan terpasang. */
class KonfirmasiPemasangan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengendalian Aset';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Pemasangan label';

    protected static ?string $title = 'Konfirmasi pemasangan label';

    protected string $view = 'filament.pages.konfirmasi-pemasangan';

    public ?int $roomId = null;

    public array $feed = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('label.install');
    }

    public function mount(): void
    {
        $this->roomId = request()->integer('room') ?: null;
    }

    public function getRoomsProperty()
    {
        return Location::whereIn('location_type', ['room', 'venue', 'area'])->where('is_active', true)->orderBy('sort_key')->get();
    }

    public function getRoomProperty(): ?Location
    {
        return $this->roomId ? Location::find($this->roomId) : null;
    }

    /** Label aktif yang seharusnya terpasang di ruangan ini. */
    public function getExpectedProperty()
    {
        if (! $this->room) {
            return collect();
        }

        return app(LabelService::class)->select(['room_ids' => [$this->room->id], 'include_unverified' => true, 'include_groups' => true]);
    }

    public function confirmInstall(string $text, string $channel = 'scanner'): void
    {
        if (! $this->room) {
            $this->push('warning', 'Pilih kamar/ruang terlebih dahulu.');

            return;
        }
        try {
            $r = app(InstallationService::class)->confirm($this->room, mb_substr($text, 0, 500), auth()->user());
            $this->push($r['level'], $r['message']);
        } catch (WorkflowException $e) {
            $this->push('danger', $e->getMessage());
        }
    }

    public function mark(int $labelId, string $status): void
    {
        if (! in_array($status, ['damaged', 'missing', 'needs_relabel'], true)) {
            return;
        }
        $label = AssetLabel::find($labelId);
        if (! $label || ! auth()->user()->hasPermission('label.install')) {
            return;
        }
        $target = $label->asset ?? $label->quantityGroup;
        InstallationEvent::create([
            'asset_label_id' => $label->id, 'asset_id' => $label->asset_id, 'quantity_group_id' => $label->quantity_group_id,
            'location_id' => $target?->location_id, 'status' => $status, 'note' => 'Ditandai saat pemasangan', 'actor_id' => auth()->id(), 'occurred_at' => now(),
        ]);
        app(LabelService::class)->syncInstallStatus($label);
        $this->push('warning', ($target->asset_code ?? $target->group_code).' ditandai: '.InstallationEvent::STATUSES[$status]);
    }

    protected function push(string $level, string $message): void
    {
        array_unshift($this->feed, ['level' => $level, 'message' => $message, 'at' => now()->timezone(config('assetcare.display_timezone'))->format('H:i:s')]);
        $this->feed = array_slice($this->feed, 0, 15);
    }
}
