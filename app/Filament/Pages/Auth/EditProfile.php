<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil')->columns(2)->schema([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                TextInput::make('job_title')->label('Jabatan')->disabled()->dehydrated(false),
                TextInput::make('phone')->label('Telepon / WA'),
            ]),
            Section::make('Kata sandi')
                ->description(fn () => auth()->user()?->must_change_password ? 'Wajib diganti sebelum memakai Asset Care. Minimal 10 karakter, huruf & angka.' : 'Kosongkan bila tidak ingin mengganti.')
                ->schema([
                    $this->getPasswordFormComponent()->rule(Password::defaults())
                        ->required(fn () => (bool) auth()->user()?->must_change_password),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (filled($data['password'] ?? null)) {
            $data['must_change_password'] = false;
            $data['password_changed_at'] = now();
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
