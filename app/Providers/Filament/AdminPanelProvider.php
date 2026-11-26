<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Beranda;
use App\Http\Middleware\EnsurePasswordChanged;
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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Panel /admin "Asset Care": tema kertas & emas; logo, nama & warna utama dari Pengaturan › Identitas perusahaan. */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(EditProfile::class, isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable()->brandName(\App\Support\Brand::panelName()),
            ])
            ->brandName(fn () => \App\Support\Brand::panelName())
            ->brandLogo(fn () => \App\Support\Brand::logoUrl())
            ->darkModeBrandLogo(fn () => \App\Support\Brand::logoUrl(true))
            ->brandLogoHeight('3.1rem')
            ->favicon(fn () => \App\Support\Brand::faviconUrl())
            ->colors(fn () => [
                'primary' => Color::generateV3Palette(\App\Support\Brand::color()),  // Pengaturan › Identitas perusahaan
                'danger' => Color::generateV3Palette('#A8432A'),   // kencu
                'warning' => Color::generateV3Palette('#C08A1E'),
                'success' => Color::generateV3Palette('#2F7A3F'),
                'info' => Color::generateV3Palette('#34506E'),
                'gray' => Color::Stone,
            ])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->defaultAvatarProvider(\App\Filament\Support\InitialsAvatarProvider::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->globalSearch(false)
            ->navigationGroups([
                NavigationGroup::make('Umum'),
                NavigationGroup::make('Pemeliharaan'),
                NavigationGroup::make('Aset'),
                NavigationGroup::make('Pengendalian Aset'),
                NavigationGroup::make('Aset Tetap & Dokumen'),
                NavigationGroup::make('Laporan'),
                NavigationGroup::make('Pengaturan')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Beranda::class])
            ->widgets([])
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => Blade::render('<x-ac.help-button />'))
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn () => Blade::render('<x-ac.property-badge />'))
            ->renderHook(PanelsRenderHook::FOOTER, fn () => Blade::render('<x-ac.footer />'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => \App\Support\Brand::showOrnament() ? Blade::render('<x-ac.sidebar-wayang />') : '')
            ->renderHook(PanelsRenderHook::BODY_END, fn () => Blade::render('<x-ac.login-ornament /><x-ac.bottom-nav />'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString('<meta name="theme-color" content="'.\App\Support\Brand::color().'"><style>:root{--k-hijau-gp:'.\App\Support\Brand::color().';}</style><link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=cormorant-garamond:600,700i,700&display=swap" rel="stylesheet">'))
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
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\RedirectFieldOfficer::class,
                EnsurePasswordChanged::class,
            ]);
    }
}
