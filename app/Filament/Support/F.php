<?php

namespace App\Filament\Support;

use App\Models\Location;
use App\Models\User;
use App\Services\WorkflowException;
use App\Support\Fmt;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Komponen form/tabel yang dipakai berulang (gaya sama dengan Arah GPCH). */
class F
{
    public static function user(string $name, string $label = 'Penanggung jawab', ?string $permission = null): Select
    {
        return Select::make($name)->label($label)->native(false)->searchable()
            ->options(fn () => ($permission ? User::withPermission($permission) : User::where('is_active', true)->orderBy('name')->get())
                ->sortBy('name')->mapWithKeys(fn ($u) => [$u->id => $u->label])->all());
    }

    public static function location(string $name = 'location_id', string $label = 'Lokasi', ?array $types = null): Select
    {
        return Select::make($name)->label($label)->native(false)->searchable()->options(fn () => Location::options($types));
    }

    /** Pilih aset dengan pencarian kode / nama / nomor seri / ruangan (cepat untuk ribuan aset). */
    public static function asset(string $name = 'asset_id', string $label = 'Aset', ?\Closure $scope = null): Select
    {
        return Select::make($name)->label($label)->native(false)->searchable()
            ->getSearchResultsUsing(fn (string $search) => \App\Models\Asset::query()
                ->when($scope, $scope)
                ->where(fn ($q) => $q->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")->orWhereHas('location', fn ($l) => $l->where('full_path', 'like', "%{$search}%")))
                ->with('location')->limit(40)->get()
                ->mapWithKeys(fn ($a) => [$a->id => $a->asset_code.' · '.$a->name.($a->location ? ' · '.$a->location->labelText() : '')])->all())
            ->getOptionLabelUsing(fn ($value) => ($a = \App\Models\Asset::with('location')->find($value)) ? $a->asset_code.' · '.$a->name.($a->location ? ' · '.$a->location->labelText() : '') : null);
    }

    public static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->numeric()->prefix('Rp')->minValue(0)
            ->helperText('Kosongkan bila belum diketahui (tidak diisi nol).');
    }

    /** Unggahan privat: JPG/PNG/PDF, nama acak, ukuran dibatasi. */
    public static function upload(string $name, string $label, string $dir, bool $multiple = false): FileUpload
    {
        $f = FileUpload::make($name)->label($label)->disk('local')->directory($dir)->visibility('private')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])->maxSize(8192)
            ->image()->imageResizeMode('contain')->imageResizeTargetWidth(1920)->imageResizeTargetHeight(1920)
            ->downloadable(false)->openable(false)->previewable(true);

        return $multiple ? $f->multiple()->maxFiles(6) : $f;
    }

    public static function statusColumn(string $name, array $labels, array $colors, string $label = 'Status'): TextColumn
    {
        return TextColumn::make($name)->label($label)->badge()
            ->formatStateUsing(fn ($state) => $labels[$state] ?? $state)
            ->color(fn ($state) => $colors[$state] ?? 'gray');
    }

    public static function priorityColumn(string $name = 'priority'): TextColumn
    {
        return TextColumn::make($name)->label('Prioritas')->badge()->sortable()
            ->color(fn ($state) => \App\Models\ServiceRequest::PRIORITY_COLORS[$state] ?? 'gray')
            ->icon(fn ($state) => $state === 'P1' ? 'heroicon-m-exclamation-triangle' : null);
    }

    public static function moneyColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)->label($label)->alignEnd()
            ->formatStateUsing(fn ($state) => Fmt::rp($state))
            ->visible(fn () => (bool) auth()->user()?->canSeeFinancials());
    }

    /** Jalankan aksi alur kerja; tampilkan pesan pelanggaran aturan tanpa stack trace. */
    public static function attempt(callable $fn, ?string $success = null): bool
    {
        try {
            $fn();
            if ($success) {
                Notification::make()->title($success)->success()->send();
            }

            return true;
        } catch (WorkflowException $e) {
            Notification::make()->title($e->getCode() === 409 ? 'Konflik versi' : 'Tidak dapat diproses')->body($e->getMessage())->danger()->persistent()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Periksa isian')->body(collect($e->errors())->flatten()->join(' '))->danger()->persistent()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Terjadi kesalahan')->body('Permintaan tidak tersimpan. Ulangi atau hubungi IT. Kode: '.request()->attributes->get('request_id'))->danger()->persistent()->send();
        }

        return false;
    }
}
