<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\Location;
use App\Services\AssetService;
use App\Services\WorkflowException;
use App\Support\Fmt;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Persetujuan hasil lapangan: aset yang labelnya sudah dipasang & kondisinya dicek petugas
 * diperiksa di layar web lalu disetujui (diaktifkan) oleh Asset Controller / atasan.
 * Penyetuju harus berbeda dari petugas yang memeriksa (pemisahan tugas).
 */
class PersetujuanLapangan extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengendalian Aset';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Persetujuan hasil lapangan';

    protected static ?string $title = 'Persetujuan hasil lapangan';

    protected string $view = 'filament.pages.persetujuan-lapangan';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission('asset.activate');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::readyQuery()->count();

        return $n ? (string) $n : null;
    }

    public static function readyQuery(): Builder
    {
        return Asset::query()->where('lifecycle_status', 'draft')->where('verification_status', 'verified')->where('installation_status', 'installed');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Asset::query()->where('lifecycle_status', 'draft')->with(['location', 'category', 'verifier']))
            ->columns([
                ImageColumn::make('foto')->label('Foto')->state(fn (Asset $r) => $r->photo_path ? $r->mainPhotoUrl() : null)->square()->imageSize(48),
                TextColumn::make('asset_code')->label('Kode')->weight('bold')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->wrap(),
                TextColumn::make('location.full_path')->label('Lokasi')->wrap(),
                TextColumn::make('installation_status')->label('Label')->badge()
                    ->formatStateUsing(fn ($state) => \App\Models\InstallationEvent::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'installed' => 'success', 'pending' => 'gray', default => 'danger' }),
                TextColumn::make('verification_status')->label('Cek lapangan')->badge()
                    ->formatStateUsing(fn ($state) => ['verified' => 'Ada', 'discrepancy' => 'Tidak ditemukan', 'unverified' => 'Belum'][$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'verified' => 'success', 'discrepancy' => 'danger', default => 'gray' }),
                TextColumn::make('condition_grade')->label('Kondisi')->placeholder('—'),
                TextColumn::make('verifier.name')->label('Petugas')->placeholder('—'),
                TextColumn::make('verified_at')->label('Dicek')->dateTime('d M H:i', Fmt::tz())->sortable()->placeholder('—'),
                TextColumn::make('notes')->label('Catatan')->limit(60)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tahap')->label('Tahap')->default('siap')->options([
                    'siap' => 'Siap disetujui (label terpasang & kondisi dicek)',
                    'tidak_ditemukan' => 'Aset tidak ditemukan',
                    'label' => 'Label rusak / hilang / perlu cetak ulang',
                    'belum_cek' => 'Label terpasang, kondisi belum dicek',
                ])->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                    'siap' => $query->where('verification_status', 'verified')->where('installation_status', 'installed'),
                    'tidak_ditemukan' => $query->where('verification_status', 'discrepancy'),
                    'label' => $query->whereIn('installation_status', ['damaged', 'missing', 'needs_relabel']),
                    'belum_cek' => $query->where('installation_status', 'installed')->where('verification_status', 'unverified'),
                    default => $query,
                }),
                SelectFilter::make('area')->label('Gedung / lantai')->searchable()
                    ->options(fn () => Location::whereIn('location_type', ['building', 'floor', 'zone', 'wing'])->orderBy('sort_key')->get()->mapWithKeys(fn ($l) => [$l->id => $l->full_path]))
                    ->query(function (Builder $query, array $data) {
                        if (! ($id = $data['value'] ?? null) || ! ($l = Location::find($id))) {
                            return $query;
                        }

                        return $query->whereHas('location', fn ($w) => $w->whereKey($l->id)->orWhere('sort_key', 'like', $l->sort_key.'/%'));
                    }),
            ])
            ->recordUrl(fn (Asset $r) => \App\Filament\Resources\Assets\AssetResource::getUrl('view', ['record' => $r]))
            ->defaultSort('verified_at', 'desc')
            ->toolbarActions([
                BulkAction::make('setujui')->label('Setujui & aktifkan')->icon('heroicon-o-check-badge')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Aset yang labelnya terpasang dan kondisinya sudah dicek akan diaktifkan. Aset yang dicek oleh Anda sendiri dilewati (penyetuju harus orang lain). Unit penanggung jawab yang kosong diisi nama departemen.')
                    ->action(fn (Collection $records) => $this->approve($records))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('kembalikan')->label('Kembalikan untuk dicek ulang')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->schema([Textarea::make('reason')->label('Alasan (terlihat oleh petugas di catatan aset)')->required()->rows(2)])
                    ->action(fn (Collection $records, array $data) => $this->sendBack($records, $data['reason']))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->recordActions([
                Action::make('setujui1')->label('Setujui')->icon('heroicon-o-check')->color('success')->requiresConfirmation()
                    ->visible(fn (Asset $r) => $r->verification_status === 'verified' && $r->installation_status === 'installed')
                    ->action(fn (Asset $r) => $this->approve(collect([$r]))),
            ])
            ->emptyStateHeading('Tidak ada aset pada tahap ini')
            ->emptyStateDescription('Aset muncul di sini setelah petugas memasang label dan memeriksa kondisinya lewat menu Lapangan.');
    }

    protected function approve(Collection $records): void
    {
        $user = auth()->user();
        $ok = 0;
        $skip = [];
        foreach ($records as $a) {
            if ($a->lifecycle_status !== 'draft') {
                continue;
            }
            if ($a->verification_status !== 'verified' || $a->installation_status !== 'installed') {
                $skip[] = $a->asset_code.' (label/cek belum lengkap)';

                continue;
            }
            if ((int) $a->verified_by === (int) $user->id) {
                $skip[] = $a->asset_code.' (dicek oleh Anda sendiri)';

                continue;
            }
            if (blank($a->custodian_id) && blank($a->custodian_unit) && $a->department) {
                $a->auditReason = 'Persetujuan lapangan: unit penanggung jawab = departemen';
                $a->update(['custodian_unit' => $a->department->name]);
            }
            try {
                app(AssetService::class)->activate($a, $user);
                $ok++;
            } catch (WorkflowException $e) {
                $skip[] = $a->asset_code.' ('.$e->getMessage().')';
            }
        }
        Notification::make()->title($ok.' aset disetujui & aktif')
            ->body($skip ? 'Dilewati: '.implode('; ', array_slice($skip, 0, 8)).(count($skip) > 8 ? ' … (+'.(count($skip) - 8).')' : '') : null)
            ->{$skip ? 'warning' : 'success'}()->persistent()->send();
    }

    protected function sendBack(Collection $records, string $reason): void
    {
        $user = auth()->user();
        foreach ($records as $a) {
            $a->auditReason = 'Dikembalikan untuk dicek ulang: '.$reason;
            $a->update([
                'verification_status' => 'unverified', 'verified_by' => null, 'verified_at' => null,
                'notes' => trim(($a->notes ? $a->notes."\n" : '').'['.now(Fmt::tz())->format('d/m/Y H:i').' '.$user->name.' · dikembalikan] '.$reason),
            ]);
        }
        Notification::make()->title($records->count().' aset dikembalikan ke petugas')->success()->send();
    }
}
