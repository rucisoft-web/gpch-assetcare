<?php

namespace App\Filament\App\Resources\Assets\Pages;

use App\Enums\VerificationStatus;
use App\Filament\App\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function canOperate(): bool
    {
        return (bool) auth()->user()->roleIn(Filament::getTenant())?->canOperate();
    }

    protected function getHeaderActions(): array
    {
        $mark = fn (string $name, string $label, VerificationStatus $status, $icon, string $color) => Action::make($name)
            ->label($label)->icon($icon)->color($color)
            ->visible(fn () => $this->canOperate())
            ->action(function (Asset $record) use ($status) {
                $record->markVerification($status);
                Notification::make()->title('Status verifikasi: '.$status->getLabel())->success()->send();
            });

        return [
            ActionGroup::make([
                $mark('verified', 'Tandai terverifikasi', VerificationStatus::Verified, Heroicon::OutlinedCheckBadge, 'success'),
                $mark('missing', 'Tidak ditemukan', VerificationStatus::Missing, Heroicon::OutlinedXCircle, 'danger'),
                $mark('additional', 'Aset tambahan', VerificationStatus::Additional, Heroicon::OutlinedPlusCircle, 'warning'),
            ])->label('Verifikasi')->icon(Heroicon::OutlinedCheckBadge)->button()->color('gray')->visible(fn () => $this->canOperate()),
            Action::make('print_qr')->label('Cetak label QR')->icon(Heroicon::OutlinedQrCode)->color('gray')
                ->url(fn (Asset $record) => route('qr.labels', ['ids' => $record->id]))->openUrlInNewTab(),
            Action::make('regenerate_qr')->label('Buat ulang QR')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn () => $this->canOperate())
                ->requiresConfirmation()
                ->modalDescription('Label QR lama tidak bisa dipakai lagi. Cetak ulang label setelah ini.')
                ->action(fn (Asset $record) => $record->regenerateQr()),
            EditAction::make(),
        ];
    }
}
