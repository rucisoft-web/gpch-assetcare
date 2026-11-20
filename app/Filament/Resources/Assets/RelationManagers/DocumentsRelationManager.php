<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\AssetDocuments\AssetDocumentResource;
use App\Filament\Resources\AssetDocuments\Pages\CreateAssetDocument;
use App\Models\AssetDocument;
use App\Services\DocumentService;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** Panel "Dokumen" di kartu aset: nol, satu, atau banyak dokumen digital per aset. */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumen';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->hasPermission('document.view');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->documents()->whereIn('asset_documents.status', AssetDocument::LIVE)->count();

        return $n ? (string) $n : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $can = fn () => (bool) auth()->user()?->hasPermission('document.manage');
        $asset = $this->getOwnerRecord();
        $missing = $this->missingText();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->visibleTo(auth()->user())->with('type')->withCount('files'))
            ->recordTitle(fn (AssetDocument $record) => $record->label)
            ->description($missing)
            ->columns([
                TextColumn::make('register_no')->label('No. register')->fontFamily('mono'),
                TextColumn::make('title')->label('Dokumen')->wrap()->description(fn ($record) => $record->type?->code.($record->document_number ? ' · No. '.$record->document_number : '')),
                TextColumn::make('valid_until')->label('Berlaku sampai')->state(fn ($record) => $record->validityText()),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => AssetDocument::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => AssetDocument::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('files_count')->label('Berkas')->formatStateUsing(fn ($state) => $state ?: 'belum'),
            ])
            ->emptyStateHeading('Belum ada dokumen digital')
            ->emptyStateDescription('Aset boleh tidak punya dokumen. Bila kelasnya mewajibkan dokumen, kekurangannya tampil di laporan "Aset belum lengkap dokumennya".')
            ->headerActions([
                Action::make('daftar')->label('Daftarkan dokumen')->icon('heroicon-o-document-plus')->visible($can)
                    ->schema(fn (Schema $schema) => $schema->components(AssetDocumentResource::cardFields(withAssets: false, withFiles: true)))
                    ->fillForm(['classification' => 'internal', 'pic_id' => auth()->id()])
                    ->action(function (array $data) use ($asset) {
                        \App\Filament\Support\F::attempt(fn () => app(DocumentService::class)->create(CreateAssetDocument::withFiles($data) + ['asset_ids' => [$asset->id]], auth()->user()), 'Dokumen terdaftar & ditautkan ke aset');
                    }),
                Action::make('unggahCepat')->label('Unggah dokumen legalitas')->icon('heroicon-o-arrow-up-tray')->color('success')->visible($can)
                    ->modalDescription('Unggah satu atau beberapa berkas sekaligus (sertifikat, izin, BPKB, faktur, polis). Detail kartu dokumen dapat dilengkapi kemudian.')
                    ->schema(fn (Schema $schema) => $schema->components(self::quickUploadFields()))
                    ->fillForm(['per_file' => false, 'no_expiry' => false])
                    ->action(function (array $data) use ($asset) {
                        \App\Filament\Support\F::attempt(fn () => self::runQuickUpload($data, [$asset->id]), 'Dokumen legalitas tersimpan & ditautkan ke aset');
                    }),
                AttachAction::make()->label('Tautkan dokumen yang ada')->visible($can)->preloadRecordSelect(false)
                    ->recordSelectOptionsQuery(fn ($query) => $query->visibleTo(auth()->user())->whereIn('asset_documents.status', AssetDocument::LIVE))
                    ->recordSelectSearchColumns(['register_no', 'title', 'document_number']),
            ])
            ->recordActions([
                Action::make('buka')->label('Buka')->icon('heroicon-o-eye')->url(fn (AssetDocument $record) => AssetDocumentResource::getUrl('view', ['record' => $record])),
                DetachAction::make()->label('Lepas')->visible($can),
            ])
            ->defaultSort('valid_until');
    }

    protected function missingText(): ?HtmlString
    {
        $asset = $this->getOwnerRecord();
        $class = $asset->depreciationClass();
        if (! $class) {
            return null;
        }
        $have = $asset->documents()->whereIn('asset_documents.status', AssetDocument::LIVE)->pluck('document_type_id')->all();
        $missing = $class->requirements()->where('is_mandatory', true)->with('documentType')->get()
            ->reject(fn ($r) => in_array($r->document_type_id, $have, true))
            ->map(fn ($r) => e($r->documentType->code.' '.$r->documentType->name));

        return new HtmlString('Kelas '.e($class->code).': '.($missing->isEmpty()
            ? '<span style="color:var(--k-hijau)">dokumen wajib lengkap.</span>'
            : '<span style="color:var(--k-kuning)">belum ada '.$missing->join(', ').'.</span>'));
    }

    /** Isian unggah cepat (dipakai di kartu aset & register dokumen). */
    public static function quickUploadFields(bool $withAssets = false): array
    {
        return array_values(array_filter([
            \Filament\Forms\Components\Select::make('document_type_id')->label('Jenis dokumen')->options(fn () => \App\Models\DocumentType::options())
                ->searchable()->native(false)->required()->live()
                ->afterStateUpdated(fn ($state, $set) => $set('no_expiry', ! (\App\Models\DocumentType::find($state)?->has_expiry ?? true)))
                ->helperText(fn ($get) => ($t = \App\Models\DocumentType::find($get('document_type_id'))) ? $t->validity_hint : 'Mis. sertifikat HGB, BPKB, izin, polis, faktur pembelian.'),
            $withAssets ? \Filament\Forms\Components\Select::make('asset_ids')->label('Aset')->multiple()->searchable()->native(false)
                ->getSearchResultsUsing(fn (string $search) => \App\Models\Asset::query()
                    ->where(fn ($q) => $q->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('serial_number', 'like', "%{$search}%"))
                    ->limit(40)->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name])->all())
                ->getOptionLabelsUsing(fn (array $values) => \App\Models\Asset::whereIn('id', $values)->get()->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name])->all())
                ->helperText('Boleh kosong untuk dokumen tingkat perusahaan (izin usaha, NIB).') : null,
            AssetDocumentResource::fileUpload('files')->label('Berkas (boleh banyak)')->multiple()->maxFiles(20)->required()->columnSpanFull()
                ->helperText('PDF/JPG/PNG maks 20 MB per berkas. Seret beberapa berkas sekaligus.'),
            \Filament\Forms\Components\Toggle::make('per_file')->label('Satu dokumen per berkas')->inline(false)
                ->helperText('Aktifkan bila tiap berkas adalah dokumen berbeda (judul diambil dari nama berkas). Matikan bila semua berkas bagian dari satu dokumen.'),
            \Filament\Forms\Components\TextInput::make('document_number')->label('Nomor dokumen')->visible(fn ($get) => ! $get('per_file')),
            \Filament\Forms\Components\Toggle::make('no_expiry')->label('Tanpa batas waktu')->live()->inline(false),
            \Filament\Forms\Components\DatePicker::make('valid_until')->label('Berlaku sampai')->required(fn ($get) => ! $get('no_expiry'))->visible(fn ($get) => ! $get('no_expiry')),
        ]));
    }

    public static function runQuickUpload(array $data, array $assetIds): array
    {
        $files = \App\Filament\Resources\AssetDocuments\Pages\CreateAssetDocument::withFiles($data)['files'];
        $card = array_filter(['document_number' => $data['document_number'] ?? null, 'valid_until' => $data['valid_until'] ?? null], fn ($v) => filled($v));
        $card['no_expiry'] = (bool) ($data['no_expiry'] ?? false);

        return app(DocumentService::class)->quickUpload($assetIds, (int) $data['document_type_id'], $files, (bool) ($data['per_file'] ?? false), $card, auth()->user());
    }
}
