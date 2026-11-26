<?php

namespace App\Filament\Resources\LabelPrintBatches\Pages;

use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Pages\KonfirmasiPemasangan;
use App\Filament\Resources\LabelPrintBatches\LabelPrintBatchResource;
use App\Services\LabelService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewLabelPrintBatch extends ViewRecord
{
    use RunsWorkflow;

    protected static string $resource = LabelPrintBatchResource::class;

    public function getTitle(): string
    {
        return 'Batch cetak '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        $b = $this->record;

        return [
            LabelPrintBatchResource::downloadAction(),
            LabelPrintBatchResource::downloadAction(true),
            Action::make('tercetak')->label('Konfirmasi sudah dicetak')->icon('heroicon-o-check')->color('success')
                ->visible(fn () => in_array($b->status, ['ready', 'downloaded'], true))
                ->requiresConfirmation()->modalDescription('Konfirmasi bahwa label pada batch ini sudah tercetak dengan baik (sudah diuji scan).')
                ->action(fn () => $this->run(fn () => app(LabelService::class)->confirmPrinted($b, auth()->user()), 'Cetak dikonfirmasi')),
            Action::make('pasang')->label('Konfirmasi pemasangan')->icon('heroicon-o-map-pin')->color('gray')
                ->visible(fn () => auth()->user()->hasPermission('label.install'))
                ->url(fn () => KonfirmasiPemasangan::getUrl()),
        ];
    }
}
