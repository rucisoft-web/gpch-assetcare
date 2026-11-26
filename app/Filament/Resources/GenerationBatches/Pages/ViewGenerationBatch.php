<?php

namespace App\Filament\Resources\GenerationBatches\Pages;

use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Pages\CetakLabel;
use App\Filament\Resources\GenerationBatches\GenerationBatchResource;
use App\Services\RoomTemplateService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewGenerationBatch extends ViewRecord
{
    use RunsWorkflow;

    protected static string $resource = GenerationBatchResource::class;

    public function getTitle(): string
    {
        return 'Batch '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        $b = $this->record;

        return [
            Action::make('retry')->label('Proses ulang kamar yang gagal')->icon('heroicon-o-arrow-path')->color('warning')
                ->visible(fn () => in_array($b->status, ['partial', 'failed', 'committing'], true))
                ->requiresConfirmation()->modalDescription('Hanya kamar yang belum berhasil yang diproses. Kamar berstatus konflik tetap ditolak sampai dibuat preview baru.')
                ->action(fn () => $this->run(fn () => app(RoomTemplateService::class)->commit($b, $b->match_decisions ?? [], auth()->user()), 'Batch diproses ulang')),
            Action::make('cetak')->label('Cetak label batch')->icon('heroicon-o-qr-code')
                ->visible(fn () => $b->created_assets > 0)->url(fn () => CetakLabel::getUrl(['batch' => $b->id])),
        ];
    }
}
