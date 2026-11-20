@auth
@php
    $r = request();
    $items = [
        ['Beranda', \App\Filament\Pages\Beranda::getUrl(), 'M2.25 12 11.2 3.05a1.13 1.13 0 0 1 1.6 0L21.75 12M4.5 9.75v10.13c0 .62.5 1.12 1.13 1.12H9.75v-4.88c0-.62.5-1.12 1.13-1.12h2.25c.62 0 1.12.5 1.12 1.12V21h4.13c.62 0 1.12-.5 1.12-1.12V9.75', $r->routeIs('filament.admin.pages.beranda'), ''],
        ['Tugas Saya', \App\Filament\Resources\WorkOrders\WorkOrderResource::getUrl('index', ['tab' => 'saya']), 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', $r->routeIs('filament.admin.resources.work-orders.*'), ''],
        ['Scan', \App\Filament\Pages\ScanQr::getUrl(), 'M3.75 4.88c0-.62.5-1.13 1.13-1.13h4.5c.62 0 1.12.5 1.12 1.13v4.5c0 .62-.5 1.12-1.12 1.12h-4.5c-.63 0-1.13-.5-1.13-1.12v-4.5ZM3.75 14.63c0-.63.5-1.13 1.13-1.13h4.5c.62 0 1.12.5 1.12 1.13v4.5c0 .62-.5 1.12-1.12 1.12h-4.5c-.63 0-1.13-.5-1.13-1.12v-4.5ZM13.5 4.88c0-.62.5-1.13 1.13-1.13h4.5c.62 0 1.12.5 1.12 1.13v4.5c0 .62-.5 1.12-1.12 1.12h-4.5c-.63 0-1.13-.5-1.13-1.12v-4.5ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z', $r->routeIs('filament.admin.pages.scan-qr'), 'scan'],
        ['Lapor', \App\Filament\Resources\ServiceRequests\ServiceRequestResource::getUrl('create'), 'M12 9v3.75m-9.3 3.38c-.87 1.5.21 3.37 1.95 3.37h14.7c1.73 0 2.81-1.87 1.95-3.37L13.95 3.38c-.87-1.5-3.03-1.5-3.9 0L2.7 16.13ZM12 15.75h.01v.01H12v-.01Z', $r->routeIs('filament.admin.resources.service-requests.create'), ''],
        ['Akun', filament()->getProfileUrl(), 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.12a7.5 7.5 0 0 1 15 0A18 18 0 0 1 12 21.75c-2.68 0-5.22-.58-7.5-1.63Z', $r->routeIs('filament.admin.auth.profile'), ''],
    ];
@endphp
<nav class="ac-bottomnav" aria-label="Navigasi cepat">
    @foreach ($items as [$label, $url, $path, $on, $cls])
        <a href="{{ $url }}" class="{{ $cls }} {{ $on ? 'on' : '' }}" @if($on) aria-current="page" @endif>
            <span class="ic"><svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg></span>
            <span class="tx">{{ $label }}</span>
        </a>
    @endforeach
</nav>
@endauth
