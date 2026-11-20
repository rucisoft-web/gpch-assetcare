<?php

namespace App\Filament\Resources\LabelPrintBatches\Pages;

use App\Filament\Pages\CetakLabel;
use App\Filament\Resources\LabelPrintBatches\LabelPrintBatchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListLabelPrintBatches extends ListRecords
{
    protected static string $resource = LabelPrintBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('cetak')->label('Cetak label')->icon('heroicon-o-qr-code')->url(CetakLabel::getUrl())];
    }
}
