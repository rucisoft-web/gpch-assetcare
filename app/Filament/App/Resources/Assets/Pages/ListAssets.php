<?php

namespace App\Filament\App\Resources\Assets\Pages;

use App\Enums\Condition;
use App\Enums\Priority;
use App\Filament\App\Resources\Assets\AssetResource;
use App\Filament\App\Resources\Assets\IntakeActions;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make(IntakeActions::all())->label('Identifikasi awal')->icon(Heroicon::OutlinedSparkles)->button()->color('gray')
                ->visible(fn () => auth()->user()->roleIn(Filament::getTenant())?->canOperate()),
            Action::make('print_qr_location')->label('Cetak label QR')->icon(Heroicon::OutlinedQrCode)->color('gray')
                ->modalHeading('Cetak label QR per lokasi')
                ->modalDescription('Semua aset aktif di lokasi ini dan sub-lokasinya (maks. 500 label). Untuk memilih aset satu per satu: centang aset di tabel › Tindakan › Cetak label QR.')
                ->modalSubmitActionLabel('Buka halaman label')
                ->schema([
                    Select::make('location_id')->label('Lokasi')->required()->searchable()
                        ->options(fn () => Location::query()->where('is_active', true)->orderBy('complete_name')->pluck('complete_name', 'id')),
                ])
                ->action(fn (array $data) => $this->redirect(route('qr.labels', ['location' => $data['location_id']]))),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua aset'),
            'kritis' => Tab::make('Aset kritis')->icon(Heroicon::OutlinedFire)
                ->modifyQueryUsing(fn (Builder $query) => $query->needsAttention()),
            'perhatian' => Tab::make('Perlu perhatian kesehatan')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('condition', [Condition::Fair->value, Condition::Poor->value, Condition::Critical->value])),
            'mendesak' => Tab::make('Prioritas mendesak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('priority', Priority::Urgent->value)),
        ];
    }
}
