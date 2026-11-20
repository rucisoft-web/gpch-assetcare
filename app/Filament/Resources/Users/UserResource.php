<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\HasPermissions;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/** Pengguna & peran. Akses ditolak secara default; nonaktifkan pengguna yang keluar (tidak dihapus). */
class UserResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $viewPermission = 'user.manage';

    protected static ?string $editPermission = 'user.manage';

    public static function canEdit(Model $record): bool
    {
        return static::allows('user.manage') && (! $record->isSuperAdmin() || static::user()->isSuperAdmin());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Identitas')->columns(3)->schema([
                TextInput::make('name')->label('Nama')->required(),
                TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Telepon / WhatsApp'),
                TextInput::make('job_title')->label('Jabatan'),
                Select::make('department_id')->label('Departemen')->native(false)->options(fn () => Department::orderBy('name')->pluck('name', 'id')),
                TextInput::make('labor_rate')->label('Tarif tenaga per jam (alokasi)')->numeric()->prefix('Rp')
                    ->visible(fn () => auth()->user()->canSeeFinancials()),
                Toggle::make('is_active')->label('Aktif')->default(true),
                Toggle::make('must_change_password')->label('Wajib ganti kata sandi saat login')->default(true),
                TextInput::make('password')->label('Kata sandi awal')->password()->revealable()
                    ->required(fn ($record) => ! $record)->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))->rule(Password::defaults())
                    ->helperText(fn ($record) => $record ? 'Kosongkan bila tidak diubah.' : 'Minimal 10 karakter, huruf & angka.'),
            ]),
            Section::make('Peran')->schema([
                CheckboxList::make('roles')->hiddenLabel()->relationship('roles', 'name')->columns(3)
                    ->options(fn () => Role::orderBy('name')->get()->reject(fn ($r) => $r->slug === 'super-admin' && ! auth()->user()->isSuperAdmin())->pluck('name', 'id'))
                    ->descriptions(fn () => Role::pluck('description', 'id')->filter()->all())
                    ->helperText('Hak akses = gabungan izin dari semua peran. Pisahkan peran pemohon & penyetuju bila memungkinkan.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->description(fn ($record) => $record->email),
                TextColumn::make('job_title')->label('Jabatan')->toggleable(),
                TextColumn::make('department.name')->label('Departemen'),
                TextColumn::make('roles.name')->label('Peran')->badge()->color('gray'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                IconColumn::make('mfa')->label('MFA')->boolean()->state(fn ($record) => filled($record->app_authentication_secret)),
                TextColumn::make('last_login_at')->label('Login terakhir')->dateTime('d M Y H:i')->placeholder('belum pernah'),
            ])
            ->filters([
                SelectFilter::make('department_id')->label('Departemen')->relationship('department', 'name'),
                SelectFilter::make('roles')->label('Peran')->relationship('roles', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('reset')->label('Reset sandi')->icon('heroicon-o-key')->color('warning')
                    ->visible(fn (User $record) => static::canEdit($record))
                    ->requiresConfirmation()->modalDescription('Kata sandi sementara dibuat; pengguna wajib menggantinya saat login.')
                    ->action(function (User $record) {
                        $tmp = 'AC-'.Str::upper(Str::random(4)).'-'.random_int(1000, 9999);
                        $record->auditReason = 'Reset kata sandi oleh admin';
                        $record->forceFill(['password' => Hash::make($tmp), 'must_change_password' => true])->save();
                        Notification::make()->title('Kata sandi sementara: '.$tmp)->body('Sampaikan langsung kepada '.$record->name.'. Tidak ditampilkan lagi.')->success()->persistent()->send();
                    }),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
