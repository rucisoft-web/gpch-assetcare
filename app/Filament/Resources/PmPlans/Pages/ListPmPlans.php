<?php

namespace App\Filament\Resources\PmPlans\Pages;

use App\Filament\Resources\PmPlans\PmPlanResource;
use App\Filament\Support\F;
use App\Services\PlannedMaintenanceService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPmPlans extends ListRecords
{
    protected static string $resource = PmPlanResource::class;

    protected ?string $subheading = 'WO preventive dibuat otomatis tiap hari 06:00 untuk jadwal yang jatuh tempo (H-lead). Jadwal berikutnya dihitung dari tanggal pekerjaan selesai & diverifikasi.';

    protected function getHeaderActions(): array
    {
        $can = (bool) auth()->user()?->hasPermission('pm.manage');

        return [
            Action::make('proses')->label('Proses jatuh tempo sekarang')->icon('heroicon-o-bolt')->color('gray')->visible($can)->requiresConfirmation()
                ->action(function () {
                    $n = 0;
                    F::attempt(function () use (&$n) {
                        $n = app(PlannedMaintenanceService::class)->generateDue(null, null, auth()->user());
                    });
                    \Filament\Notifications\Notification::make()->title($n.' work order PM dibuat')->success()->send();
                }),
            Action::make('dariKategori')->label('Buat dari interval kategori')->icon('heroicon-o-squares-plus')->color('gray')->visible($can)
                ->requiresConfirmation()->modalDescription('Membuat rencana PM per kategori dari interval PM bawaan kategori (Pengaturan › Kategori aset) untuk kategori yang punya aset dan belum punya rencana. Jatuh tempo pertama: 7 hari lagi. Rencana dapat disunting sesudahnya.')
                ->action(function () {
                    $n = app(PlannedMaintenanceService::class)->plansFromCategoryDefaults(auth()->user());
                    \Filament\Notifications\Notification::make()->title($n.' rencana PM dibuat')->success()->send();
                }),
            CreateAction::make()->label('Tambah rencana PM'),
        ];
    }
}
