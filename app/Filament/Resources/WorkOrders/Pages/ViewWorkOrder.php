<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Models\ServiceRequest;
use App\Models\Vendor;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewWorkOrder extends ViewRecord
{
    use RunsWorkflow;

    protected static string $resource = WorkOrderResource::class;

    /** versi saat halaman dibuka — dipakai untuk mendeteksi perubahan bersamaan (409) */
    public int $openedVersion = 0;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->openedVersion = (int) $this->record->version;
    }

    public function getTitle(): string
    {
        return $this->record->number;
    }

    public function getSubheading(): ?string
    {
        return $this->record->title;
    }

    public function infolist(Schema $schema): Schema
    {
        [$pct, $color] = WorkOrderResource::slaState($this->record);

        return $schema->columns(1)->components([
            View::make('filament.wo-summary')->viewData(['wo' => $this->record, 'pct' => $pct, 'color' => $color]),
            Section::make('Penugasan & persetujuan')->columns(4)->collapsible()->schema([
                TextEntry::make('type')->label('Jenis')->formatStateUsing(fn ($state) => WorkOrder::TYPES[$state] ?? $state)
                    ->helperText(fn ($record) => 'Sumber: '.(WorkOrder::SOURCES[$record->source] ?? $record->source)
                        .($record->pmSchedule?->plan ? ' · '.$record->pmSchedule->plan->code.' ('.$record->pmSchedule->plan->intervalText().')' : '')
                        .($record->planned_due_at ? ' · jatuh tempo '.$record->planned_due_at->format('d M Y') : '')
                        .($record->conditionReading ? ' · '.$record->conditionReading->parameter?->name.' '.$record->conditionReading->valueText() : '')),
                TextEntry::make('pic.name')->label('PIC')->placeholder('belum ditugaskan'),
                TextEntry::make('team_ids')->label('Tim')->placeholder('—')
                    ->state(fn ($record) => \App\Models\User::whereIn('id', $record->team_ids ?? [])->pluck('name')->join(', ') ?: null),
                TextEntry::make('scheduled_at')->label('Jadwal')->dateTime('d M Y H:i')->placeholder('—'),
                TextEntry::make('permits')->label('Izin kerja')->placeholder('tidak ada')
                    ->state(fn ($record) => collect($record->permits ?? [])->map(fn ($p) => WorkOrder::PERMITS[$p] ?? $p)->join(', ') ?: null),
                TextEntry::make('vendor.name')->label('Vendor')->placeholder('—'),
                TextEntry::make('warranty_decision')->label('Keputusan garansi')->placeholder('—')
                    ->formatStateUsing(fn ($state) => ['claim' => 'Klaim garansi', 'exception' => 'Pengecualian (dikerjakan internal)', 'not_applicable' => 'Tidak berlaku'][$state] ?? $state),
                TextEntry::make('estimated_cost')->label('Estimasi biaya')->formatStateUsing(fn ($state) => Fmt::rp($state))->placeholder('belum diketahui')
                    ->visible(fn () => auth()->user()->canSeeFinancials() || auth()->user()->hasAnyPermission(['wo.approve', 'wo.assign'])),
                TextEntry::make('approver.name')->label('Disetujui')->placeholder('—')
                    ->formatStateUsing(fn ($state, $record) => $state.' · '.Fmt::dt($record->approved_at)),
                TextEntry::make('creator.name')->label('Dibuat oleh')->placeholder('—'),
            ]),
            Section::make('Checklist PM')->collapsible()->visible(fn ($record) => filled($record->pm_checklist))->schema([
                TextEntry::make('pm_checklist')->hiddenLabel()->html()
                    ->state(fn ($record) => collect($record->pm_checklist ?? [])->map(fn ($r) => ($r['done'] ?? false ? '☑ ' : '☐ ').e($r['item'] ?? ''))->join('<br>')),
            ]),
            Section::make('Hasil pekerjaan')->columns(3)->collapsible()
                ->visible(fn ($record) => filled($record->diagnosis) || $record->completed_at)->schema([
                    TextEntry::make('failure_mode')->label('Mode kerusakan')->placeholder('—'),
                    TextEntry::make('function_test_result')->label('Uji fungsi')->formatStateUsing(fn ($state) => WorkOrder::TEST_RESULTS[$state] ?? $state)->placeholder('—'),
                    TextEntry::make('completer.name')->label('Diselesaikan')->placeholder('—')->formatStateUsing(fn ($state, $record) => $state.' · '.Fmt::dt($record->completed_at)),
                    TextEntry::make('diagnosis')->label('Diagnosis')->columnSpanFull()->placeholder('—'),
                    TextEntry::make('action_taken')->label('Tindakan')->columnSpanFull()->placeholder('—'),
                    TextEntry::make('root_cause')->label('Akar masalah')->placeholder('—'),
                    TextEntry::make('follow_up')->label('Tindak lanjut')->placeholder('—'),
                    TextEntry::make('no_cost_reason')->label('Alasan tanpa biaya')->placeholder('—'),
                    TextEntry::make('evidence_exception_reason')->label('Pengecualian bukti')->placeholder('—'),
                    TextEntry::make('verifier.name')->label('Diverifikasi')->placeholder('belum')->formatStateUsing(fn ($state, $record) => $state.' · '.Fmt::dt($record->verified_at)),
                    TextEntry::make('asset_status_after')->label('Status aset sesudah')->formatStateUsing(fn ($state) => Asset::OPERATIONAL[$state] ?? $state)->placeholder('—'),
                ]),
        ]);
    }

    /** Field formulir per aksi alur kerja. */
    protected function actionSchema(string $action): array
    {
        $wo = $this->record;
        $reason = fn (string $label = 'Alasan', bool $req = true) => Textarea::make('reason')->label($label)->required($req)->rows(2);

        return match ($action) {
            'submit' => [F::money('estimated_cost', 'Estimasi biaya')->default($wo->estimated_cost), $reason('Catatan untuk pemberi persetujuan', false)],
            'approve_direct' => $wo->is_emergency && (float) $wo->estimated_cost > 0 ? [$reason('Alasan jalur darurat (persetujuan susulan diminta)')] : [],
            'approve' => [
                Placeholder::make('nilai')->label('Nilai diajukan')->content(Fmt::rp($wo->estimated_cost)),
                $reason('Catatan persetujuan', false),
            ],
            'assign' => [
                F::user('pic_id', 'PIC (teknisi)', 'wo.execute')->required()->default($wo->pic_id),
                Select::make('team_ids')->label('Anggota tim')->multiple()->native(false)->default($wo->team_ids ?? [])
                    ->options(fn () => \App\Models\User::withPermission('wo.execute')->sortBy('name')->pluck('name', 'id')->all()),
                DateTimePicker::make('scheduled_at')->label('Jadwal')->default(now())->seconds(false),
                TextInput::make('estimated_hours')->label('Estimasi jam')->numeric()->minValue(0)->default($wo->estimated_hours),
                CheckboxList::make('permits')->label('Izin kerja / persyaratan')->options(WorkOrder::PERMITS)->columns(2)->default($wo->permits ?? [])
                    ->helperText('Hot work, ketinggian & LOTO wajib minimal dua petugas.'),
                Toggle::make('needs_vendor')->label('Perlu vendor')->default($wo->needs_vendor)->live(),
                Select::make('vendor_id')->label('Vendor')->native(false)->options(fn () => Vendor::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->default($wo->vendor_id)->visible(fn ($get) => (bool) $get('needs_vendor')),
                Radio::make('warranty_decision')->label('Aset masih bergaransi sampai '.$wo->asset?->warranty_until?->format('d/m/Y'))
                    ->options(['claim' => 'Ajukan klaim garansi ke vendor', 'exception' => 'Pengecualian: dikerjakan internal (risiko garansi dicatat)'])
                    ->required()->visible((bool) $wo->asset?->warrantyActive())->default($wo->warranty_decision),
            ],
            'start' => $wo->asset ? [Toggle::make('set_under_repair')->label('Ubah status aset menjadi "Dalam perbaikan"')->default(true)] : [],
            'hold' => [$reason('Alasan ditunda (mis. menunggu suku cadang / vendor / akses kamar)')],
            'complete' => [
                TextInput::make('failure_mode')->label('Mode kerusakan')->default($wo->failure_mode)->datalist(['Tidak dingin', 'Bocor', 'Mati total', 'Korsleting', 'Bunyi abnormal', 'Aus / rusak fisik', 'Setelan / kalibrasi']),
                Textarea::make('diagnosis')->label('Diagnosis')->required()->rows(2)->default($wo->diagnosis),
                Textarea::make('action_taken')->label('Tindakan yang dilakukan')->required()->rows(2)->default($wo->action_taken),
                Radio::make('function_test_result')->label('Hasil uji fungsi')->options(WorkOrder::TEST_RESULTS)->required()->default($wo->function_test_result),
                Textarea::make('root_cause')->label('Akar masalah (bila diketahui)')->rows(2)->default($wo->root_cause),
                Textarea::make('follow_up')->label('Tindak lanjut')->rows(2)->default($wo->follow_up),
                CheckboxList::make('pm_done')->label('Checklist PM yang sudah dikerjakan')->visible(filled($wo->pm_checklist))
                    ->options(collect($wo->pm_checklist ?? [])->mapWithKeys(fn ($r, $i) => [$i => $r['item'] ?? ''])->all())
                    ->default(collect($wo->pm_checklist ?? [])->filter(fn ($r) => ! empty($r['done']))->keys()->all())->bulkToggleable()
                    ->helperText('Butir yang tidak dikerjakan: jelaskan di Tindak lanjut.'),
                CheckboxList::make('checklist')->label('Checklist izin kerja telah dipenuhi')->options(collect($wo->permits ?? [])->mapWithKeys(fn ($p) => [$p => WorkOrder::PERMITS[$p] ?? $p])->all())
                    ->visible(filled($wo->permits))->default(array_keys(array_filter($wo->checklist ?? []))),
                TextInput::make('no_cost_reason')->label('Alasan belum ada biaya')->default($wo->no_cost_reason)
                    ->visible($wo->costLines()->doesntExist())->helperText('Atau tambahkan biaya di tab Biaya sebelum menyelesaikan.'),
                TextInput::make('evidence_exception_reason')->label('Alasan pengecualian bukti (bila foto/checklist tidak tersedia)')->default($wo->evidence_exception_reason)->visible($wo->isCritical()),
                Placeholder::make('foto')->hiddenLabel()->visible($wo->isCritical())
                    ->content(new HtmlString('<div class="ac-scan-msg">Pekerjaan kritis: unggah foto sebelum/sesudah di tab <b>Foto &amp; dokumen</b> ('.$wo->photoCount().' foto saat ini).</div>')),
                DateTimePicker::make('restored_at')->label('Layanan kembali aman pada')->default(now())->seconds(false),
            ],
            'verify' => [
                Select::make('asset_status_after')->label('Status operasional aset sekarang')->options(Asset::OPERATIONAL)->native(false)
                    ->required((bool) $wo->asset_id)->visible((bool) $wo->asset_id)->default('serviceable'),
                Toggle::make('fo_confirmed')->label('Front Office mengonfirmasi kamar siap dijual kembali')->visible((bool) $wo->room_access_needed),
                $reason('Catatan verifikasi', false),
            ],
            'reject_back' => [$reason('Apa yang belum memenuhi?')],
            'close' => [Placeholder::make('info')->hiddenLabel()->content('Pastikan biaya sudah lengkap. Biaya yang belum final tetap ditandai "sementara" hingga Finance memfinalkan.')],
            'reopen' => [$reason('Alasan dibuka ulang (mis. gangguan berulang)')],
            'cancel' => [$reason('Alasan pembatalan')],
            default => [],
        };
    }

    protected function getHeaderActions(): array
    {
        $wo = $this->record;
        $u = auth()->user();
        $svc = app(WorkOrderService::class);
        $primary = ['submit', 'approve_direct', 'approve', 'assign', 'start', 'resume', 'complete', 'verify', 'close'];
        $colors = ['approve' => 'success', 'approve_direct' => 'success', 'start' => 'primary', 'complete' => 'success', 'verify' => 'success', 'close' => 'gray', 'cancel' => 'danger', 'reject_back' => 'danger', 'reopen' => 'warning', 'hold' => 'warning', 'return_draft' => 'warning'];

        $make = function (string $action) use ($wo, $u, $svc, $colors) {
            $schema = $this->actionSchema($action);
            $a = Action::make($action)->label(WorkOrderService::ACTION_LABELS[$action])->color($colors[$action] ?? 'primary')
                ->visible(fn () => $svc->can($wo, $action, $u))
                ->action(function (array $data) use ($wo, $u, $svc, $action) {
                    if (array_key_exists('checklist', $data) && is_array($data['checklist']) && array_is_list($data['checklist'])) {
                        $data['checklist'] = array_fill_keys($data['checklist'], true);
                    }
                    $this->run(fn () => $svc->run($wo, $action, $data, $u, $this->openedVersion), WorkOrderService::ACTION_LABELS[$action].': berhasil');
                });

            return $schema ? $a->schema($schema)->modalWidth($action === 'complete' || $action === 'assign' ? '3xl' : 'lg') : $a->requiresConfirmation();
        };

        $more = collect(array_keys(WorkOrderService::ACTIONS))->diff($primary)->map($make)->values()->all();
        $more[] = Action::make('blokir')->label('Usulkan blokir kamar (FO)')->icon('heroicon-o-no-symbol')
            ->visible(fn () => $wo->isOpen() && $u->hasAnyPermission(['wo.assign', 'wo.execute']) && ($wo->location?->isRoom() || $wo->asset?->location?->isRoom()))
            ->schema([Textarea::make('reason')->label('Alasan')->required()])
            ->action(fn (array $data) => $this->run(fn () => $svc->proposeRoomBlock($wo, $data['reason'], $u), 'Usulan dikirim ke Front Office'));
        $more[] = Action::make('final')->label('Finalkan biaya (Finance)')->icon('heroicon-o-banknotes')
            ->visible(fn () => $u->hasPermission('wo.cost_finalize') && $wo->cost_status !== 'final' && in_array($wo->status, ['verified', 'closed'], true))
            ->requiresConfirmation()->modalDescription('Baris biaya sementara menjadi final. Koreksi sesudahnya memakai baris reversal.')
            ->action(fn () => $this->run(fn () => $svc->finalizeCosts($wo, $u), 'Biaya difinalkan'));
        $more[] = EditAction::make()->label('Ubah draft');

        return [
            ...collect($primary)->map($make)->all(),
            ActionGroup::make($more)->label('Lainnya')->button()->color('gray'),
            Action::make('aset')->label('Kartu aset')->icon('heroicon-o-cube')->color('gray')->visible((bool) $wo->asset)
                ->url(fn () => AssetResource::getUrl('view', ['record' => $wo->asset])),
        ];
    }
}
