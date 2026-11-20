<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Support\Brand;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Pengaturan identitas perusahaan: nama, badan hukum, alamat, kontak, logo, warna. Nama aplikasi tetap "Asset Care". */
class IdentitasPerusahaan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Identitas perusahaan';

    protected static ?string $title = 'Identitas perusahaan';

    protected string $view = 'filament.pages.identitas-perusahaan';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('settings.manage');
    }

    public function mount(): void
    {
        $values = [];
        foreach (array_keys(Brand::FIELDS) as $k) {
            $values[$k] = AppSetting::get($k);
        }
        $values['show_ornament'] = (bool) ($values['show_ornament'] ?? false);
        $values['primary_color'] ??= Brand::DEFAULT_COLOR;
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $logo = fn (string $name, string $label) => FileUpload::make($name)->label($label)->disk('local')->directory('branding')->visibility('private')
            ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'])->maxSize(2048)->imagePreviewHeight('80');

        return $schema->statePath('data')->components([
            Section::make('Perusahaan')->description('Tampil di header, label QR, laporan & email notifikasi. Nama aplikasi tetap "'.Brand::APP_NAME.'".')->columns(2)->schema([
                TextInput::make('company_name')->label('Nama perusahaan / properti')->required()->maxLength(150)->placeholder('mis. Grand Pasundan Convention Hotel'),
                TextInput::make('company_short')->label('Nama singkat')->required()->maxLength(30)->placeholder('mis. GPCH')
                    ->helperText('Dipakai di header ("Asset Care · GPCH") dan label QR.'),
                TextInput::make('legal_name')->label('Nama badan hukum')->maxLength(150)->placeholder('mis. PT ...'),
                TextInput::make('npwp')->label('NPWP')->maxLength(30),
                Textarea::make('address')->label('Alamat')->rows(2)->columnSpanFull(),
                TextInput::make('city')->label('Kota')->maxLength(60),
                TextInput::make('phone')->label('Telepon')->tel()->maxLength(40),
                TextInput::make('email')->label('Email')->email()->maxLength(120),
                TextInput::make('website')->label('Situs web')->maxLength(120),
                TextInput::make('tagline')->label('Tagline')->maxLength(120)->placeholder('Pengelolaan aset & pemeliharaan')->columnSpanFull(),
            ]),
            Section::make('Tampilan')->columns(3)->schema([
                $logo('logo_path', 'Logo')->helperText('PNG/SVG latar transparan, tinggi ±120 px.'),
                $logo('logo_dark_path', 'Logo mode gelap (opsional)'),
                $logo('favicon_path', 'Ikon / favicon (persegi)'),
                ColorPicker::make('primary_color')->label('Warna utama')->regex('/^#[0-9a-fA-F]{6}$/')->required(),
                TextInput::make('label_heading')->label('Judul di label QR')->maxLength(30)->placeholder('kosong = nama singkat')
                    ->helperText('Teks kecil di atas kode aset pada label.'),
                Toggle::make('show_ornament')->label('Tampilkan ornamen wayang')->inline(false)->helperText('Ornamen khas tema Pasundan di sidebar & login.'),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['show_ornament'] = ! empty($data['show_ornament']);
        AppSetting::put(array_intersect_key($data, Brand::FIELDS), auth()->id());
        Notification::make()->title('Identitas perusahaan disimpan')->body('Muat ulang halaman untuk melihat logo & warna baru.')->success()->send();
        $this->redirect(static::getUrl());
    }
}
