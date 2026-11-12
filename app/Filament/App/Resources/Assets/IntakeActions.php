<?php

namespace App\Filament\App\Resources\Assets;

use App\Enums\SurveyState;
use App\Models\Asset;
use App\Models\AssetSurvey;
use App\Models\AssetTemplate;
use App\Models\Location;
use App\Services\AssetIntake;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/** Tiga wizard identifikasi awal (setara wizard Odoo): dari templat, salin lokasi, duplikasi aset. */
class IntakeActions
{
    public static function all(bool $visible = true): array
    {
        return [
            static::fromTemplate()->visible($visible),
            static::fromRoomTypes()->visible($visible),
            static::copyLocation()->visible($visible),
            static::duplicate()->visible($visible),
        ];
    }

    protected static function locationSelect(string $name, string $label, bool $multiple = true): Select
    {
        return Select::make($name)->label($label)->required()->multiple($multiple)->searchable()
            ->options(fn () => Location::query()->where('is_active', true)->orderBy('complete_name')->pluck('complete_name', 'id'));
    }

    protected static function surveySelect(): Select
    {
        return Select::make('survey_id')->label('Survei awal (opsional)')
            ->options(fn () => AssetSurvey::query()->where('state', '!=', SurveyState::Cancelled->value)->latest('id')->pluck('name', 'id'));
    }

    protected static function done(int $count, string $what): void
    {
        Notification::make()
            ->title($count ? "{$count} aset {$what}." : 'Tidak ada aset baru (semua sudah ada).')
            ->body($count ? 'Status: Diharapkan. Verifikasi di lapangan lewat menu Verifikasi Aset.' : null)
            ->success()
            ->send();
    }

    public static function fromTemplate(): Action
    {
        return Action::make('generate_from_template')
            ->label('Bangkitkan dari templat')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading('Bangkitkan aset dari templat')
            ->modalSubmitActionLabel('Bangkitkan aset')
            ->schema([
                Select::make('template_id')->label('Templat')->required()
                    ->options(fn () => AssetTemplate::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->live()
                    // Lokasi yang jenis ruangnya memakai templat ini langsung terpilih; petugas tinggal menambah/mengurangi.
                    ->afterStateUpdated(fn ($state, Set $set) => $set('location_ids', $state
                        ? Location::query()->where('is_active', true)->where('asset_template_id', $state)->pluck('id')->map(fn ($id) => (string) $id)->all()
                        : [])),
                static::locationSelect('location_ids', 'Lokasi tujuan')
                    ->helperText('Terisi otomatis dengan lokasi yang jenis ruangnya memakai templat ini.'),
                static::surveySelect(),
                Toggle::make('skip_existing')->label('Lewati aset yang sudah ada (nama & kategori sama)')->default(true),
            ])
            ->action(function (array $data) {
                $created = app(AssetIntake::class)->fromTemplate(
                    AssetTemplate::findOrFail($data['template_id']), $data['location_ids'], $data['survey_id'] ?? null, (bool) $data['skip_existing'],
                );
                static::done($created->count(), 'dibangkitkan dari templat');
            });
    }

    /** Bangkitkan aset untuk banyak lokasi sekaligus, masing-masing sesuai jenis ruang (templat) yang ditautkan. */
    public static function fromRoomTypes(): Action
    {
        return Action::make('generate_from_room_types')
            ->label('Bangkitkan sesuai jenis ruang')
            ->icon(Heroicon::OutlinedHomeModern)
            ->modalHeading('Bangkitkan aset sesuai jenis ruang')
            ->modalDescription('Setiap lokasi memakai templat jenis ruangnya sendiri (diatur di menu Lokasi). Lokasi tanpa jenis ruang dilewati.')
            ->modalSubmitActionLabel('Bangkitkan aset')
            ->schema([
                Select::make('template_ids')->label('Jenis ruang')->required()->multiple()
                    ->options(fn () => AssetTemplate::query()->where('is_active', true)->has('locations')->withCount('locations')->orderBy('name')->get()
                        ->mapWithKeys(fn (AssetTemplate $t) => [$t->id => "{$t->name} ({$t->locations_count} lokasi)"]))
                    ->helperText('Mis. pilih Kamar Deluxe untuk membangkitkan aset di semua kamar Deluxe sekaligus.'),
                Select::make('parent_id')->label('Batasi ke lokasi (opsional)')->searchable()
                    ->options(fn () => Location::query()->where('is_active', true)->has('children')->orderBy('complete_name')->pluck('complete_name', 'id'))
                    ->helperText('Mis. Lantai 4. Kosongkan untuk seluruh gedung.'),
                static::surveySelect(),
                Toggle::make('skip_existing')->label('Lewati aset yang sudah ada (nama & kategori sama)')->default(true),
            ])
            ->action(function (array $data) {
                $locations = Location::query()->where('is_active', true)->whereIn('asset_template_id', $data['template_ids']);

                if ($data['parent_id'] ?? null) {
                    $parent = Location::findOrFail($data['parent_id']);
                    $locations->whereIn('id', [$parent->id, ...$parent->descendantIds()]);
                }

                $created = app(AssetIntake::class)->fromLinkedTemplates($locations->pluck('id')->all(), $data['survey_id'] ?? null, (bool) $data['skip_existing']);
                static::done($created->count(), 'dibangkitkan sesuai jenis ruang');
            });
    }

    public static function copyLocation(): Action
    {
        return Action::make('copy_from_location')
            ->label('Salin dari lokasi')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->modalHeading('Salin struktur aset dari lokasi lain')
            ->modalDescription('Nomor seri, tanggal beli, dan nilai perolehan tidak ikut disalin.')
            ->modalSubmitActionLabel('Salin struktur')
            ->schema([
                static::locationSelect('source_id', 'Lokasi sumber', multiple: false),
                static::locationSelect('target_ids', 'Lokasi tujuan'),
                static::surveySelect(),
                Toggle::make('skip_existing')->label('Lewati aset yang sudah ada')->default(true),
            ])
            ->action(function (array $data, Action $action) {
                try {
                    $created = app(AssetIntake::class)->copyLocation((int) $data['source_id'], $data['target_ids'], $data['survey_id'] ?? null, (bool) $data['skip_existing']);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }
                static::done($created->count(), 'disalin');
            });
    }

    public static function duplicate(): Action
    {
        return Action::make('duplicate_asset')
            ->label('Duplikasi aset')
            ->icon(Heroicon::OutlinedSquare2Stack)
            ->modalHeading('Duplikasi aset ke beberapa lokasi')
            ->modalSubmitActionLabel('Duplikasi')
            ->schema([
                Select::make('asset_id')->label('Aset sumber')->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Asset::query()
                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->with('location')->limit(30)->get()
                        ->mapWithKeys(fn (Asset $a) => [$a->id => "{$a->code} · {$a->name} ({$a->location?->complete_name})"]))
                    ->getOptionLabelUsing(fn ($value) => Asset::find($value)?->name),
                static::locationSelect('target_ids', 'Lokasi tujuan'),
                static::surveySelect(),
            ])
            ->action(function (array $data) {
                $created = app(AssetIntake::class)->duplicate(Asset::findOrFail($data['asset_id']), $data['target_ids'], $data['survey_id'] ?? null);
                static::done($created->count(), 'diduplikasi');
            });
    }
}
