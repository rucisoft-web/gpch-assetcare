@php
    use App\Models\Asset;
    use App\Models\InstallationEvent;
    use App\Support\Fmt;
    $a = $asset;
    $label = $a->activeLabel;
    $opColor = match ($a->operational_status) { 'serviceable' => 'arah-hijau', 'degraded' => 'arah-kuning', default => 'arah-merah' };
    $condColor = match ($a->condition_grade) { 'A', 'B' => 'arah-hijau', 'C' => 'arah-kuning', 'D', 'E' => 'arah-merah', default => 'arah-abu' };
    $openWo = $a->workOrders()->whereIn('status', \App\Models\WorkOrder::OPEN)->latest()->first();
    $dups = $a->duplicateSerials();
@endphp
<div class="arah">
    <div class="arah-card">
        <div class="ac-assetcard">
            <div>
                <div class="code">{{ $a->asset_code }}</div>
                <h2>{{ $a->name }}</h2>
                <div class="arah-muted">{{ $a->category?->name }} @if($a->brand || $a->model) · {{ trim($a->brand.' '.$a->model) }} @endif @if($a->serial_number) · SN {{ $a->serial_number }} @endif</div>
                <div style="display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem">
                    <span class="arah-badge {{ $opColor }}">{{ Asset::OPERATIONAL[$a->operational_status] }}</span>
                    <span class="arah-badge {{ $condColor }}">Kondisi {{ $a->condition_grade ? Asset::CONDITIONS[$a->condition_grade] : 'belum dinilai' }}</span>
                    <span class="arah-badge {{ $a->lifecycle_status === 'active' ? 'arah-biru' : 'arah-abu' }}">{{ Asset::LIFECYCLE[$a->lifecycle_status] }}</span>
                    <span class="arah-badge {{ $a->verification_status === 'verified' ? 'arah-hijau' : 'arah-kuning' }}">{{ Asset::VERIFICATION[$a->verification_status] }}</span>
                    @if ($a->criticality)<span class="arah-badge {{ $a->isCritical() ? 'arah-merah' : 'arah-abu' }}">Kritikalitas {{ Asset::CRITICALITY[$a->criticality] }}</span>@endif
                    @if ($a->in_transit)<span class="arah-badge arah-kuning">Dalam perjalanan (mutasi)</span>@endif
                </div>
                <div class="arah-kamasan" style="margin-top:.9rem"></div>
                <dl class="ac-kv">
                    <div><dt>Lokasi terkini</dt><dd>{{ $a->location?->full_path ?? '-' }}</dd></div>
                    <div><dt>Departemen</dt><dd>{{ $a->department?->name ?? '-' }}</dd></div>
                    <div><dt>Penanggung jawab</dt><dd>{{ $a->custodian?->name ?? $a->custodian_unit ?? '-' }}</dd></div>
                    <div><dt>Inspeksi terakhir</dt><dd>{{ Fmt::dt($a->last_inspected_at, 'd M Y') }}</dd></div>
                    <div><dt>Garansi</dt><dd>{{ $a->warranty_until ? $a->warranty_until->format('d M Y').($a->warrantyActive() ? ' · aktif' : ' · berakhir') : 'tidak tercatat' }}</dd></div>
                    <div><dt>Aset induk</dt><dd>@if($a->parent)<a href="{{ \App\Filament\Resources\Assets\AssetResource::getUrl('view', ['record' => $a->parent]) }}" style="color:var(--k-link)">{{ $a->parent->asset_code }}</a>@else - @endif</dd></div>
                    <div><dt>Komponen aktif</dt><dd>{{ $a->children()->count() }}</dd></div>
                    <div><dt>Work order terbuka</dt><dd>@if($openWo)<a href="{{ \App\Filament\Resources\WorkOrders\WorkOrderResource::getUrl('view', ['record' => $openWo]) }}" style="color:var(--k-link)">{{ $openWo->number }}</a> · {{ \App\Models\WorkOrder::STATUSES[$openWo->status] }}@else tidak ada @endif</dd></div>
                </dl>
                @if ($dups->isNotEmpty())
                    <div class="ac-scan-msg err" style="margin-top:.8rem">Nomor seri sama dengan {{ $dups->pluck('asset_code')->join(', ') }} — periksa kemungkinan duplikasi.</div>
                @endif
                @if ($a->lifecycle_status === 'draft' && ($m = $a->missingForActivation()))
                    <div class="ac-scan-msg" style="margin-top:.8rem">Belum dapat diaktifkan — lengkapi: {{ implode(', ', $m) }}.</div>
                @endif
            </div>
            <div style="text-align:center">
                @if ($photo = $a->mainPhotoUrl())
                    <a href="{{ $photo }}" target="_blank" rel="noopener"><img src="{{ $photo }}" alt="Foto {{ $a->name }}" class="ac-photo" loading="lazy"></a>
                @endif
                @if ($qr)
                    <div class="ac-qr">{!! $qr !!}</div>
                    <div class="arah-muted" style="margin-top:.3rem">Label: {{ InstallationEvent::STATUSES[$a->installation_status] ?? $a->installation_status }}</div>
                    <div class="arah-muted">{{ $label->printItems()->count() }}× dicetak</div>
                @else
                    <div class="arah-empty">Belum ada label aktif</div>
                @endif
            </div>
        </div>
    </div>
</div>
