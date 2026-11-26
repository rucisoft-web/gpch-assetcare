<?php

namespace App\Filament\Resources\ConditionReadings\Pages;

use App\Filament\Resources\ConditionReadings\ConditionReadingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListConditionReadings extends ListRecords
{
    protected static string $resource = ConditionReadingResource::class;

    protected ?string $subheading = 'Catat jam operasi, suhu, getaran, arus, tekanan. Nilai yang melewati batas otomatis membuat work order predictive; pembacaan meter memicu PM berbasis jam operasi.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catat')->label('Catat pembacaan')->icon('heroicon-o-pencil-square')
                ->visible(fn () => (bool) auth()->user()?->hasPermission('condition.record'))
                ->schema(ConditionReadingResource::readingFields())
                ->action(fn (array $data) => ConditionReadingResource::record($data)),
        ];
    }
}
