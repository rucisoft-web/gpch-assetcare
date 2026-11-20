@php
    use App\Support\Fmt;
    use App\Models\WorkOrder;
    use App\Models\ServiceRequest;
    use App\Filament\Resources\WorkOrders\WorkOrderResource as WO;
    use App\Filament\Resources\ServiceRequests\ServiceRequestResource as SR;
    use App\Filament\Resources\Assets\AssetResource as AR;
    $t = $tasks;
    $prio = fn ($p) => '<span class="ac-prio ac-'.$p.'">'.$p.'</span>';
@endphp
<x-filament-panels::page>
<div class="arah">
    <div class="arah-card">
        <div class="arah-hero">
            <div>
                <h2>Sampurasun, {{ $user->name }}</h2>
                <div class="arah-muted">{{ $user->job_title }} · {{ $roles ?: 'tanpa peran' }}</div>
                <div class="arah-muted" style="margin-top:.25rem">{{ $property?->name }} · diperbarui {{ Fmt::dt(now(), 'd M Y H:i') }} WIB
                    <span class="ac-net" x-data="{ on: navigator.onLine }" x-on:online.window="on = true" x-on:offline.window="on = false">
                        · <span class="arah-dot" :style="'background:' + (on ? '#4F7A3A' : '#A8432A')"></span><span x-text="on ? 'online' : 'offline — kiriman belum dapat diproses'"></span>
                    </span>
                </div>
            </div>
            <div class="ac-actions" style="margin-top:0">
                <a class="arah-btn arah-btn-primary" href="{{ \App\Filament\Pages\ScanQr::getUrl() }}">Scan QR</a>
                @if ($user->hasPermission('request.create'))<a class="arah-btn" href="{{ SR::getUrl('create') }}">Lapor kerusakan</a>@endif
            </div>
        </div>
        @if ($kpi)
            <div class="arah-kamasan" style="margin-top:1rem"></div>
            <div class="arah-grid arah-grid-4">
                <div class="arah-stat"><span class="label">Work order terbuka</span><span class="value">{{ $kpi['open_wo'] }}</span>
                    <span class="sub"><a href="{{ WO::getUrl('index', ['tab' => 'overdue']) }}" style="color:var(--k-merah)">{{ $kpi['overdue_wo'] }} lewat target</a> · {{ $kpi['p1p2_open'] }} P1/P2</span></div>
                <div class="arah-stat"><span class="label">Respons sesuai SLA (30 hari)</span><span class="value">{{ Fmt::pct($kpi['sla_response']['value']) }}</span><span class="sub">{{ $kpi['sla_response']['note'] }}</span></div>
                <div class="arah-stat"><span class="label">MTTR (bruto)</span><span class="value">{{ $kpi['mttr']['value'] === null ? 'N/A' : Fmt::minutes($kpi['mttr']['value']) }}</span><span class="sub">{{ $kpi['mttr']['value'] === null ? $kpi['mttr']['note'] : 'bersih '.Fmt::minutes($kpi['mttr']['net']).' · '.$kpi['mttr']['note'] }}</span></div>
                <div class="arah-stat"><span class="label">Laporan belum ditriase</span><span class="value" style="color:{{ $kpi['requests_untriaged'] ? 'var(--k-merah)' : 'inherit' }}">{{ $kpi['requests_untriaged'] }}</span><span class="sub">dari {{ $kpi['requests_open'] }} laporan terbuka</span></div>
            </div>
            <div class="arah-grid arah-grid-4" style="margin-top:1rem">
                <div class="arah-stat"><span class="label">Aset terdaftar</span><span class="value">{{ Fmt::num($kpi['assets_total']) }}</span><span class="sub">{{ Fmt::num($kpi['assets_active']) }} aktif · {{ Fmt::num($kpi['assets_draft']) }} draft</span></div>
                <div class="arah-stat"><span class="label">Belum terverifikasi</span><span class="value">{{ Fmt::num($kpi['assets_unverified']) }}</span><span class="sub"><a href="{{ AR::getUrl('index', ['filters' => ['verification_status' => ['value' => 'unverified']]]) }}">lihat daftar</a></span></div>
                <div class="arah-stat"><span class="label">Label belum terpasang</span><span class="value">{{ Fmt::num($kpi['labels_pending']) }}</span><span class="sub">termasuk perlu label ulang</span></div>
                <div class="arah-stat"><span class="label">Downtime · room days</span><span class="value">{{ Fmt::num($kpi['downtime_hours'], 1) }} j</span><span class="sub">{{ Fmt::num($kpi['room_days'], 2) }} room days terdampak · {{ $kpi['repeat_failures'] }} kegagalan berulang</span></div>
            </div>
        @endif
    </div>

    <h3 style="margin:1.4rem 0 .6rem; font-size:1rem; font-weight:700">Pekerjaan saya</h3>
    <div class="arah-grid arah-grid-2">
        @if ($user->hasPermission('wo.execute') || $t['assigned']->isNotEmpty())
        <div class="arah-card">
            <h3>Tugas work order <small>ditugaskan kepada saya</small></h3>
            <ul class="arah-list">
            @forelse ($t['assigned'] as $w)
                <li><span>{!! $prio($w->priority) !!} <a href="{{ WO::getUrl('view', ['record' => $w]) }}">{{ $w->number }}</a> {{ \Illuminate\Support\Str::limit($w->title, 50) }}<br><span class="arah-muted">{{ $w->location?->full_path ?? $w->asset?->location?->full_path }}</span></span>
                    <span style="text-align:right"><span class="arah-badge {{ Fmt::badgeClass(WorkOrder::STATUS_COLORS[$w->status] ?? null) }}">{{ WorkOrder::STATUSES[$w->status] }}</span><br>
                    @if ($w->due_at)<span class="arah-muted" style="{{ $w->due_at->isPast() && ! $w->restored_at ? 'color:var(--k-merah);font-weight:600' : '' }}">target {{ Fmt::dt($w->due_at, 'd M H:i') }}</span>@endif</span></li>
            @empty
                <li class="arah-empty">Tidak ada tugas aktif.</li>
            @endforelse
            </ul>
        </div>
        @endif

        @if ($user->hasPermission('request.triage'))
        <div class="arah-card">
            <h3>Laporan menunggu triase <small>urut prioritas & waktu terima</small></h3>
            <ul class="arah-list">
            @forelse ($t['to_triage'] as $r)
                <li><span>{!! $prio($r->priority) !!} <a href="{{ SR::getUrl('view', ['record' => $r]) }}">{{ $r->number }}</a> {{ \Illuminate\Support\Str::limit($r->symptom, 48) }}<br><span class="arah-muted">{{ $r->location?->full_path }}{{ $r->asset ? ' · '.$r->asset->asset_code : '' }}</span></span>
                    <span class="arah-muted" style="text-align:right; {{ $r->responseOverdue() ? 'color:var(--k-merah);font-weight:600' : '' }}">respons s.d.<br>{{ Fmt::dt($r->response_due_at, 'd M H:i') }}</span></li>
            @empty
                <li class="arah-empty">Semua laporan sudah ditriase.</li>
            @endforelse
            </ul>
        </div>
        @endif

        @if ($t['to_verify']->isNotEmpty() || $t['to_approve']->isNotEmpty() || $t['transfers']->isNotEmpty() || $t['room_blocks']->isNotEmpty())
        <div class="arah-card">
            <h3>Persetujuan & verifikasi tertunda</h3>
            <ul class="arah-list">
                @foreach ($t['to_approve'] as $w)
                    <li><span>Setujui biaya <a href="{{ WO::getUrl('view', ['record' => $w]) }}">{{ $w->number }}</a> {{ \Illuminate\Support\Str::limit($w->title, 40) }}</span><span class="arah-muted">{{ $user->canSeeFinancials() ? Fmt::rp($w->estimated_cost) : '' }}</span></li>
                @endforeach
                @foreach ($t['to_verify'] as $w)
                    <li><span>Verifikasi hasil <a href="{{ WO::getUrl('view', ['record' => $w]) }}">{{ $w->number }}</a> {{ \Illuminate\Support\Str::limit($w->title, 40) }}</span><span class="arah-muted">selesai {{ Fmt::dt($w->completed_at, 'd M H:i') }}</span></li>
                @endforeach
                @foreach ($t['transfers'] as $m)
                    <li><span>Mutasi <a href="{{ \App\Filament\Resources\AssetTransfers\AssetTransferResource::getUrl('view', ['record' => $m]) }}">{{ $m->number }}</a> {{ \Illuminate\Support\Str::limit($m->reason, 40) }}</span><span class="arah-badge arah-kuning">diajukan</span></li>
                @endforeach
                @foreach ($t['room_blocks'] as $b)
                    <li><span>Blokir kamar {{ $b->room?->room_ref ?: $b->room?->name }} — {{ $b->workOrder?->number }}</span><a href="{{ \App\Filament\Resources\RoomBlocks\RoomBlockResource::getUrl('index') }}">konfirmasi FO</a></li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="arah-card">
            <h3>Laporan saya <small>masih terbuka</small></h3>
            <ul class="arah-list">
            @forelse ($t['my_requests'] as $r)
                <li><span><a href="{{ SR::getUrl('view', ['record' => $r]) }}">{{ $r->number }}</a> {{ \Illuminate\Support\Str::limit($r->symptom, 50) }}<br><span class="arah-muted">{{ $r->location?->full_path }}</span></span>
                    <span class="arah-badge {{ Fmt::badgeClass(ServiceRequest::STATUS_COLORS[$r->status] ?? null) }}">{{ ServiceRequest::STATUSES[$r->status] }}</span></li>
            @empty
                <li class="arah-empty">Tidak ada laporan terbuka. Temukan kerusakan? Tekan <strong>Scan</strong> lalu <strong>Lapor kerusakan</strong>.</li>
            @endforelse
            </ul>
        </div>

        @if ($kpi)
        <div class="arah-card">
            <h3>Backlog work order <small>umur sejak dibuat</small></h3>
            @php $maxAging = max(1, max($kpi['aging'])); @endphp
            @foreach ($kpi['aging'] as $label => $n)
                <div style="display:flex; justify-content:space-between; font-size:.8rem; margin:.4rem 0 .25rem"><span>{{ $label }}</span><strong>{{ $n }}</strong></div>
                <div class="arah-bar"><span style="width:{{ $n / $maxAging * 100 }}%; background:{{ $loop->last ? '#A8432A' : ($loop->first ? '#4F7A3A' : '#C08A1E') }}"></span></div>
            @endforeach
            <div class="arah-muted" style="margin-top:.6rem">PM tepat waktu: {{ $kpi['pm_on_time']['note'] }}</div>
        </div>
        <div class="arah-card">
            <h3>Kondisi fisik aset <small>A baik … E tidak layak</small></h3>
            @php $condTotal = max(1, array_sum($kpi['conditions'])); $condColor = ['A' => '#2F7A3F', 'B' => '#6E9A4A', 'C' => '#C08A1E', 'D' => '#C0662A', 'E' => '#A8432A', '?' => '#9A8F80']; @endphp
            @foreach (['A', 'B', 'C', 'D', 'E', '?'] as $g)
                @php $n = $kpi['conditions'][$g] ?? 0; @endphp
                <div style="display:flex; justify-content:space-between; font-size:.8rem; margin:.35rem 0 .2rem"><span>{{ $g === '?' ? 'Belum dinilai' : \App\Models\Asset::CONDITIONS[$g] }}</span><strong>{{ $n }}</strong></div>
                <div class="arah-bar"><span style="width:{{ $n / $condTotal * 100 }}%; background:{{ $condColor[$g] }}"></span></div>
            @endforeach
            <div class="arah-muted" style="margin-top:.6rem">{{ $kpi['assets_de'] }} aset kondisi D/E perlu kajian · {{ $kpi['assets_out'] }} aset aktif tidak beroperasi/dalam perbaikan · {{ $kpi['warranty_90'] }} garansi berakhir ≤ 90 hari</div>
            @isset($kpi['cost'])
                <div class="arah-kamasan" style="margin-top:.8rem"></div>
                <div class="arah-muted">Biaya perawatan 30 hari: <strong>{{ Fmt::rp($kpi['cost']['cash_out']) }}</strong> cash out + {{ Fmt::rp($kpi['cost']['internal']) }} alokasi tenaga internal · provisional {{ Fmt::rp($kpi['cost']['provisional']) }}, final {{ Fmt::rp($kpi['cost']['final']) }}</div>
            @endisset
        </div>
        @endif
    </div>
</div>
</x-filament-panels::page>
