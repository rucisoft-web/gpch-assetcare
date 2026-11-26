<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentProperty;
use App\Support\PermissionCatalog;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentProperty::class);
    }

    public function boot(): void
    {
        // Timestamp tersimpan UTC; seluruh tampilan & input memakai Asia/Jakarta
        FilamentTimezone::set(config('assetcare.display_timezone'));
        \Filament\Forms\Components\DatePicker::configureUsing(fn ($c) => $c->native(false)->displayFormat('d/m/Y'));
        \Filament\Forms\Components\DateTimePicker::configureUsing(fn ($c) => $c->native(false)->displayFormat('d/m/Y H:i')->seconds(false));
        \Filament\Tables\Table::configureUsing(fn ($t) => $t->defaultPaginationPageOption(25)->striped());

        // versi = isi file, agar browser/CDN langsung memakai CSS/JS terbaru setelah pembaruan (bukan versi lama dari cache)
        $v = fn (string $f) => asset($f).'?v='.substr((string) @md5_file(public_path($f)), 0, 10);
        FilamentAsset::register([
            Css::make('asset-care-gpch', $v('css/assetcare.css')),
            Js::make('jsqr', $v('js/vendor/jsQR.js')),
            Js::make('asset-care-scan', $v('js/assetcare-scan.js')),
        ]);

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Izin dapat dipakai lewat Gate/@can: Gate::allows('wo.verify')
        Gate::before(function (User $user, string $ability) {
            if (array_key_exists($ability, PermissionCatalog::PERMISSIONS)) {
                return $user->hasPermission($ability);
            }

            return null;
        });

        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)->by(($request->input('email') ?? '').'|'.$request->ip()));

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        if (! $this->app->runningInConsole()) {
            request()->attributes->set('request_id', (string) Str::ulid());
        }
    }
}
