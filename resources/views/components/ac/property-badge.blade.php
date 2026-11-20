@php $property = app(\App\Support\CurrentProperty::class)->get(); @endphp
@if ($property)
    <span class="arah-context" title="Properti aktif · waktu ditampilkan {{ config('assetcare.display_timezone') }}">
        <span class="arah-dot" style="background:#4F7A3A"></span>
        {{ $property->code }} · {{ \App\Support\Brand::APP_NAME }}
    </span>
@endif
