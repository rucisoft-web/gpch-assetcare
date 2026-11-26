@php use App\Support\Fmt; @endphp
<x-filament-panels::page>
<div class="arah ac-exec">
    <div class="arah-muted" style="margin-bottom:.6rem">Data per {{ Fmt::dt($d['generated'], 'd M Y H:i') }} WIB. Klik kartu, irisan, batang, atau titik grafik untuk membuka data rinci.</div>
    <div class="ac-exec-kpi">
        @foreach ($d['kpi'] as $k)
            <a href="{{ $k['url'] }}" class="ac-exec-tile ac-tone-{{ $k['tone'] }}">
                <span class="label">{{ $k['label'] }}</span>
                <span class="value">{{ is_numeric($k['value']) ? Fmt::num($k['value']) : $k['value'] }}</span>
                <span class="sub">{{ $k['sub'] }}</span>
            </a>
        @endforeach
    </div>
    <div class="ac-exec-grid">
        @foreach ($d['charts'] as $c)
            <div class="arah-card ac-exec-chart {{ in_array($c['id'], ['tren', 'verifikasi', 'biaya', 'kategori', 'lokasi', 'sering'], true) ? 'wide' : '' }}">
                <h3>{{ $c['title'] }}</h3>
                @if (array_sum(array_map(fn ($ds) => array_sum($ds['data']), $c['datasets'])) == 0)
                    <div class="arah-empty">Belum ada data.</div>
                @else
                    <div class="ac-exec-canvas" style="height: {{ in_array($c['id'], ['kategori', 'lokasi', 'sering', 'verifikasi'], true) ? max(220, count($c['labels']) * 28 + 60) : 260 }}px">
                        <canvas data-chart='@json($c)'></canvas>
                    </div>
                @endif
                <div class="arah-muted ac-exec-hint">{{ $c['hint'] }}</div>
            </div>
        @endforeach
    </div>
</div>
<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
<script>
(function () {
    const fmtRp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    function draw() {
        if (!window.Chart) return;
        const dark = document.documentElement.classList.contains('dark');
        Chart.defaults.color = dark ? '#d6cfc4' : '#6E5F52';
        Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
        document.querySelectorAll('.ac-exec canvas[data-chart]').forEach(cv => {
            if (cv._chart) cv._chart.destroy();
            const c = JSON.parse(cv.dataset.chart);
            const o = c.opts || {};
            const round = c.type === 'doughnut';
            cv._chart = new Chart(cv, {
                type: c.type,
                data: { labels: c.labels, datasets: c.datasets },
                options: {
                    responsive: true, maintainAspectRatio: false, indexAxis: o.indexAxis || 'x',
                    cutout: round ? '58%' : undefined,
                    plugins: {
                        legend: { display: round || c.datasets.length > 1, position: round ? 'right' : 'top', labels: { boxWidth: 12 } },
                        tooltip: { callbacks: { label: ctx => (ctx.dataset.label ? ctx.dataset.label + ': ' : (round ? ctx.label + ': ' : '')) + (o.money ? fmtRp(ctx.raw) : Number(ctx.raw).toLocaleString('id-ID')) } },
                    },
                    scales: round ? {} : {
                        x: { stacked: !!o.stacked, grid: { display: o.indexAxis !== 'y' ? false : true }, ticks: o.money && o.indexAxis !== 'y' ? {} : { precision: 0 } },
                        y: { stacked: !!o.stacked, beginAtZero: true, ticks: { precision: 0, callback: function (v) { const l = this.getLabelForValue ? this.getLabelForValue(v) : v; return o.money ? (v / 1e6).toLocaleString('id-ID') + ' jt' : (typeof l === 'string' && l.length > 34 ? l.slice(0, 33) + '…' : l); } } },
                    },
                    onHover: (e, els) => { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
                    onClick: (e, els) => {
                        if (!els.length) return;
                        const el = els[0];
                        const url = c.urls.byDataset ? c.urls.byDataset[el.datasetIndex][el.index] : c.urls[el.index];
                        if (url) window.location.href = url;
                    },
                },
            });
        });
    }
    if (document.readyState !== 'loading') draw(); else document.addEventListener('DOMContentLoaded', draw);
    document.addEventListener('livewire:navigated', draw);
})();
</script>
</x-filament-panels::page>
