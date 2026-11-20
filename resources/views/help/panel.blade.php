<div class="ac-help-body">
    <h2 class="ac-help-title">{{ $h['title'] }}</h2>
    @if ($h['konsep'])
        <section><h3>Konsep halaman</h3>@foreach ($h['konsep'] as $p)<p>{{ $p }}</p>@endforeach</section>
    @endif
    @if ($h['shots'])
        <section class="ac-help-shots">
            @foreach ($h['shots'] as [$file, $cap])
                <figure><a href="{{ asset('help/'.$file) }}" target="_blank" rel="noopener"><img src="{{ asset('help/'.$file) }}" alt="{{ $cap }}" loading="lazy"></a><figcaption>{{ $cap }} · klik untuk memperbesar</figcaption></figure>
            @endforeach
        </section>
    @endif
    @if ($h['langkah'])
        <section><h3>Cara pengisian / pemakaian</h3><ol>@foreach ($h['langkah'] as $s)<li>{{ $s }}</li>@endforeach</ol></section>
    @endif
    @if ($h['sections'])
        <section><h3>Penjelasan per field</h3>
            @foreach ($h['sections'] as $sec => $fields)
                <div class="ac-help-sec">{{ $sec }}</div>
                <dl class="ac-help-fields">
                    @foreach ($fields as $f)
                        <div>
                            <dt>{{ $f['label'] ?: $f['name'] }} @if ($f['required'])<span class="ac-help-req">wajib</span>@endif</dt>
                            <dd>
                                @if ($f['desc']){{ $f['desc'] }}@elseif ($f['help']){{ $f['help'] }}@else<span class="arah-muted">Isi sesuai label.</span>@endif
                                @if ($f['desc'] && $f['help'] && $f['help'] !== $f['desc'])<div class="arah-muted">{{ $f['help'] }}</div>@endif
                                @if ($f['options'])<div class="ac-help-opts">Pilihan: {{ implode(' · ', $f['options']) }}</div>@endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endforeach
        </section>
    @endif
    @if ($h['tips'])
        <section><h3>Tips</h3><ul>@foreach ($h['tips'] as $t)<li>{{ $t }}</li>@endforeach</ul></section>
    @endif
    <p class="ac-help-foot">Lihat juga <a href="{{ \App\Filament\Pages\PanduanImplementasi::getUrl() }}">Panduan implementasi</a> untuk urutan penerapan dari data awal.</p>
</div>
