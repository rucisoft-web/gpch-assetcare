<?php

namespace App\Filament\Resources\Assets;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Vendor;
use App\Services\AssetService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

/** M02 Register aset: satu aset individual = satu unit fisik. Kode = lokasi-jenis-urut (mis. MB-08-819-TV-01), dibuat server; kode lama disimpan di former_codes. */
class AssetResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = Asset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Aset';

    protected static ?string $pluralModelLabel = 'Register aset';

    protected static ?string $navigationLabel = 'Register aset';

    protected static ?string $viewPermission = 'asset.view';

    protected static ?string $editPermission = 'asset.edit';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $fin = fn () => (bool) auth()->user()?->canSeeFinancials();

        return $schema->columns(1)->components([
            Tabs::make('aset')->persistTabInQueryString()->tabs([
                Tab::make('Identitas')->columns(3)->schema([
                    Placeholder::make('asset_code_view')->label('Kode aset')->content(fn ($record) => $record?->asset_code ?? 'Dibuat otomatis saat disimpan: LOKASI-JENIS-URUT, mis. MB-08-819-TV-01'),
                    TextInput::make('name')->label('Nama aset')->required()->columnSpan(2),
                    Select::make('category_id')->label('Kategori')->required()->native(false)->searchable()
                        ->options(fn () => AssetCategory::where('is_active', true)->where('tracking_mode', 'individual')->orderBy('name')->pluck('name', 'id')),
                    TextInput::make('brand')->label('Merek'),
                    TextInput::make('model')->label('Model'),
                    TextInput::make('serial_number')->label('Nomor seri')->helperText('Boleh kosong. Duplikasi menghasilkan peringatan.'),
                    F::upload('photo_path', 'Foto aset', 'assets/photos')->columnSpan(2),
                ]),
                Tab::make('Penempatan')->columns(3)->schema([
                    F::location('location_id', 'Lokasi')->required()
                        ->disabled(fn ($record) => $record && $record->lifecycle_status !== 'draft')
                        ->helperText(fn ($record) => $record && $record->lifecycle_status !== 'draft' ? 'Lokasi aset aktif diubah melalui Mutasi.' : null),
                    Select::make('department_id')->label('Departemen')->required()->native(false)->options(fn () => Department::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                    F::user('custodian_id', 'Penanggung jawab'),
                    TextInput::make('custodian_unit')->label('Unit penanggung jawab')->helperText('Diisi bila penanggung jawab bukan perorangan'),
                    Select::make('parent_id')->label('Aset induk (bila komponen)')->native(false)->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Asset::where(fn ($q) => $q->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))->limit(30)->get()->mapWithKeys(fn ($a) => [$a->id => $a->label]))
                        ->getOptionLabelUsing(fn ($value) => Asset::find($value)?->label)
                        ->disabled(fn ($record) => (bool) $record)->helperText('Pemasangan/pelepasan komponen tercatat lewat aksi Komponen di kartu aset.'),
                ]),
                Tab::make('Kondisi & risiko')->columns(3)->schema([
                    Select::make('condition_grade')->label('Kondisi fisik')->options(Asset::CONDITIONS)->native(false),
                    Select::make('criticality')->label('Kritikalitas')->options(Asset::CRITICALITY)->native(false),
                    Select::make('operational_status')->label('Status operasional')->options(Asset::OPERATIONAL)->default('serviceable')->native(false)->required(),
                    Placeholder::make('lc')->label('Siklus hidup')->content(fn ($record) => Asset::LIFECYCLE[$record?->lifecycle_status ?? 'draft']),
                    Placeholder::make('vs')->label('Verifikasi')->content(fn ($record) => Asset::VERIFICATION[$record?->verification_status ?? 'unverified']),
                    Placeholder::make('note_dim')->label('')->content('Kondisi, status operasional & siklus hidup berdiri sendiri; satu tidak mengganti yang lain.')->columnSpanFull(),
                ]),
                Tab::make('Perolehan & perawatan')->columns(3)->schema([
                    Select::make('ownership')->label('Kepemilikan')->options(Asset::OWNERSHIP)->native(false),
                    DatePicker::make('purchase_date')->label('Tanggal beli'),
                    DatePicker::make('in_service_date')->label('Mulai pakai'),
                    Select::make('vendor_id')->label('Vendor')->native(false)->searchable()->options(fn () => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                    TextInput::make('invoice_ref')->label('Referensi invoice'),
                    TextInput::make('funding_source')->label('Sumber dana'),
                    DatePicker::make('warranty_until')->label('Garansi sampai'),
                    TextInput::make('contract_ref')->label('Kontrak servis'),
                    TextInput::make('pm_interval_days')->label('Interval PM (hari)')->numeric()->minValue(1)->helperText('Usulan; jadwal PM rilis B'),
                    Textarea::make('specification')->label('Spesifikasi / kapasitas')->rows(3)->columnSpanFull(),
                ]),
                Tab::make('Keuangan')->visible($fin)->schema([
                    Group::make()->relationship('financial')->columns(3)
                        ->disabled(fn () => ! auth()->user()?->hasPermission('asset.edit_financial'))
                        ->schema([
                            DatePicker::make('acquisition_date')->label('Tanggal perolehan'),
                            F::money('acquisition_cost', 'Biaya perolehan'),
                            F::money('replacement_estimate', 'Estimasi harga pengganti'),
                            TextInput::make('cost_center')->label('Cost center'),
                            TextInput::make('accounting_ref')->label('Referensi aset akuntansi'),
                            Select::make('verification_status')->label('Status nilai')->options(['unverified' => 'Belum terverifikasi', 'verified' => 'Terverifikasi Finance'])->default('unverified')->native(false),
                            Textarea::make('notes')->label('Catatan Finance')->columnSpanFull(),
                            \Filament\Schemas\Components\Section::make('Penyusutan (kebijakan aset tetap)')->columns(3)->columnSpanFull()->collapsible()
                                ->description('Kosongkan untuk mengikuti kelas dari kategori. Isi hanya bila aset ini berbeda dari kebijakan kelasnya.')
                                ->schema([
                                    Select::make('capitalisation')->label('Perlakuan')->options(\App\Models\AssetFinancial::CAPITALISATION)->default('capitalised')->native(false)->required(),
                                    TextInput::make('group_reference')->label('Ref. pembelian kelompok / proyek')->helperText('Wajib bila dikapitalisasi sebagai kelompok.'),
                                    Select::make('depreciation_class_id')->label('Kelas (pengecualian)')->native(false)->searchable()
                                        ->options(fn () => \App\Models\DepreciationClass::options())->placeholder('Ikut kategori'),
                                    DatePicker::make('depreciation_start_date')->label('Tanggal siap dipakai')->helperText('Kosong = tanggal mulai pakai aset. Penyusutan komersial mulai bulan ini.'),
                                    TextInput::make('commercial_life_months')->label('Umur komersial (bulan)')->numeric()->minValue(1)->placeholder('Ikut kelas'),
                                    F::money('residual_value', 'Nilai residu')->helperText('Kosong = persen residu kelas.'),
                                    DatePicker::make('fiscal_start_date')->label('Mulai fiskal')->helperText('Kosong = bulan perolehan (pengeluaran).'),
                                    Select::make('fiscal_group_code')->label('Kelompok fiskal (pengecualian)')->native(false)->options(fn () => \App\Models\FiscalGroup::options())->placeholder('Ikut kategori / kelas'),
                                    TextInput::make('fiscal_life_months')->label('Umur fiskal (bulan)')->numeric()->minValue(1)->placeholder('Ikut kelompok'),
                                    DatePicker::make('opening_balance_date')->label('Tanggal potong saldo awal')->helperText('Untuk migrasi dari SIAGA / workbook FC.'),
                                    F::money('opening_accum_commercial', 'Akumulasi komersial per tanggal potong'),
                                    F::money('opening_accum_fiscal', 'Akumulasi fiskal per tanggal potong'),
                                    F::money('impairment_loss', 'Rugi penurunan nilai (akumulasi)'),
                                    DatePicker::make('disposal_date')->label('Tanggal pelepasan'),
                                    F::money('disposal_proceeds', 'Hasil pelepasan'),
                                ]),
                        ]),
                ]),
                Tab::make('Kualitas data')->columns(3)->schema([
                    TextInput::make('legacy_code')->label('Kode lama (legacy)'),
                    TextInput::make('migration_source')->label('Sumber migrasi'),
                    Textarea::make('notes')->label('Catatan')->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('location', 'category'))
            ->columns([
                TextColumn::make('asset_code')->label('Kode')->fontFamily('mono')->searchable(['asset_code', 'former_codes'])->sortable()->copyable()
                    ->description(fn (Asset $r) => $r->former_codes ? 'dulu: '.str_replace(';', ', ', $r->former_codes) : null),
                TextColumn::make('name')->label('Nama')->searchable(['name', 'serial_number', 'legacy_code', 'brand', 'model'])->wrap()
                    ->description(fn ($record) => trim(($record->brand ? $record->brand.' ' : '').($record->model ?? '').($record->serial_number ? ' · SN '.$record->serial_number : ''))),
                TextColumn::make('category.name')->label('Kategori')->toggleable(),
                TextColumn::make('location.full_path')->label('Lokasi')->wrap()->toggleable(),
                TextColumn::make('condition_grade')->label('Kondisi')->badge()->placeholder('?')
                    ->color(fn ($state) => match ($state) { 'A', 'B' => 'success', 'C' => 'warning', 'D', 'E' => 'danger', default => 'gray' }),
                TextColumn::make('operational_status')->label('Operasional')->badge()->formatStateUsing(fn ($state) => Asset::OPERATIONAL[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'serviceable' => 'success', 'degraded' => 'warning', default => 'danger' }),
                TextColumn::make('lifecycle_status')->label('Siklus')->badge()->formatStateUsing(fn ($state) => Asset::LIFECYCLE[$state] ?? $state)
                    ->color(fn ($state) => $state === 'active' ? 'primary' : 'gray'),
                TextColumn::make('verification_status')->label('Verifikasi')->badge()->formatStateUsing(fn ($state) => Asset::VERIFICATION[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'verified' => 'success', 'discrepancy' => 'danger', default => 'warning' })->toggleable(),
                TextColumn::make('installation_status')->label('Label')->badge()->formatStateUsing(fn ($state) => \App\Models\InstallationEvent::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'installed' ? 'success' : ($state === 'pending' ? 'gray' : 'warning'))->toggleable(),
                TextColumn::make('criticality')->label('Kritikalitas')->formatStateUsing(fn ($state) => Asset::CRITICALITY[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('location_id')->label('Lokasi')->options(fn () => \App\Models\Location::options())->searchable()
                    ->query(fn ($query, array $data) => $data['value'] ? $query->whereHas('location', fn ($l) => $l->where('id', $data['value'])->orWhere('sort_key', 'like', (\App\Models\Location::find($data['value'])?->sort_key ?? '-').'/%')) : $query),
                SelectFilter::make('category_id')->label('Kategori')->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('department_id')->label('Departemen')->relationship('department', 'name'),
                SelectFilter::make('condition_grade')->label('Kondisi')->options(Asset::CONDITIONS),
                SelectFilter::make('criticality')->label('Kritikalitas')->options(Asset::CRITICALITY),
                SelectFilter::make('operational_status')->label('Status operasional')->options(Asset::OPERATIONAL),
                SelectFilter::make('verification_status')->label('Verifikasi')->options(Asset::VERIFICATION),
                SelectFilter::make('installation_status')->label('Pemasangan label')->options(\App\Models\InstallationEvent::STATUSES),
                SelectFilter::make('generation_batch_id')->label('Batch pembuatan')->options(fn () => \App\Models\GenerationBatch::latest()->pluck('number', 'id')),
            ])
            ->filtersFormColumns(3)
            ->defaultSort('asset_code', 'desc')
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('verifikasi')->label('Verifikasi fisik')->icon('heroicon-o-clipboard-document-check')
                        ->visible(fn () => auth()->user()?->hasPermission('asset.verify'))
                        ->schema([
                            Toggle::make('found')->label('Unit ditemukan di posisi yang benar')->default(true),
                            Select::make('condition_grade')->label('Kondisi (kosong = tidak diubah)')->options(Asset::CONDITIONS)->native(false),
                            F::user('custodian_id', 'Penanggung jawab (kosong = tidak diubah)'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $n = 0;
                            foreach ($records as $a) {
                                F::attempt(function () use ($a, $data, &$n) {
                                    app(AssetService::class)->verify($a, $data, auth()->user());
                                    $n++;
                                });
                            }
                            \Filament\Notifications\Notification::make()->title($n.' aset diverifikasi')->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('aktifkan')->label('Aktifkan')->icon('heroicon-o-bolt')->color('success')
                        ->visible(fn () => auth()->user()?->hasPermission('asset.activate'))->requiresConfirmation()
                        ->modalDescription('Hanya aset yang memenuhi field wajib & terverifikasi yang diaktifkan.')
                        ->action(function (Collection $records) {
                            $ok = 0;
                            $fail = [];
                            foreach ($records as $a) {
                                if ($a->lifecycle_status !== 'draft') {
                                    continue;
                                }
                                $m = $a->missingForActivation();
                                if ($m) {
                                    $fail[] = $a->asset_code.' ('.implode(', ', $m).')';

                                    continue;
                                }
                                app(AssetService::class)->activate($a, auth()->user());
                                $ok++;
                            }
                            \Filament\Notifications\Notification::make()->title($ok.' aset diaktifkan')->body($fail ? 'Belum lengkap: '.implode('; ', array_slice($fail, 0, 8)).(count($fail) > 8 ? ' …' : '') : null)
                                ->{$fail ? 'warning' : 'success'}()->persistent()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('cetak')->label('Cetak label')->icon('heroicon-o-printer')
                        ->visible(fn () => auth()->user()?->hasPermission('label.print'))
                        ->action(fn (Collection $records) => redirect(\App\Filament\Pages\CetakLabel::getUrl(['assets' => $records->pluck('id')->join(',')]))),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StatusEventsRelationManager::class,
            RelationManagers\WorkOrdersRelationManager::class,
            RelationManagers\PmSchedulesRelationManager::class,
            RelationManagers\ConditionRelationManager::class,
            RelationManagers\ComponentsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\LabelsRelationManager::class,
            \App\Filament\RelationManagers\AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view' => Pages\ViewAsset::route('/{record}'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::allows('asset.edit') && ! in_array($record->lifecycle_status, ['disposed'], true);
    }

    public static function duplicateSerialWarning(?Asset $record): ?HtmlString
    {
        $dups = $record?->duplicateSerials();

        return $dups && $dups->isNotEmpty() ? new HtmlString('<span style="color:var(--k-kuning)">Nomor seri sama dengan: '.e($dups->pluck('asset_code')->join(', ')).' — periksa.</span>') : null;
    }
}
