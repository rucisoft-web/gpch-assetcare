<?php

namespace App\Filament\App\Resources\LegalDocuments;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Filament\App\Resources\LegalDocuments\Pages\ManageLegalDocuments;
use App\Filament\Concerns\OperationalPermissions;
use App\Filament\Forms\AssetSelect;
use App\Models\LegalDocument;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class LegalDocumentResource extends Resource
{
    use OperationalPermissions;

    protected static ?string $model = LegalDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Legal & Dokumen';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Dokumen';

    protected static ?string $pluralModelLabel = 'Dokumen & Perizinan';

    public static function getNavigationBadge(): ?string
    {
        $count = LegalDocument::query()->needsAction()->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Kedaluwarsa atau segera berakhir';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama dokumen')->required()->maxLength(255)->columnSpanFull()
                ->placeholder('Sertifikat Laik Fungsi (SLF) Gedung Utama'),
            Select::make('document_type')->label('Jenis')->options(DocumentType::class)->default(DocumentType::License)->required(),
            TextInput::make('number')->label('Nomor dokumen')->maxLength(255),
            TextInput::make('issuer')->label('Penerbit')->maxLength(255)->placeholder('DPMPTSP Kota Bandung'),
            Select::make('responsible_id')->label('Penanggung jawab')->options(fn () => static::memberOptions())->searchable(),
            DatePicker::make('issued_date')->label('Tanggal terbit'),
            DatePicker::make('expiry_date')->label('Berlaku sampai')->afterOrEqual('issued_date')
                ->helperText('Kosongkan bila berlaku tanpa batas waktu.'),
            TextInput::make('reminder_days')->label('Ingatkan sejak (hari sebelum)')->numeric()->minValue(1)->maxValue(365)->default(60)->required(),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false)
                ->helperText('Matikan untuk dokumen lama yang sudah digantikan.'),
            Select::make('vehicle_id')->label('Terkait kendaraan')->searchable()
                ->options(fn () => Vehicle::query()->orderBy('plate_number')->get()->mapWithKeys(fn (Vehicle $v) => [$v->id => "{$v->plate_number} · {$v->name}"]))
                ->hidden(fn ($livewire) => $livewire instanceof RelationManager),
            AssetSelect::make('asset_id', required: false)->label('Terkait aset'),
            FileUpload::make('file')->label('Berkas (PDF/foto)')->disk('local')->directory('dokumen')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(max((int) config('asset.uploads.max_kb'), 10240))->columnSpanFull()->downloadable(),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expiry_date')
            ->columns([
                TextColumn::make('name')->label('Dokumen')->searchable(['name', 'number', 'issuer'])->wrap()->weight('bold')
                    ->description(fn (LegalDocument $record) => collect([$record->number, $record->issuer])->filter()->join(' · ') ?: null),
                TextColumn::make('document_type')->label('Jenis')->badge()->toggleable(),
                TextColumn::make('related')->label('Terkait')->placeholder('—')->toggleable()
                    ->state(fn (LegalDocument $record) => $record->vehicle?->plate_number ?? $record->asset?->code)
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                TextColumn::make('expiry_date')->label('Berlaku sampai')->date('d/m/Y')->sortable()->placeholder('Tanpa batas')
                    ->description(fn (LegalDocument $record) => match (true) {
                        $record->daysLeft() === null => null,
                        $record->daysLeft() < 0 => 'Lewat '.abs($record->daysLeft()).' hari',
                        $record->daysLeft() === 0 => 'Berakhir hari ini',
                        default => 'Sisa '.$record->daysLeft().' hari',
                    }),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('responsible.name')->label('Penanggung jawab')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(DocumentStatus::class)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->withStatus(DocumentStatus::from($data['value'])) : $query),
                SelectFilter::make('document_type')->label('Jenis')->options(DocumentType::class),
                TernaryFilter::make('is_active')->label('Aktif')->default(true),
            ])
            ->recordActions([
                Action::make('download')->label('Berkas')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                    ->visible(fn (LegalDocument $record) => filled($record->file))
                    ->url(fn (LegalDocument $record) => Storage::disk('local')->temporaryUrl($record->file, now()->addMinutes(15)))
                    ->openUrlInNewTab(),
                ActionGroup::make([EditAction::make(), DeleteAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLegalDocuments::route('/')];
    }
}
