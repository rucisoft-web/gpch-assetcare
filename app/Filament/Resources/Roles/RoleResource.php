<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Concerns\HasPermissions;
use App\Models\Role;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RoleResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 21;

    protected static ?string $modelLabel = 'Peran';

    protected static ?string $pluralModelLabel = 'Peran & izin';

    protected static ?string $navigationLabel = 'Peran & izin';

    protected static ?string $viewPermission = 'role.manage';

    protected static ?string $editPermission = 'role.manage';

    public static function canEdit(Model $record): bool
    {
        return static::allows('role.manage') && $record->slug !== 'super-admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Nama peran')->required(),
                TextInput::make('slug')->label('Slug')->required()->alphaDash()->unique(ignoreRecord: true)->disabled(fn ($record) => (bool) $record),
                Textarea::make('description')->label('Deskripsi')->rows(2)->columnSpanFull(),
                CheckboxList::make('permissions')->label('Izin')->relationship('permissions', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.' ('.$record->slug.')')
                    ->columns(2)->columnSpanFull()->bulkToggleable()->searchable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Peran')->searchable()->description(fn ($record) => $record->slug),
                TextColumn::make('description')->label('Deskripsi')->wrap()->limit(90),
                TextColumn::make('permissions_count')->label('Izin')->counts('permissions'),
                TextColumn::make('users_count')->label('Pengguna')->counts('users'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
