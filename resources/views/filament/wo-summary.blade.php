@php
    use App\Models\ServiceRequest;
    use App\Models\WorkOrder;
    use App\Support\Fmt;
    $barColor = ['danger' => '#A8432A', 'warning' => '#C08A1E', 'success' => '#2F7A3F', 'gray' => '#a8a29e'][$color] ?? '#a8a29e';
    $steps = ['draft', 'approved', 'assigned', 'in_progress', 'completed', 'verified', 'closed'];
    $current = match ($wo->status) { 'awaiting_approval' => 'draft', 'on_hold' => 'in_progress', 'reopened' => 'assigned', default => $wo->status };
    $idx = array_search($current, $steps, true);
@endphp
<div class="arah">
    <div class="arah-card">
        <div style="display:flex; flex-wrap:wrap; gap:.4rem; align-items:center">
            <span class="ac-prio ac-{{ $wo->priority }}">{{ ServiceRequest::PRIORITIES[$wo->priority] ?? $wo->priority }}</span>
            <span class="arah-badge arah-biru">{{ WorkOrder::STATUSES[$wo->status] }}</span>
            @if ($wo->is_safety)<span class="arah-badge arah-merah">Keselamatan</span>@endif
            @if ($wo->isCritical())<span class="arah-badge arah-kuning">Pekerjaan kritis · verifikator berbeda</span>@endif
            @if ($wo->reopen_count)<span class="arah-badge arah-merah">Dibuka ulang {{ $wo->reopen_count }}×</span>@endif
            @if ($wo->status === 'on_hold')<span class="arah-badge arah-kuning">Ditunda: {{ $wo->hold_reason }}</span>@endif
            @if ($wo->cost_status === 'final')<span class="arah-badge arah-hijau">Biaya final</span>@endif
        </div>
        <ol class="ac-steps" style="margin-top:.8rem">
            @foreach ($steps as $i => $s)
                <li class="ac-step {{ $idx !== false && $i < $idx ? 'done' : ($i === $idx ? 'on' : '') }}">{{ WorkOrder::STATUSES[$s] }}</li>
            @endforeach
        </ol>
        <div class="arah-kamasan" style="margin:.8rem 0"></div>
        <dl class="ac-kv">
            <div><dt>Lokasi</dt><dd>{{ $wo->location?->full_path ?? $wo->asset?->location?->full_path ?? '-' }}</dd></div>
            <div><dt>Aset</dt><dd>{{ $wo->asset ? $wo->asset->asset_code.' · '.$wo->asset->name : '-' }}</dd></div>
            <div><dt>Laporan diterima</dt><dd>{{ Fmt::dt($wo->received_at) }}</dd></div>
            <div><dt>Batas respons</dt><dd>{{ Fmt::dt($wo->response_due_at) }}</dd></div>
            <div><dt>Target pemulihan</dt><dd>{{ $wo->due_at ? Fmt::dt($wo->due_at) : 'segera (darurat)' }} · kalender {{ $wo->sla_calendar === 'business' ? 'jam kerja' : '24 jam' }}</dd></div>
            <div><dt>Waktu hold</dt><dd>{{ Fmt::minutes($wo->hold_minutes) }} @if($wo->sla_cycle > 1)· siklus SLA {{ $wo->sla_cycle }}@endif</dd></div>
            @if ($wo->started_at)<div><dt>Mulai dikerjakan</dt><dd>{{ Fmt::dt($wo->started_at) }}</dd></div>@endif
            @if ($wo->restored_at)<div><dt>Layanan pulih</dt><dd>{{ Fmt::dt($wo->restored_at) }}</dd></div>@endif
        </dl>
        @if ($pct !== null && $wo->isOpen())
            <div style="margin-top:.8rem">
                <div class="arah-muted" style="display:flex; justify-content:space-between"><span>SLA pemulihan terpakai</span><b style="color:{{ $barColor }}">{{ Fmt::pct($pct, 0) }}</b></div>
                <div style="height:8px; border-radius:6px; background:rgba(120,113,108,.18); overflow:hidden; margin-top:.25rem">
                    <div style="height:100%; width:{{ min(100, $pct) }}%; background:{{ $barColor }}"></div>
                </div>
            </div>
        @endif
        @if ($wo->description)
            <p class="arah-muted" style="margin-top:.8rem; white-space:pre-line">{{ $wo->description }}</p>
        @endif
    </div>
</div>
