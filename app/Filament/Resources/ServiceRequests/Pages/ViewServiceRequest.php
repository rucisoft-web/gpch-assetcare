<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\F;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\Services\ServiceRequestService;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewServiceRequest extends ViewRecord
{
    use \App\Filament\Concerns\RunsWorkflow;

    protected static string $resource = ServiceRequestResource::class;

    public function getTitle(): string
    {
        return 'Laporan '.$this->record->number;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('priority')->label('Prioritas')->badge()->color(fn ($state) => ServiceRequest::PRIORITY_COLORS[$state] ?? 'gray')
                    ->formatStateUsing(fn ($state) => ServiceRequest::PRIORITIES[$state] ?? $state),
                TextEntry::make('status')->label('Status')->badge()->color(fn ($state) => ServiceRequest::STATUS_COLORS[$state] ?? 'gray')
                    ->formatStateUsing(fn ($state) => ServiceRequest::STATUSES[$state] ?? $state),
                TextEntry::make('category')->label('Kategori')->formatStateUsing(fn ($state) => ServiceRequest::CATEGORIES[$state] ?? $state),
                TextEntry::make('impact')->label('Dampak')->formatStateUsing(fn ($state) => ServiceRequest::IMPACTS[$state] ?? $state)->placeholder('—'),
                TextEntry::make('symptom')->label('Gejala')->columnSpanFull(),
                TextEntry::make('location.full_path')->label('Lokasi')->columnSpan(2)->placeholder('—'),
                TextEntry::make('asset.asset_code')->label('Aset')->placeholder('tidak diketahui')
                    ->formatStateUsing(fn ($state, $record) => $state.' · '.$record->asset->name)
                    ->url(fn ($record) => $record->asset ? AssetResource::getUrl('view', ['record' => $record->asset]) : null),
                IconEntry::make('is_safety')->label('Bahaya keselamatan')->boolean(),
            ]),
            Section::make('Waktu layanan')->columns(4)->schema([
                TextEntry::make('received_at')->label('Diterima server')->dateTime('d M Y H:i'),
                TextEntry::make('response_due_at')->label('Batas respons')->dateTime('d M Y H:i')
                    ->color(fn ($record) => $record->responseOverdue() ? 'danger' : null),
                TextEntry::make('response_at')->label('Direspons')->dateTime('d M Y H:i')->placeholder('belum')
                    ->formatStateUsing(fn ($state, $record) => Fmt::dt($state).($record->responder ? ' · '.$record->responder->name : '')),
                TextEntry::make('triaged_at')->label('Ditriase')->dateTime('d M Y H:i')->placeholder('belum'),
                TextEntry::make('reporter.name')->label('Pelapor')->formatStateUsing(fn ($state, $record) => $state.($record->reporterDepartment ? ' · '.$record->reporterDepartment->name : '')),
                TextEntry::make('reporter_contact')->label('Kontak')->placeholder('—'),
                TextEntry::make('workOrders.number')->label('Work order')->placeholder('belum ada')->badge()
                    ->url(fn ($record) => ($wo = $record->workOrders->first()) ? WorkOrderResource::getUrl('view', ['record' => $wo]) : null),
                TextEntry::make('status_reason')->label('Alasan status')->placeholder('—'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $sr = $this->record;
        $u = auth()->user();
        $svc = app(ServiceRequestService::class);
        $canTriage = fn () => $u->hasPermission('request.triage') && in_array($sr->status, ['submitted', 'triaged'], true);

        return [
            Action::make('akui')->label('Akui laporan')->icon('heroicon-o-hand-raised')->color('gray')
                ->visible(fn () => $canTriage() && ! $sr->response_at)
                ->action(fn () => $this->run(fn () => $svc->acknowledge($sr, $u), 'Laporan diakui; waktu respons tercatat')),
            Action::make('buat_wo')->label('Buat work order')->icon('heroicon-o-wrench-screwdriver')->color('primary')
                ->visible($canTriage)
                ->fillForm(fn () => ['title' => \Illuminate\Support\Str::limit($sr->symptom, 120), 'priority' => $sr->priority, 'asset_id' => $sr->asset_id, 'location_id' => $sr->location_id])
                ->schema([
                    TextInput::make('title')->label('Judul pekerjaan')->required()->maxLength(160),
                    Select::make('priority')->label('Prioritas')->options(ServiceRequest::PRIORITIES)->required()->native(false),
                    Select::make('type')->label('Jenis')->options(WorkOrder::TYPES)->default('corrective')->native(false),
                    F::asset('asset_id', 'Aset'),
                    F::location('location_id', 'Lokasi'),
                    Select::make('also')->label('Gabungkan laporan sejenis (opsional)')->multiple()->native(false)
                        ->options(fn () => ServiceRequest::whereIn('status', ['submitted', 'triaged'])->whereKeyNot($sr->id)
                            ->where(fn ($q) => $q->where('location_id', $sr->location_id)->orWhere('asset_id', $sr->asset_id))
                            ->get()->mapWithKeys(fn ($r) => [$r->id => $r->number.' · '.\Illuminate\Support\Str::limit($r->symptom, 50)])),
                    F::money('estimated_cost', 'Estimasi biaya'),
                    Toggle::make('needs_vendor')->label('Perlu vendor'),
                ])
                ->action(function (array $data) use ($svc, $sr, $u) {
                    $wo = null;
                    if (F::attempt(function () use (&$wo, $svc, $sr, $u, $data) {
                        $wo = $svc->createWorkOrder(array_merge([$sr->id], $data['also'] ?? []), $data, $u);
                    }, 'Work order dibuat')) {
                        $this->redirect(WorkOrderResource::getUrl('view', ['record' => $wo]));
                    }
                }),
            Action::make('triase')->label('Triase')->icon('heroicon-o-adjustments-horizontal')->color('warning')
                ->visible($canTriage)
                ->fillForm(fn () => $sr->only(['priority', 'category', 'asset_id', 'location_id', 'is_safety']))
                ->schema([
                    Select::make('priority')->label('Prioritas')->options(ServiceRequest::PRIORITIES)->required()->native(false)
                        ->helperText('Perubahan prioritas wajib disertai alasan; baseline lama tetap tersimpan.'),
                    Select::make('category')->label('Kategori')->options(ServiceRequest::CATEGORIES)->native(false),
                    F::asset('asset_id', 'Aset'),
                    F::location('location_id', 'Lokasi'),
                    Toggle::make('is_safety')->label('Bahaya keselamatan'),
                    Textarea::make('reason')->label('Alasan / catatan triase'),
                ])
                ->action(fn (array $data) => $this->run(fn () => $svc->triage($sr, $data, $u), 'Triase tersimpan')),
            ActionGroup::make([
                Action::make('tautkan')->label('Tautkan ke work order yang ada')->icon('heroicon-o-link')
                    ->visible($canTriage)
                    ->schema([
                        Select::make('work_order_id')->label('Work order terbuka')->required()->searchable()->native(false)
                            ->options(fn () => WorkOrder::whereIn('status', WorkOrder::OPEN)
                                ->orderByRaw('CASE WHEN asset_id = ? OR location_id = ? THEN 0 ELSE 1 END', [(int) $sr->asset_id, (int) $sr->location_id])
                                ->latest()->limit(100)->get()->mapWithKeys(fn ($w) => [$w->id => $w->number.' · '.\Illuminate\Support\Str::limit($w->title, 60)])),
                    ])
                    ->action(fn (array $data) => $this->run(fn () => $svc->link($sr, WorkOrder::findOrFail($data['work_order_id']), $u), 'Laporan ditautkan; pelapor ikut menerima pembaruan')),
                Action::make('duplikat')->label('Tandai duplikat')->icon('heroicon-o-document-duplicate')
                    ->visible($canTriage)
                    ->schema([
                        Select::make('duplicate_of')->label('Duplikat dari laporan')->required()->searchable()->native(false)
                            ->options(fn () => ServiceRequest::whereKeyNot($sr->id)->latest('received_at')->limit(100)->get()->mapWithKeys(fn ($r) => [$r->id => $r->number.' · '.\Illuminate\Support\Str::limit($r->symptom, 50)])),
                        Textarea::make('reason')->label('Alasan')->required()->default('Laporan sama dengan tiket yang sudah ada'),
                    ])
                    ->action(fn (array $data) => $this->run(fn () => $svc->reject($sr, $data['reason'], $u, (int) $data['duplicate_of']), 'Ditandai duplikat')),
                Action::make('tolak')->label('Tolak laporan')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible($canTriage)
                    ->schema([Textarea::make('reason')->label('Alasan (dikirim ke pelapor)')->required()])
                    ->action(fn (array $data) => $this->run(fn () => $svc->reject($sr, $data['reason'], $u), 'Laporan ditolak')),
            ])->label('Lainnya')->button()->color('gray')->visible($canTriage),
        ];
    }
}
