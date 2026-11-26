<?php

namespace App\Filament\Resources\AssetTransfers\Pages;

use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\AssetTransfers\AssetTransferResource;
use App\Models\Asset;
use App\Services\TransferService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetTransfer extends ViewRecord
{
    use RunsWorkflow;

    protected static string $resource = AssetTransferResource::class;

    public function getTitle(): string
    {
        return 'Mutasi '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        $t = $this->record;
        $u = auth()->user();
        $svc = app(TransferService::class);

        return [
            Action::make('ajukan')->label('Ajukan')->visible(fn () => $t->status === 'draft' && (int) $t->requested_by === (int) $u->id)
                ->requiresConfirmation()->action(fn () => $this->run(fn () => $svc->submit($t, $u), 'Mutasi diajukan')),
            Action::make('setujui')->label('Setujui')->color('success')->visible(fn () => $t->status === 'submitted' && $u->hasPermission('transfer.approve'))
                ->requiresConfirmation()->action(fn () => $this->run(fn () => $svc->approve($t, $u), 'Mutasi disetujui')),
            Action::make('tolak')->label('Tolak')->color('danger')->visible(fn () => $t->status === 'submitted' && $u->hasPermission('transfer.approve'))
                ->schema([Textarea::make('reason')->label('Alasan')->required()])
                ->action(fn (array $data) => $this->run(fn () => $svc->reject($t, $data['reason'], $u), 'Mutasi ditolak')),
            Action::make('kirim')->label('Kirim (dalam perjalanan)')->visible(fn () => $t->status === 'approved' && $u->hasPermission('transfer.receive'))
                ->requiresConfirmation()->action(fn () => $this->run(fn () => $svc->dispatch($t, $u), 'Aset dalam perjalanan')),
            Action::make('terima')->label('Terima di tujuan')->color('success')->visible(fn () => $t->status === 'dispatched' && $u->hasPermission('transfer.receive'))
                ->modalDescription('Periksa fisik & kondisi tiap aset. Lokasi aset berubah saat diterima; label yang tulisan lokasinya berbeda ditandai perlu label ulang.')
                ->schema(fn () => $t->items()->with('asset')->get()->map(fn ($i) => Select::make('cond.'.$i->id)->label($i->asset->asset_code.' · '.$i->asset->name)
                    ->options(Asset::CONDITIONS)->default($i->condition_out)->native(false))->all())
                ->action(fn (array $data) => $this->run(fn () => $svc->receive($t, $data['cond'] ?? [], $u), 'Mutasi diterima; lokasi aset diperbarui')),
            Action::make('selesai')->label('Selesaikan')->color('gray')->visible(fn () => $t->status === 'received' && $u->hasAnyPermission(['transfer.receive', 'transfer.approve']))
                ->requiresConfirmation()->action(fn () => $this->run(fn () => $svc->close($t, $u), 'Mutasi selesai')),
            Action::make('batal')->label('Batalkan')->color('danger')->visible(fn () => in_array($t->status, ['draft', 'submitted', 'approved'], true) && ((int) $t->requested_by === (int) $u->id || $u->hasPermission('transfer.approve')))
                ->schema([Textarea::make('reason')->label('Alasan')->required()])
                ->action(fn (array $data) => $this->run(fn () => $svc->cancel($t, $data['reason'], $u), 'Mutasi dibatalkan')),
        ];
    }
}
