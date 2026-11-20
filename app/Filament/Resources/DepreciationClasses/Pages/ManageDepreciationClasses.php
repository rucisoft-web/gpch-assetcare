<?php

namespace App\Filament\Resources\DepreciationClasses\Pages;

use App\Filament\Resources\DepreciationClasses\DepreciationClassResource;
use App\Filament\Support\F;
use App\Models\FixedAssetPolicy;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ManageDepreciationClasses extends ManageRecords
{
    protected static string $resource = DepreciationClassResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $p = FixedAssetPolicy::current();

        return new HtmlString(e(sprintf(
            '%s · Batas kapitalisasi %s per unit, %s pembelian kelompok, %s renovasi · Umur fiskal bangunan %d tahun · Pajak tangguhan %s%%',
            FixedAssetPolicy::STATUSES[$p->status] ?? $p->status, Fmt::rp($p->capitalisation_threshold), Fmt::rp($p->group_purchase_threshold),
            Fmt::rp($p->renovation_threshold), $p->building_fiscal_life_years, rtrim(rtrim((string) $p->deferred_tax_rate, '0'), '.')
        )));
    }

    protected function getHeaderActions(): array
    {
        $can = fn () => (bool) auth()->user()?->hasPermission('policy.manage');

        return [
            Action::make('kebijakan')->label('Batas kapitalisasi & umur fiskal')->icon('heroicon-o-adjustments-horizontal')->visible($can)
                ->fillForm(fn () => FixedAssetPolicy::current()->only(['capitalisation_threshold', 'group_purchase_threshold', 'renovation_threshold', 'building_fiscal_life_years', 'deferred_tax_rate', 'effective_date', 'status', 'notes']))
                ->schema([
                    TextInput::make('capitalisation_threshold')->label('Batas kapitalisasi per unit')->numeric()->prefix('Rp')->required()->minValue(0)->helperText('Usulan Rp5.000.000; di bawahnya dibebankan.'),
                    TextInput::make('group_purchase_threshold')->label('Pembelian massal satu jenis (total)')->numeric()->prefix('Rp')->required()->minValue(0)->helperText('Usulan Rp25.000.000; dikapitalisasi sebagai satu kelompok.'),
                    TextInput::make('renovation_threshold')->label('Renovasi / perbaikan bangunan per proyek')->numeric()->prefix('Rp')->required()->minValue(0),
                    Select::make('building_fiscal_life_years')->label('Umur fiskal bangunan permanen')->required()->native(false)
                        ->options([20 => '20 tahun (5%)', 25 => '25 tahun', 30 => '30 tahun', 40 => '40 tahun, sama dengan buku (2,5%)', 50 => '50 tahun'])
                        ->helperText('Keputusan FC bersama konsultan pajak; harus diterapkan konsisten.'),
                    TextInput::make('deferred_tax_rate')->label('Tarif PPh Badan untuk pajak tangguhan')->numeric()->suffix('%')->required()->minValue(0)->maxValue(100),
                    DatePicker::make('effective_date')->label('Tanggal berlaku'),
                    Select::make('status')->label('Status')->options(FixedAssetPolicy::STATUSES)->native(false)->required(),
                    Textarea::make('notes')->label('Catatan / dasar keputusan')->rows(3),
                ])
                ->action(function (array $data) {
                    F::attempt(function () use ($data) {
                        $p = FixedAssetPolicy::current();
                        if ($data['status'] === 'approved' && $p->status !== 'approved') {
                            $data += ['approved_by' => auth()->id(), 'approved_at' => now()];
                        }
                        $p->update($data);
                    }, 'Kebijakan aset tetap disimpan');
                }),
            CreateAction::make()->label('Tambah kelas'),
        ];
    }
}
