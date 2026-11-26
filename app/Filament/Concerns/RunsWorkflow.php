<?php

namespace App\Filament\Concerns;

use App\Filament\Support\F;

/** Jalankan aksi alur kerja dari halaman detail lalu muat ulang halaman agar status & tombol mutakhir. */
trait RunsWorkflow
{
    protected function run(callable $fn, ?string $success = null): bool
    {
        $ok = F::attempt($fn, $success);
        if ($ok) {
            $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record->getKey()]), navigate: false);
        }

        return $ok;
    }
}
