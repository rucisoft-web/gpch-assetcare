@php
    use App\Support\Money;
    use App\Filament\Support\Domain;
    $k = $d['kpi'];
@endphp
<x-filament-panels::page>
<div class="rac-dash">
    <div class="rac-header">
        <div>
            <div class="rac-eyebrow">{{ $company?->name }}</div>
            <h1>Bagian Umum</h1>
            <p>Rapat, kendaraan dinas, persediaan, dan jatuh tempo dokumen · {{ $d['today']->translatedFormat('l, d F Y') }}</p>
        </div>
    </div>

    <div class="rac-kpis">
        <a class="rac-kpi" href="{{ $links['rooms'] }}"><span>Rapat hari ini</span><strong>{{ $k['meetings_today'] }}</strong><small>Lihat jadwal →</small></a>
        <a class="rac-kpi {{ $k['pending_approvals'] ? 'warning' : 'good' }}" href="{{ $links['pending_rooms'] }}"><span>Menunggu persetujuan</span><strong>{{ $k['pending_approvals'] }}</strong><small>Ruang, kendaraan, barang →</small></a>
        <a class="rac-kpi good" href="{{ $links['vehicles'] }}"><span>Kendaraan siap pakai</span><strong>{{ $k['vehicles_ready'] }} <small>/ {{ $k['vehicles_total'] }}</small></strong><small>{{ $k['vehicles_in_use'] }} sedang dipakai →</small></a>
        <a class="rac-kpi {{ $k['documents_action'] ? 'danger' : 'good' }}" href="{{ $links['documents'] }}"><span>Dokumen perlu tindakan</span><strong>{{ $k['documents_action'] }}</strong><small>Kedaluwarsa / segera berakhir →</small></a>
        <a class="rac-kpi {{ $k['low_stock'] ? 'danger' : 'good' }}" href="{{ $links['low_stock'] }}"><span>Stok menipis</span><strong>{{ $k['low_stock'] }}</strong><small>Barang di bawah minimum →</small></a>
        <a class="rac-kpi" href="{{ $links['stock'] }}"><span>Nilai persediaan</span><strong>{{ Money::short($k['stock_value']) }}</strong><small>Lihat stok →</small></a>
        <a class="rac-kpi" href="{{ $links['vehicles'] }}"><span>BBM bulan ini</span><strong>{{ Money::short($k['fuel_month']) }}</strong><small>Per kendaraan →</small></a>
        <a class="rac-kpi" href="{{ $links['vehicles'] }}"><span>Biaya servis YTD</span><strong>{{ Money::short($k['service_ytd']) }}</strong><small>Riwayat servis →</small></a>
    </div>

    <div class="rac-grid">
        <section class="rac-panel">
            <h3>Agenda ruang rapat hari ini <small><a href="{{ $links['schedule'] }}">jadwal mingguan →</a></small></h3>
            <div class="rac-list">
                @forelse ($d['rooms_today'] as $b)
                    <a href="{{ $links['rooms'] }}" class="rac-list-row">
                        <span class="time">{{ $b->starts_at->format('H:i') }}<small>{{ $b->ends_at->format('H:i') }}</small></span>
                        <span class="body"><b>{{ $b->title }}</b><small>{{ $b->room?->name }} · {{ $b->attendees }} org · {{ $b->organizer?->name }}</small></span>
                        <span class="rac-chip {{ $b->state->value }}">{{ $b->state->getLabel() }}</span>
                    </a>
                @empty
                    <p class="rac-muted">Tidak ada rapat terjadwal hari ini.</p>
                @endforelse
            </div>
        </section>

        <section class="rac-panel">
            <h3>Perjalanan kendaraan hari ini <small><a href="{{ $links['trips'] }}">semua pemesanan →</a></small></h3>
            <div class="rac-list">
                @forelse ($d['trips_today'] as $t)
                    <a href="{{ $links['trips'] }}" class="rac-list-row">
                        <span class="time">{{ $t->starts_at->format('H:i') }}<small>{{ $t->ends_at->isSameDay($t->starts_at) ? $t->ends_at->format('H:i') : $t->ends_at->format('d/m') }}</small></span>
                        <span class="body"><b>{{ $t->destination }}</b><small>{{ $t->vehicle?->plate_number ?? 'Kendaraan belum ditetapkan' }} · {{ $t->requester?->name }}</small></span>
                        <span class="rac-chip {{ $t->state->value }}">{{ $t->state->getLabel() }}</span>
                    </a>
                @empty
                    <p class="rac-muted">Tidak ada perjalanan dinas hari ini.</p>
                @endforelse
            </div>
        </section>

        <section class="rac-panel">
            <h3>Jatuh tempo <small>dokumen &amp; kendaraan</small></h3>
            <div class="rac-list">
                @forelse ($d['deadlines'] as $row)
                    <a href="{{ $row['kind'] === 'vehicle' ? $links['vehicle']($row['id']) : $links['documents'] }}" class="rac-list-row">
                        <span class="rac-days {{ $row['days'] < 0 ? 'late' : ($row['days'] <= 14 ? 'soon' : '') }}">
                            {{ $row['days'] < 0 ? 'Lewat' : 'H-' }}<b>{{ abs($row['days']) }}</b>
                        </span>
                        <span class="body"><b>{{ $row['title'] }}</b><small>{{ $row['subtitle'] }} · {{ $row['date']->format('d/m/Y') }}</small></span>
                    </a>
                @empty
                    <p class="rac-muted">Tidak ada dokumen atau kewajiban kendaraan yang jatuh tempo.</p>
                @endforelse
            </div>
        </section>

        <section class="rac-panel">
            <h3>Stok menipis <small><a href="{{ $links['movements'] }}">mutasi stok →</a></small></h3>
            <div class="rac-bars">
                @forelse ($d['low_stock_items'] as $item)
                    <a class="rac-bar-row" href="{{ $links['low_stock'] }}" title="Minimum {{ Domain::quantity($item->min_stock) }} {{ $item->unit }}">
                        <span class="label">{{ $item->name }}</span>
                        <span class="track"><span class="fill {{ $item->stock <= $item->min_stock / 2 ? 'bad' : 'mid' }}" style="width: {{ min(100, $item->min_stock > 0 ? $item->stock / $item->min_stock * 100 : 100) }}%"></span></span>
                        <b>{{ Domain::quantity($item->stock) }} {{ $item->unit }}</b>
                    </a>
                @empty
                    <p class="rac-muted">Semua stok di atas batas minimum.</p>
                @endforelse
            </div>
        </section>

        <section class="rac-panel wide">
            <h3>Permintaan barang belum diserahkan <small><a href="{{ $links['requests'] }}">semua permintaan →</a></small></h3>
            <div class="rac-table-wrap">
            <table>
                <thead><tr><th>Nomor</th><th>Pemohon</th><th>Barang</th><th>Dibutuhkan</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($d['open_requests'] as $r)
                        <tr onclick="window.location='{{ $links['request']($r->id) }}'">
                            <td><a href="{{ $links['request']($r->id) }}">{{ $r->number }}</a></td>
                            <td>{{ $r->requester?->name }}<div class="rac-muted">{{ $r->department?->name }}</div></td>
                            <td>{{ $r->lines->map(fn ($l) => $l->item?->name.' × '.Domain::quantity($l->quantity))->join(', ') }}</td>
                            <td>{{ $r->needed_by?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="rac-chip {{ $r->state->value }}">{{ $r->state->getLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="rac-muted">Tidak ada permintaan yang menunggu.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </section>
    </div>
</div>
</x-filament-panels::page>
