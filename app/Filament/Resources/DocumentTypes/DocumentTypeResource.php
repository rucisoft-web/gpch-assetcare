<?php

namespace App\Filament\Resources\DocumentTypes;

use App\Filament\Concerns\HasPermissions;
use App\Models\AssetDocument;
use App\Models\DocumentType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Daftar jenis dokumen (Register Dokumen Aset bagian 2): retensi, klasifikasi & jadwal pengingat. */
class DocumentTypeResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = DocumentType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Aset Tetap & Dokumen';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Jenis dokumen';

    protected static ?string $pluralModelLabel = 'Jenis dokumen';

    protected static ?string $viewPermission = 'document.manage';

    protected static ?string $editPermission = 'document.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('code')->label('Kode jenis')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Dokumen')->required()->columnSpan(2),
            Select::make('archive_category')->label('Kategori arsip (SRS ARSIP)')->options(DocumentType::ARCHIVE_CATEGORIES)->required()->native(false),
            Select::make('section')->label('Kelompok')->options(array_combine(DocumentType::SECTIONS, DocumentType::SECTIONS))->required()->native(false),
            Select::make('default_classification')->label('Klasifikasi default')->options(AssetDocument::CLASSIFICATIONS)->required()->native(false),
            TextInput::make('validity_hint')->label('Pola masa berlaku')->columnSpan(2)->helperText('Petunjuk saja; register selalu memakai tanggal di dokumen.'),
            TextInput::make('default_pic_role')->label('PIC lazim'),
            Toggle::make('has_expiry')->label('Punya tanggal berakhir')->default(true)->inline(false),
            TextInput::make('first_reminder_days')->label('Pengingat pertama (hari sebelum berakhir)')->numeric()->minValue(0)->maxValue(3650)->default(90)
                ->helperText('Standar 90; HGB 1.095 (3 tahun), SLF 180, polis 60.'),
            TextInput::make('retention')->label('Retensi'),
            Toggle::make('is_k3')->label('Izin / riksa uji K3')->helperText('Kedaluwarsa → aset ditandai "Tidak boleh dioperasikan".')->inline(false),
            Toggle::make('is_insurance')->label('Polis asuransi')->inline(false),
            Toggle::make('is_contract')->label('Kontrak (punya batas pemberitahuan)')->inline(false),
            Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('requirements'))
            ->columns([
                TextColumn::make('code')->label('Kode')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('name')->label('Dokumen')->wrap()->searchable()->description(fn ($record) => $record->validity_hint),
                TextColumn::make('section')->label('Kelompok')->badge()->color('gray')->toggleable(),
                TextColumn::make('archive_category')->label('Arsip')->toggleable(),
                TextColumn::make('first_reminder_days')->label('Ingat mulai')->formatStateUsing(fn ($state, $record) => $record->has_expiry ? 'H-'.$state : '—'),
                TextColumn::make('default_classification')->label('Klasifikasi')->formatStateUsing(fn ($state) => AssetDocument::CLASSIFICATIONS[$state] ?? $state)->toggleable(),
                IconColumn::make('is_k3')->label('K3')->boolean()->trueIcon('heroicon-o-shield-exclamation')->falseIcon('')->toggleable(),
                TextColumn::make('retention')->label('Retensi')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('section')->label('Kelompok')->options(array_combine(DocumentType::SECTIONS, DocumentType::SECTIONS)),
                SelectFilter::make('archive_category')->label('Kategori arsip')->options(DocumentType::ARCHIVE_CATEGORIES),
            ])
            ->defaultSort('sort')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDocumentTypes::route('/')];
    }
}
