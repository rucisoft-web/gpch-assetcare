<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\DasborEksekutif;
use App\Http\Middleware\SetCurrentCompany;
use App\Models\Company;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->passwordReset()
            ->profile()
            ->brandName('Ruci Asset Care')
            ->favicon(asset('favicon.svg'))
            ->colors([
                // Biru dongker khas Ruci Asset Care; 600 dipakai tombol utama.
                'primary' => [
                    50 => '#eef5fb', 100 => '#d6e6f3', 200 => '#adcce6', 300 => '#7eaed6', 400 => '#4d8cc0',
                    500 => '#2a6ea6', 600 => '#14558a', 700 => '#0b3a5c', 800 => '#0a3150', 900 => '#082842', 950 => '#051a2c',
                ],
                'warning' => Color::Amber,
            ])
            ->font('Inter')
            ->tenant(Company::class, slugAttribute: 'slug', ownershipRelationship: 'company')
            ->tenantMiddleware([SetCurrentCompany::class], isPersistent: true)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: fn () => (bool) config('asset.mfa_required'),
            )
            ->navigationGroups([
                NavigationGroup::make('Intelijen Aset'),
                NavigationGroup::make('Identifikasi Awal'),
                NavigationGroup::make('Inspeksi & Pemeliharaan'),
                NavigationGroup::make('Risiko & Investasi'),
                NavigationGroup::make('Layanan Umum'),
                NavigationGroup::make('ATK & Persediaan'),
                NavigationGroup::make('Legal & Dokumen'),
                NavigationGroup::make('Konfigurasi')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([DasborEksekutif::class])
            ->widgets([])
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => auth()->guest() ? view('filament.auth.hero', ['eyebrow' => 'Manajemen Aset Terpadu']) : '')
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString('<link rel="stylesheet" href="'.asset('css/asset-care.css').'?v='.@filemtime(public_path('css/asset-care.css')).'">'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
