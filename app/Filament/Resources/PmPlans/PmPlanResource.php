<?php

namespace App\Filament\Resources\PmPlans;

use App\Filament\Concerns\HasPermissions;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ConditionParameter;
use App\Models\PmPlan;
use App\Models\User;
use App\Models\Vendor;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Rencana preventive maintenance → WO preventive otomatis saat jatuh tempo. */
class PmPlanResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = PmPlan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Pemeliharaan';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Rencana PM';

    protected static ?string $pluralModelLabel = 'Rencana PM';

    protected static ?string $viewPermission = 'wo.view';

    protected static ?string $editPermission = 'pm.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Rencana')->columns(3)->schema([
                TextInput::make('code')->label('Kode')->required()->maxLength(30)->unique(ignoreRecord: true)->placeholder('PM-GEN-250'),
                TextInput::make('name')->label('Nama pekerjaan')->required()->columnSpan(2)->placeholder('Servis genset 250 jam / bulanan'),
                Select::make('wo_type')->label('Jenis WO')->options(PmPlan::WO_TYPES)->default('preventive')->required()->native(false),
                Select::make('scope')->label('Cakupan')->options(PmPlan::SCOPES)->default('asset')->required()->native(false)->live(),
                Select::make('asset_id')->label('Aset')->searchable()->native(false)
                    ->visible(fn (Get $get) => $get('scope') === 'asset')->required(fn (Get $get) => $get('scope') === 'asset')
                    ->getSearchResultsUsing(fn (string $search) => Asset::query()->where(fn ($q) => $q->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                        ->limit(40)->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name])->all())
                    ->getOptionLabelUsing(fn ($value) => ($a = Asset::find($value)) ? $a->asset_code.' · '.$a->name : null),
                Select::make('category_id')->label('Kategori')->options(fn () => AssetCategory::orderBy('name')->pluck('name', 'id'))->searchable()
                    ->visible(fn (Get $get) => $get('scope') === 'category')->required(fn (Get $get) => $get('scope') === 'category')
                    ->helperText('Setiap aset aktif dalam kategori mendapat jadwal sendiri; aset baru ikut otomatis.'),
            ]),
            Section::make('Pemicu')->description('Isi interval hari, interval meter, atau keduanya (mana yang lebih dulu tercapai).')->columns(3)->schema([
                TextInput::make('interval_days')->label('Interval (hari)')->numeric()->minValue(1)->helperText('30 = bulanan, 90 = 3 bulanan, 365 = tahunan')
                    ->required(fn (Get $get) => blank($get('interval_meter'))),
                Select::make('meter_parameter_id')->label('Meter')->options(fn () => ConditionParameter::where('is_meter', true)->orderBy('name')->pluck('name', 'id'))->placeholder('—'),
                TextInput::make('interval_meter')->label('Interval meter')->numeric()->minValue(0.001)->placeholder('mis. 250 (jam)')
                    ->required(fn (Get $get) => filled($get('meter_parameter_id'))),
                DatePicker::make('starts_on')->label('Jatuh tempo pertama')->default(now())->helperText('Untuk interval hari.'),
                TextInput::make('lead_days')->label('WO dibuat H-')->numeric()->minValue(0)->maxValue(90)->default(7),
                Select::make('priority')->label('Prioritas')->options(['P2' => 'P2', 'P3' => 'P3', 'P4' => 'P4'])->default('P4')->native(false),
            ]),
            Section::make('Pelaksanaan')->columns(3)->schema([
                Select::make('pic_id')->label('PIC / penanggung jawab')->options(fn () => User::withPermission('wo.execute')->pluck('name', 'id'))->searchable(),
                TextInput::make('estimated_hours')->label('Estimasi jam kerja')->numeric(),
                Toggle::make('needs_vendor')->label('Dikerjakan vendor')->live()->inline(false),
                Select::make('vendor_id')->label('Vendor')->options(fn () => Vendor::orderBy('name')->pluck('name', 'id'))->searchable()->visible(fn (Get $get) => $get('needs_vendor')),
                Textarea::make('instructions')->label('Instruksi kerja')->rows(3)->columnSpanFull(),
                Repeater::make('checklist')->label('Checklist PM')->simple(TextInput::make('item')->required()->placeholder('mis. Cek level oli & coolant'))
                    ->defaultItems(0)->addActionLabel('Tambah butir')->reorderable()->columnSpanFull(),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('asset', 'category', 'meterParameter', 'pic')->withCount(['schedules', 'schedules as open_count' => fn ($q) => $q->whereNotNull('open_work_order_id')])
                ->withMin('schedules', 'next_due_at'))
            ->columns([
                TextColumn::make('code')->label('Kode')->fontFamily('mono')->searchable(),
                TextColumn::make('name')->label('Rencana')->searchable()->wrap()
                    ->description(fn ($record) => $record->scope === 'asset' ? ($record->asset?->asset_code.' · '.$record->asset?->name) : 'Kategori: '.$record->category?->name),
                TextColumn::make('interval')->label('Interval')->state(fn ($record) => $record->intervalText()),
                TextColumn::make('schedules_count')->label('Aset')->alignEnd(),
                TextColumn::make('schedules_min_next_due_at')->label('Jatuh tempo terdekat')->date('d M Y')->sortable()
                    ->color(fn ($state) => $state && $state < now()->toDateString() ? 'danger' : null),
                TextColumn::make('open_count')->label('WO terbuka')->alignEnd(),
                TextColumn::make('pic.name')->label('PIC')->toggleable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('wo_type')->label('Jenis')->options(PmPlan::WO_TYPES),
                SelectFilter::make('category_id')->label('Kategori')->relationship('category', 'name'),
            ])
            ->defaultSort('code')->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\SchedulesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPmPlans::route('/'),
            'create' => Pages\CreatePmPlan::route('/create'),
            'edit' => Pages\EditPmPlan::route('/{record}/edit'),
        ];
    }
}
