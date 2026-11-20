<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Concerns\HasPermissions;
use App\Models\Vendor;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class VendorResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = Vendor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Vendor';

    protected static ?string $pluralModelLabel = 'Vendor';

    protected static ?string $viewPermission = 'reference.manage';

    protected static ?string $editPermission = 'reference.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Kode')->required()->maxLength(30)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $r) => $r->where('property_id', app(\App\Support\CurrentProperty::class)->id())),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('specialization')->label('Spesialisasi'),
            TextInput::make('contact_person')->label('Kontak'),
            TextInput::make('phone')->label('Telepon')->tel(),
            TextInput::make('email')->label('Email')->email(),
            TextInput::make('service_hours')->label('Jam layanan'),
            TextInput::make('sla_note')->label('SLA kontrak'),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable(),
            TextColumn::make('specialization')->label('Spesialisasi')->wrap(),
            TextColumn::make('contact_person')->label('Kontak')->description(fn ($record) => $record->phone),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVendors::route('/')];
    }
}
