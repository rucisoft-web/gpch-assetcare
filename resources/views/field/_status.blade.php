@php $vs = $a->verification_status; @endphp
<span class="pill {{ $vs === 'verified' ? 'p-ok' : ($vs === 'discrepancy' ? 'p-bad' : 'p-no') }}">{{ $vs === 'verified' ? 'Sudah' : ($vs === 'discrepancy' ? 'Tidak ditemukan' : 'Belum') }}</span>
