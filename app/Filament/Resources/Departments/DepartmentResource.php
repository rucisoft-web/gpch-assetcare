<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Concerns\HasPermissions;
use App\Models\Department;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class DepartmentResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = Department::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Departemen';

    protected static ?string $pluralModelLabel = 'Departemen';

    protected static ?string $viewPermission = 'reference.manage';

    protected static ?string $editPermission = 'reference.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Kode')->required()->maxLength(20)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $r) => $r->where('property_id', app(\App\Support\CurrentProperty::class)->id())),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('cost_center')->label('Cost center'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable(),
            TextColumn::make('cost_center')->label('Cost center'),
            TextColumn::make('users_count')->label('Pengguna')->counts('users'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->defaultSort('code')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDepartments::route('/')];
    }
}
