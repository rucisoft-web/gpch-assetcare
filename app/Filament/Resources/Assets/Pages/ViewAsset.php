<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Pages\CetakLabel;
use App\Filament\Resources\AssetTransfers\AssetTransferResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Support\F;
use App\Models\Asset;
use App\Services\AssetService;
use App\Services\LabelService;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/** Kartu aset: kode, nama, lokasi, status & tindakan yang relevan (hasil scan QR membuka halaman ini). */
class ViewAsset extends ViewRecord
{
    use \App\Filament\Concerns\RunsWorkflow;

    protected static string $resource = AssetResource::class;

    public function getTitle(): string
    {
        return 'Kartu aset';
    }

    public function infolist(Schema $schema): Schema
    {
        $fin = (bool) auth()->user()?->canSeeFinancials();

        return $schema->columns(1)->components([
            View::make('filament.asset-alerts')->viewData(['asset' => $this->record]),
            View::make('filament.asset-card')->viewData(['asset' => $this->record, 'qr' => $this->record->activeLabel ? app(LabelService::class)->qrSvg($this->record->activeLabel->url(), 180) : null]),
            Section::make('Perolehan & perawatan')->columns(4)->collapsible()->collapsed()->schema([
                TextEntry::make('ownership')->label('Kepemilikan')->formatStateUsing(fn ($state) => Asset::OWNERSHIP[$state] ?? $state)->placeholder('belum diisi'),
                TextEntry::make('purchase_date')->label('Tanggal beli')->date('d M Y')->placeholder('-'),
                TextEntry::make('in_service_date')->label('Mulai pakai')->date('d M Y')->placeholder('-'),
                TextEntry::make('vendor.name')->label('Vendor')->placeholder('-'),
                TextEntry::make('invoice_ref')->label('Ref. invoice')->placeholder('-'),
                TextEntry::make('warranty_until')->label('Garansi')->placeholder('tidak tercatat')
                    ->formatStateUsing(fn ($state, $record) => $state ? $record->warranty_until->format('d M Y').($record->warrantyActive() ? ' (aktif)' : ' (berakhir)') : null),
                TextEntry::make('contract_ref')->label('Kontrak servis')->placeholder('-'),
                TextEntry::make('pm_interval_days')->label('Interval PM')->suffix(' hari')->placeholder('-'),
                TextEntry::make('specification')->label('Spesifikasi')->columnSpanFull()->placeholder('-'),
            ]),
            Section::make('Keuangan (terbatas)')->columns(4)->visible($fin)->collapsible()->collapsed()->schema([
                TextEntry::make('financial.acquisition_cost')->label('Biaya perolehan')->formatStateUsing(fn ($state) => Fmt::rp($state))->placeholder('belum diketahui'),
                TextEntry::make('financial.replacement_estimate')->label('Estimasi pengganti')->formatStateUsing(fn ($state) => Fmt::rp($state))->placeholder('belum diketahui'),
                TextEntry::make('financial.accounting_ref')->label('Ref. akuntansi')->placeholder('-'),
                TextEntry::make('maint_cost')->label('Biaya perawatan 12 bulan')
                    ->state(fn ($record) => Fmt::rp(\App\Models\WoCostLine::whereHas('workOrder', fn ($q) => $q->where('asset_id', $record->id))->where('created_at', '>=', now()->subYear())->sum('amount'))),
            ]),
            Section::make('Penyusutan (komersial & fiskal)')->visible($fin)->collapsible()->collapsed()->schema([
                View::make('filament.asset-depreciation')->viewData(['asset' => $this->record]),
            ]),
            Section::make('Kualitas data')->columns(4)->collapsible()->collapsed()->schema([
                TextEntry::make('legacy_code')->label('Kode lama')->placeholder('-'),
                TextEntry::make('migration_source')->label('Sumber')->placeholder('-'),
                TextEntry::make('creator.name')->label('Dibuat oleh')->placeholder('-'),
                TextEntry::make('verifier.name')->label('Diverifikasi')->placeholder('-')
                    ->formatStateUsing(fn ($state, $record) => $state ? $state.' · '.Fmt::dt($record->verified_at) : null),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $a = $this->record;
        $u = auth()->user();

        return [
            Action::make('lapor')->label('Lapor kerusakan')->icon('heroicon-o-exclamation-triangle')->color('danger')
                ->visible(fn () => $u->hasPermission('request.create'))
                ->url(fn () => ServiceRequestResource::getUrl('create', ['asset' => $a->id])),
            Action::make('verifikasi')->label('Verifikasi fisik')->icon('heroicon-o-clipboard-document-check')
                ->visible(fn () => $u->hasPermission('asset.verify') && in_array($a->lifecycle_status, ['draft', 'active'], true))
                ->fillForm(fn () => $a->only(['brand', 'model', 'serial_number', 'condition_grade', 'custodian_id', 'custodian_unit', 'ownership']))
                ->schema([
                    Toggle::make('found')->label('Unit ditemukan di posisi yang benar')->default(true),
                    TextInput::make('brand')->label('Merek'),
                    TextInput::make('model')->label('Model'),
                    TextInput::make('serial_number')->label('Nomor seri'),
                    Select::make('condition_grade')->label('Kondisi')->options(Asset::CONDITIONS)->native(false),
                    Select::make('ownership')->label('Kepemilikan')->options(Asset::OWNERSHIP)->native(false),
                    F::user('custodian_id', 'Penanggung jawab'),
                    TextInput::make('custodian_unit')->label('atau unit penanggung jawab'),
                    Textarea::make('note')->label('Catatan (wajib bila tidak ditemukan)'),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(AssetService::class)->verify($a, $data, $u), 'Hasil verifikasi tersimpan')),
            Action::make('aktifkan')->label('Aktifkan')->icon('heroicon-o-bolt')->color('success')
                ->visible(fn () => $u->hasPermission('asset.activate') && $a->lifecycle_status === 'draft')
                ->requiresConfirmation()->modalDescription(fn () => ($m = $a->missingForActivation()) ? 'Belum lengkap: '.implode(', ', $m) : 'Semua field wajib terisi.')
                ->action(fn () => $this->run(fn () => app(AssetService::class)->activate($a, $u), 'Aset aktif')),
            ActionGroup::make([
                Action::make('cetak')->label('Cetak / cetak ulang label')->icon('heroicon-o-printer')
                    ->visible(fn () => $u->hasPermission('label.print'))->url(fn () => CetakLabel::getUrl(['assets' => $a->id])),
                Action::make('rotasi')->label('Rotasi token label')->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn () => $u->hasPermission('label.rotate') && $a->activeLabel)
                    ->schema([Textarea::make('reason')->label('Alasan (mis. label diduga disalahgunakan)')->required()])
                    ->modalDescription('Label lama akan menampilkan pesan "tidak berlaku". Label baru perlu dicetak & dipasang ulang.')
                    ->action(fn (array $data) => $this->run(fn () => app(LabelService::class)->rotate($a->activeLabel, $data['reason'], $u), 'Token dirotasi; cetak label baru')),
                Action::make('mutasi')->label('Ajukan mutasi')->icon('heroicon-o-arrows-right-left')
                    ->visible(fn () => $u->hasPermission('transfer.request') && $a->lifecycle_status === 'active')
                    ->url(fn () => AssetTransferResource::getUrl('create', ['asset' => $a->id])),
                Action::make('hentikan')->label('Hentikan pemakaian (retired)')->icon('heroicon-o-archive-box')->color('gray')
                    ->visible(fn () => $u->hasPermission('asset.activate') && $a->lifecycle_status === 'active')
                    ->schema([Textarea::make('reason')->label('Alasan')->required()])
                    ->action(fn (array $data) => $this->run(fn () => app(AssetService::class)->retire($a, $data['reason'], $u), 'Aset berhenti dipakai (masih dimiliki)')),
                EditAction::make(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }
}
