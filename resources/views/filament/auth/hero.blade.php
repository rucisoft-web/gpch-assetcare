{{-- Panel merek di sisi kiri halaman login (hanya untuk tamu). --}}
<aside class="rac-auth-hero" aria-hidden="true">
    <div class="rac-auth-brand">
        <img src="{{ asset('favicon.svg') }}" alt="">
        <span>Ruci Asset Care</span>
    </div>

    <div class="rac-auth-copy">
        <p class="rac-auth-eyebrow">{{ $eyebrow ?? 'Manajemen Aset Terpadu' }}</p>
        <h2>Aset terawat,<br>operasional tenang.</h2>
        <ul>
            <li>Registrasi aset dan label QR</li>
            <li>Inspeksi, permintaan, dan perintah kerja</li>
            <li>Risiko, CAPEX, dan dasbor eksekutif</li>
        </ul>
    </div>

    <p class="rac-auth-foot">&copy; {{ date('Y') }} Rucisoft</p>
    <div class="rac-auth-stripes"></div>
</aside>
