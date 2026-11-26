<?php

namespace App\Filament\Resources\ConditionParameters\Pages;

use App\Filament\Resources\ConditionParameters\ConditionParameterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageConditionParameters extends ManageRecords
{
    protected static string $resource = ConditionParameterResource::class;

    protected ?string $subheading = 'Pemantauan kondisi untuk predictive maintenance: pembacaan yang melewati batas otomatis membuat work order. Parameter meter (jam operasi) memicu PM berbasis meter.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah parameter')];
    }
}
