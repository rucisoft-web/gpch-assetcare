<x-filament-panels::page>
<div class="arah ac-guide">
    <div class="arah-card">
        <div class="ac-guide-head">
            <div>
                <h2 style="font-family:var(--serif);font-size:1.4rem;color:var(--k-marun)">Langkah penerapan Asset Care</h2>
                <p class="arah-muted">Mulai dari data awal sampai pemantauan pimpinan. Status dihitung dari data aplikasi saat ini. Tombol <b>Bantuan</b> di pojok kanan atas setiap halaman menjelaskan konsep dan cara mengisi field.</p>
            </div>
            <div class="ac-guide-prog"><span>{{ $done }}/{{ $total }}</span><small>langkah memenuhi syarat</small>
                <div class="ac-guide-bar"><i style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></i></div></div>
        </div>
    </div>
    @foreach ($phases as $pi => [$title, $intro, $steps])
        <div class="arah-card ac-guide-phase">
            <h3>{{ $title }}</h3>
            <p class="arah-muted">{{ $intro }}</p>
            <ol class="ac-guide-steps">
                @foreach ($steps as $si => [$name, $desc, $status, $metric, $url, $helpKey])
                    <li class="st-{{ $status }}">
                        <span class="ac-guide-dot">{{ $status === 'selesai' ? '✓' : $si + 1 }}</span>
                        <div class="ac-guide-txt">
                            <div class="ac-guide-name">{{ $name }} <span class="ac-guide-badge">{{ ['selesai' => 'Memenuhi syarat', 'berjalan' => 'Berjalan', 'belum' => 'Belum'][$status] }}</span></div>
                            <div>{{ $desc }}</div>
                            <div class="arah-muted" style="font-size:.78rem">{{ $metric }}</div>
                        </div>
                        <div class="ac-guide-act">
                            @if ($url)<a href="{{ $url }}" class="arah-btn">Buka</a>@endif
                            <a href="{{ route('help.show', $helpKey) }}" target="_blank" class="arah-btn" title="Panduan halaman ini">?</a>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endforeach
</div>
</x-filament-panels::page>
