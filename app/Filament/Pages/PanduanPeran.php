<?php

namespace App\Filament\Pages;

use App\Support\Help\RoleGuide;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/** Panduan penggunaan per peran, dengan tangkapan layar web atau ponsel sesuai peran. */
class PanduanPeran extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Umum';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Panduan per peran';

    protected static ?string $title = 'Panduan penggunaan per peran';

    protected static ?string $slug = 'panduan-peran';

    protected string $view = 'filament.pages.panduan-peran';

    #[Url(as: 'peran')]
    public ?string $peran = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    public function mount(): void
    {
        [$mine, $all] = RoleGuide::forUser(auth()->user());
        if (! $this->peran || ! isset($all[$this->peran])) {
            $this->peran = $mine[0] ?? array_key_first($all);
        }
    }

    public function pilih(string $slug): void
    {
        $this->peran = $slug;
    }

    protected function getViewData(): array
    {
        [$mine, $all] = RoleGuide::forUser(auth()->user());

        return ['mine' => $mine, 'all' => $all, 'g' => $all[$this->peran] ?? null];
    }
}
