<?php

namespace App\Filament\Resources\GenerationBatches\Pages;

use App\Filament\Resources\GenerationBatches\GenerationBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListGenerationBatches extends ListRecords
{
    protected static string $resource = GenerationBatchResource::class;
}
