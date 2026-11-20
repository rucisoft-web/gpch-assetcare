<?php

namespace App\Filament\Resources\AssetDocuments;

use App\Filament\Concerns\HasPermissions;
use App\Filament\Support\F;
use App\Models\AssetDocument;
use App\Models\DocumentType;
use App\Models\Vendor;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Register Dokumen Aset: legalitas, izin & sertifikat K3, kontrak, polis, dokumen perolehan.
 * Satu dokumen dapat menaungi banyak aset; aset boleh tidak punya dokumen digital sama sekali.
 */
class AssetDocumentResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = AssetDocument::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset Tetap & Dokumen';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Dokumen';

    protected static ?string $pluralModelLabel = 'Register dokumen';

    protected static ?string $navigationLabel = 'Register dokumen';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $viewPermission = 'document.view';

    protected static ?string $editPermission = 'document.manage';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }
        $n = AssetDocument::visibleTo(auth()->user())->whereIn('status', ['expiring', 'expired'])->count();

        return $n ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return AssetDocument::visibleTo(auth()->user())->where('status', 'expired')->exists() ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Akan berakhir ≤ 90 hari atau kedaluwarsa';
    }

    /** Field kartu dokumen (bagian 3.1), dipakai di register & di kartu aset. */
    public static function cardFields(bool $withAssets = true, bool $withFiles = true): array
    {
        $type = fn ($get) => ($id = $get('document_type_id')) ? DocumentType::find($id) : null;

        return array_values(array_filter([
            Section::make('Identitas dokumen')->columns(3)->schema([
                Select::make('document_type_id')->label('Jenis dokumen')->options(fn () => DocumentType::options())->searchable()->native(false)->required()->live()
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if ($t = DocumentType::find($state)) {
                            $set('classification', $t->default_classification);
                            if (blank($get('title'))) {
                                $set('title', $t->name);
                            }
                            if (! $t->has_expiry) {
                                $set('no_expiry', true);
                            }
                        }
                    })
                    ->helperText(fn ($get) => $type($get)?->validity_hint)->columnSpan(2),
                TextInput::make('register_no')->label('Nomor register')->disabled()->dehydrated(false)->placeholder('Otomatis, mis. LGL-IZN/2026/0042'),
                TextInput::make('title')->label('Judul / uraian')->required()->columnSpan(2)->maxLength(255),
                Select::make('classification')->label('Klasifikasi akses')->options(AssetDocument::CLASSIFICATIONS)->required()->native(false)->default('internal')
                    ->disableOptionWhen(fn ($value) => in_array($value, ['terbatas', 'rahasia'], true) && ! auth()->user()?->hasPermission('document.view_restricted')),
                TextInput::make('document_number')->label('Nomor dokumen'),
                TextInput::make('issuer')->label('Instansi / pihak penerbit'),
                DatePicker::make('issued_at')->label('Tanggal terbit'),
                Toggle::make('no_expiry')->label('Tanpa batas waktu')->live()->inline(false),
                DatePicker::make('valid_until')->label('Berlaku sampai')->required(fn ($get) => ! $get('no_expiry'))->visible(fn ($get) => ! $get('no_expiry'))
                    ->afterOrEqual('issued_at')->helperText('Isi sesuai yang tertera di dokumen; pengingat berjalan dari tanggal ini.'),
                TextInput::make('notice_days')->label('Batas pemberitahuan (hari sebelum berakhir)')->numeric()->minValue(0)->maxValue(365)
                    ->visible(fn ($get) => (bool) $type($get)?->is_contract)->helperText('Pengingat pertama = batas ini + 30 hari.'),
            ]),
            Section::make('Penanggung jawab & dokumen asli')->columns(3)->schema([
                F::user('pic_id', 'PIC perpanjangan')->required(),
                F::user('backup_pic_id', 'PIC cadangan (atasan)'),
                Select::make('original_location')->label('Lokasi dokumen asli')->options(AssetDocument::ORIGINAL_LOCATIONS)->native(false),
                TextInput::make('original_location_note')->label('Keterangan lokasi asli')->columnSpan(2)->helperText('Mis. nama bank & nomor perjanjian, nama notaris, nomor brankas.'),
                Select::make('vendor_id')->label('Vendor / pihak kontrak')->native(false)->searchable()->options(fn () => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                F::location('location_id', 'Lokasi terkait'),
            ]),
            Section::make('Nilai kontrak / polis')->columns(3)->collapsible()
                ->visible(fn ($get) => ($t = $type($get)) && ($t->is_contract || $t->is_insurance))
                ->schema([
                    F::money('contract_value', 'Nilai kontrak'),
                    F::money('premium', 'Premi'),
                    F::money('sum_insured', 'Nilai pertanggungan'),
                ]),
            $withAssets ? Section::make('Aset yang ditaungi')->schema([
                Select::make('asset_ids')->label('Aset')->multiple()->searchable()->native(false)
                    ->getSearchResultsUsing(fn (string $search) => \App\Models\Asset::query()
                        ->where(fn ($q) => $q->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('serial_number', 'like', "%{$search}%"))
                        ->limit(40)->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name])->all())
                    ->getOptionLabelsUsing(fn (array $values) => \App\Models\Asset::whereIn('id', $values)->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name])->all())
                    ->helperText('Boleh kosong (mis. izin usaha). Satu polis dapat menaungi banyak aset.'),
            ]) : null,
            $withFiles ? Section::make('Berkas digital')->schema([
                self::fileUpload('files')->multiple()->maxFiles(10)
                    ->helperText('Boleh kosong bila belum dipindai. PDF/JPG/PNG maks 20 MB per berkas; hash SHA-256 dicatat.'),
            ]) : null,
            Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
        ]));
    }

    public static function fileUpload(string $name): FileUpload
    {
        return FileUpload::make($name)->label('Berkas')->disk('local')->directory('documents/'.now()->format('Y/m'))->visibility('private')
            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(20480)
            ->storeFileNamesIn($name.'_names')->downloadable(false)->openable(false)->previewable(false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(self::cardFields(withAssets: $schema->getOperation() === 'create', withFiles: $schema->getOperation() === 'create'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('type', 'pic')->withCount(['assets', 'files']))
            ->columns([
                TextColumn::make('register_no')->label('No. register')->fontFamily('mono')->searchable()->sortable()->copyable(),
                TextColumn::make('title')->label('Dokumen')->wrap()->searchable(['title', 'document_number', 'issuer'])
                    ->description(fn ($record) => $record->type?->code.($record->document_number ? ' · No. '.$record->document_number : '')),
                TextColumn::make('valid_until')->label('Berlaku sampai')->sortable()->formatStateUsing(fn ($record) => $record->validityText())->placeholder('Tanpa batas')
                    ->state(fn ($record) => $record->valid_until ?? ($record->no_expiry ? 'Tanpa batas' : null)),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => AssetDocument::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => AssetDocument::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('assets_count')->label('Aset')->alignEnd(),
                TextColumn::make('files_count')->label('Berkas')->alignEnd()->color(fn ($state) => $state ? null : 'warning')->formatStateUsing(fn ($state) => $state ?: 'belum'),
                TextColumn::make('pic.name')->label('PIC')->toggleable(),
                TextColumn::make('classification')->label('Klasifikasi')->formatStateUsing(fn ($state) => AssetDocument::CLASSIFICATIONS[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type.section')->label('Kelompok')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(AssetDocument::STATUSES)->multiple()->default(['valid', 'expiring', 'expired', 'renewing']),
                SelectFilter::make('section')->label('Kelompok')->options(array_combine(DocumentType::SECTIONS, DocumentType::SECTIONS))
                    ->query(fn ($q, array $data) => $data['value'] ? $q->whereHas('type', fn ($t) => $t->where('section', $data['value'])) : $q),
                SelectFilter::make('document_type_id')->label('Jenis')->relationship('type', 'name')->searchable()->preload(),
                SelectFilter::make('pic_id')->label('PIC')->relationship('pic', 'name')->searchable(),
                Filter::make('due90')->label('Berakhir ≤ 90 hari / lewat')->query(fn ($query) => $query->whereNotNull('valid_until')->where('valid_until', '<=', now()->addDays(90))),
                TernaryFilter::make('has_files')->label('Berkas digital')->trueLabel('Sudah ada berkas')->falseLabel('Belum ada berkas')
                    ->queries(true: fn ($query) => $query->has('files'), false: fn ($query) => $query->doesntHave('files')),
                TernaryFilter::make('k3')->label('Izin / riksa uji K3')->queries(true: fn ($query) => $query->whereHas('type', fn ($t) => $t->where('is_k3', true)), false: fn ($query) => $query->whereHas('type', fn ($t) => $t->where('is_k3', false))),
            ])
            ->filtersFormColumns(3)
            ->defaultSort('valid_until')
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading('Belum ada dokumen terdaftar')
            ->emptyStateDescription('Daftarkan izin, sertifikat, kontrak, polis, atau dokumen perolehan aset.');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FilesRelationManager::class,
            RelationManagers\AssetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetDocuments::route('/'),
            'create' => Pages\CreateAssetDocument::route('/create'),
            'view' => Pages\ViewAssetDocument::route('/{record}'),
            'edit' => Pages\EditAssetDocument::route('/{record}/edit'),
        ];
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::allows('document.manage') && ! in_array($record->status, ['replaced', 'revoked'], true);
    }
}
