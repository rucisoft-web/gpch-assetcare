@if (request()->routeIs('filament.admin.auth.*') && \App\Support\Brand::showOrnament())
    <div class="ac-login-wayang" aria-hidden="true">
        <img src="{{ asset('images/wayang-golek.png') }}" alt="" class="arah-wayang-foto">
    </div>
    <div class="ac-login-lines" aria-hidden="true"></div>
@endif
