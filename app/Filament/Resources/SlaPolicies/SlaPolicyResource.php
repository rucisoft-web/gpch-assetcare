<?php

namespace App\Filament\Resources\SlaPolicies;

use App\Filament\Concerns\HasPermissions;
use App\Models\SlaPolicy;
use App\Support\Fmt;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlaPolicyResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = SlaPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Target SLA';

    protected static ?string $pluralModelLabel = 'Target SLA';

    protected static ?string $viewPermission = 'settings.manage';

    protected static ?string $editPermission = 'settings.manage';

    public static function canCreate(): bool
    {
        return false; // P1–P4 tetap; hanya nilainya yang dikonfigurasi
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('priority')->label('Level')->disabled(),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('example')->label('Contoh dampak')->columnSpanFull(),
            TextInput::make('response_minutes')->label('Target respons (menit)')->numeric()->required()->minValue(1),
            TextInput::make('recovery_minutes')->label('Target pemulihan (menit)')->numeric()->minValue(1)->helperText('Kosong = target per kasus'),
            Select::make('calendar')->label('Kalender')->options(['24h' => '24 jam', 'business' => 'Kalender kerja'])->required()->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        $days = implode(', ', array_map(fn ($d) => ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'][$d], config('assetcare.business_calendar.days')));

        return $table->columns([
            TextColumn::make('priority')->label('Level')->badge()->color(fn ($state) => \App\Models\ServiceRequest::PRIORITY_COLORS[$state] ?? 'gray'),
            TextColumn::make('name')->label('Nama'),
            TextColumn::make('example')->label('Contoh dampak')->wrap(),
            TextColumn::make('response_minutes')->label('Respons')->formatStateUsing(fn ($state) => Fmt::minutes($state)),
            TextColumn::make('recovery_minutes')->label('Pemulihan')->formatStateUsing(fn ($state) => $state ? Fmt::minutes($state) : 'per kasus')->placeholder('per kasus'),
            TextColumn::make('calendar')->label('Kalender')->formatStateUsing(fn ($state) => $state === 'business' ? 'Kalender kerja' : '24 jam'),
        ])->defaultSort('priority')->paginated(false)->recordActions([EditAction::make()])
            ->description('Usulan konfigurasi awal (spesifikasi bagian 9) — sahkan bersama GM & Engineering. Kalender kerja: '.$days.' '.config('assetcare.business_calendar.start').'–'.config('assetcare.business_calendar.end').' WIB.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSlaPolicies::route('/')];
    }
}
