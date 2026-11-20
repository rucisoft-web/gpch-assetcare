<?php

namespace App\Filament\Resources\AssetDocuments\Pages;

use App\Filament\Resources\AssetDocuments\AssetDocumentResource;
use App\Models\AssetDocument;
use App\Services\DocumentService;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Kartu dokumen: identitas, masa berlaku, asli, nilai, perpanjangan; berkas & aset di tab bawah. */
class ViewAssetDocument extends ViewRecord
{
    use \App\Filament\Concerns\RunsWorkflow;

    protected static string $resource = AssetDocumentResource::class;

    public function getTitle(): string
    {
        return $this->record->register_no;
    }

    public function getSubheading(): ?string
    {
        return $this->record->title;
    }

    public function infolist(Schema $schema): Schema
    {
        $fin = (bool) auth()->user()?->canSeeFinancials();

        return $schema->columns(1)->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => AssetDocument::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => AssetDocument::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('valid_until')->label('Berlaku sampai')->state(fn ($record) => $record->validityText()),
                TextEntry::make('type.name')->label('Jenis')->formatStateUsing(fn ($state, $record) => $record->type?->code.' · '.$state),
                TextEntry::make('classification')->label('Klasifikasi')->formatStateUsing(fn ($state) => AssetDocument::CLASSIFICATIONS[$state] ?? $state),
                TextEntry::make('document_number')->label('Nomor dokumen')->placeholder('-'),
                TextEntry::make('issuer')->label('Penerbit')->placeholder('-'),
                TextEntry::make('issued_at')->label('Tanggal terbit')->date('d M Y')->placeholder('-'),
                TextEntry::make('notice_days')->label('Batas pemberitahuan')->suffix(' hari')->placeholder('-'),
                TextEntry::make('pic.name')->label('PIC')->placeholder('belum ditetapkan'),
                TextEntry::make('backupPic.name')->label('PIC cadangan')->placeholder('-'),
                TextEntry::make('original_location')->label('Dokumen asli')->formatStateUsing(fn ($state, $record) => (AssetDocument::ORIGINAL_LOCATIONS[$state] ?? $state).($record->original_location_note ? ' · '.$record->original_location_note : ''))->placeholder('belum dicatat'),
                TextEntry::make('vendor.name')->label('Vendor / pihak')->placeholder('-'),
                TextEntry::make('reminders')->label('Titik pengingat')->columnSpan(2)
                    ->state(fn ($record) => $record->no_expiry || ! $record->valid_until ? 'Tidak ada (tanpa batas)' : collect($record->reminderOffsets())->map(fn ($d) => $d ? 'H-'.$d : 'hari H')->join(', ').' · eskalasi GM mulai H-30'),
                TextEntry::make('type.retention')->label('Retensi')->placeholder('-'),
                TextEntry::make('location.full_path')->label('Lokasi terkait')->placeholder('-'),
                TextEntry::make('notes')->label('Catatan')->columnSpanFull()->placeholder('-'),
            ]),
            Section::make('Nilai kontrak / polis')->columns(3)->visible(fn ($record) => $fin && ($record->contract_value || $record->premium || $record->sum_insured))->schema([
                TextEntry::make('contract_value')->label('Nilai kontrak')->formatStateUsing(fn ($state) => Fmt::rp($state))->placeholder('-'),
                TextEntry::make('premium')->label('Premi')->formatStateUsing(fn ($state) => Fmt::rp($state))->placeholder('-'),
                TextEntry::make('sum_insured')->label('Nilai pertanggungan')->formatStateUsing(fn ($state) => Fmt::rp($state))->placeholder('-'),
            ]),
            Section::make('Perpanjangan / penggantian')->columns(4)->visible(fn ($record) => $record->renewal_submitted_at || $record->replaced_by_id || $record->revoked_at)->schema([
                TextEntry::make('renewal_submitted_at')->label('Diajukan')->date('d M Y')->placeholder('-'),
                TextEntry::make('renewal_ref')->label('No. permohonan')->placeholder('-'),
                TextEntry::make('renewal_cost')->label('Biaya')->formatStateUsing(fn ($state) => Fmt::rp($state))->placeholder('-')->visible($fin),
                TextEntry::make('replacedBy.register_no')->label('Diganti oleh')->placeholder('-')
                    ->url(fn ($record) => $record->replaced_by_id ? AssetDocumentResource::getUrl('view', ['record' => $record->replaced_by_id]) : null),
                TextEntry::make('revoke_reason')->label('Alasan dicabut')->placeholder('-')->visible(fn ($record) => (bool) $record->revoked_at),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $d = $this->record;
        $can = fn () => auth()->user()?->hasPermission('document.manage') && in_array($d->status, AssetDocument::LIVE, true);

        return [
            Action::make('unggah')->label('Unggah berkas / versi baru')->icon('heroicon-o-arrow-up-tray')->visible($can)
                ->schema([
                    AssetDocumentResource::fileUpload('file')->multiple()->maxFiles(20)->required()
                        ->helperText('Boleh beberapa berkas sekaligus (mis. halaman pindaian, lampiran, adendum). Setiap berkas menjadi versi berikutnya.'),
                    TextInput::make('note')->label('Keterangan versi')->placeholder('mis. pindaian ulang lebih jelas, adendum 1'),
                ])
                ->action(fn (array $data) => $this->run(function () use ($d, $data) {
                    foreach (array_values(array_filter((array) $data['file'])) as $path) {
                        app(DocumentService::class)->addFile($d, $path, $data['file_names'][$path] ?? null, auth()->user(), $data['note'] ?? null);
                    }
                }, 'Berkas tersimpan; versi lama tetap ada')),
            Action::make('perpanjang')->label('Catat pengajuan perpanjangan')->icon('heroicon-o-arrow-path')->color('info')
                ->visible(fn () => $can() && ! $d->no_expiry && $d->status !== 'renewing')
                ->schema([
                    DatePicker::make('renewal_submitted_at')->label('Tanggal pengajuan')->default(now(Fmt::tz()))->required(),
                    TextInput::make('renewal_ref')->label('Nomor permohonan'),
                    TextInput::make('renewal_cost')->label('Biaya')->numeric()->prefix('Rp'),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(DocumentService::class)->startRenewal($d, $data), 'Status: Dalam perpanjangan')),
            Action::make('ganti')->label('Dokumen baru terbit')->icon('heroicon-o-document-plus')->color('success')->visible($can)
                ->modalDescription('Kartu baru dibuat dengan aset & PIC yang sama. Dokumen ini menjadi "Diganti" dan riwayatnya tetap tersimpan.')
                ->fillForm(fn () => ['title' => $d->title, 'issuer' => $d->issuer])
                ->schema([
                    TextInput::make('title')->label('Judul')->required(),
                    TextInput::make('document_number')->label('Nomor dokumen baru'),
                    TextInput::make('issuer')->label('Penerbit'),
                    DatePicker::make('issued_at')->label('Tanggal terbit'),
                    DatePicker::make('valid_until')->label('Berlaku sampai')->required(fn () => (bool) $d->type?->has_expiry),
                    AssetDocumentResource::fileUpload('files')->multiple()->maxFiles(10),
                ])
                ->action(function (array $data) use ($d) {
                    $new = null;
                    $this->run(function () use ($d, $data, &$new) {
                        $new = app(DocumentService::class)->replace($d, CreateAssetDocument::withFiles($data), auth()->user());
                    }, 'Dokumen baru terdaftar');
                    if ($new) {
                        $this->redirect(AssetDocumentResource::getUrl('view', ['record' => $new]));
                    }
                }),
            ActionGroup::make([
                EditAction::make(),
                Action::make('cabut')->label('Cabut / batalkan dokumen')->icon('heroicon-o-no-symbol')->color('danger')->visible($can)
                    ->schema([Textarea::make('reason')->label('Alasan')->required()])
                    ->action(fn (array $data) => $this->run(fn () => app(DocumentService::class)->revoke($d, $data['reason']), 'Dokumen berstatus Dicabut')),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }
}
