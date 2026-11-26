@php
    $k3 = auth()->user()?->hasPermission('document.view') ? $asset->expiredK3Documents() : collect();
@endphp
@if ($k3->isNotEmpty())
<div class="arah" style="margin-bottom:.8rem">
    <div class="arah-card" style="border-left:4px solid var(--k-merah); background:color-mix(in srgb, var(--k-merah) 8%, transparent)">
        <strong style="color:var(--k-merah)">Tidak boleh dioperasikan</strong>
        <div class="arah-muted" style="margin-top:.25rem">
            Izin / riksa uji K3 kedaluwarsa:
            @foreach ($k3 as $d)
                <a href="{{ \App\Filament\Resources\AssetDocuments\AssetDocumentResource::getUrl('view', ['record' => $d]) }}" style="text-decoration:underline">{{ $d->register_no }} {{ $d->title }} (berakhir {{ $d->valid_until?->format('d M Y') }})</a>@if (! $loop->last), @endif
            @endforeach
            . Perbarui riksa uji sebelum aset dipakai kembali.
        </div>
    </div>
</div>
@endif
