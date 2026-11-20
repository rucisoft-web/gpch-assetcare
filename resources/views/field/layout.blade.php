<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="{{ \App\Support\Brand::color() }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ \App\Support\Brand::faviconUrl() }}">
<title>@yield('title', 'Lapangan') · {{ \App\Support\Brand::panelName() }}</title>
<style>
:root{--hijau:{{ \App\Support\Brand::color() }};--emas:#B8862B;--kertas:#FBF8F1;--tinta:#2B2620;--abu:#6F665B;--garis:#E6DFD0;--merah:#A8432A;--oranye:#C08A1E;--ok:#2F7A3F}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}
body{margin:0;font:16px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--kertas);color:var(--tinta);padding-bottom:calc(76px + env(safe-area-inset-bottom))}
a{color:var(--hijau);text-decoration:none}
.top{position:sticky;top:0;z-index:5;background:var(--hijau);color:#fff;padding:10px 16px calc(10px);display:flex;align-items:center;gap:10px;box-shadow:0 2px 6px rgba(0,0,0,.12)}
.top .back{color:#fff;font-size:22px;line-height:1;padding:4px 8px 4px 0}
.top h1{font-size:17px;margin:0;font-weight:600;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.top small{display:block;font-weight:400;opacity:.85;font-size:12px}
.wrap{padding:14px 16px;max-width:640px;margin:0 auto}
.card{background:#fff;border:1px solid var(--garis);border-radius:14px;padding:14px;margin-bottom:12px}
.muted{color:var(--abu);font-size:14px}
.big{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--garis);border-radius:14px;padding:16px;margin-bottom:10px;color:var(--tinta)}
.big .ic{width:46px;height:46px;border-radius:12px;background:var(--hijau);color:#fff;display:grid;place-items:center;font-size:22px;flex:none}
.big b{display:block;font-size:17px}
.row{display:flex;align-items:center;gap:12px;padding:12px 0;border-top:1px solid var(--garis);color:var(--tinta)}
.row:first-child{border-top:0}
.row .t{flex:1;min-width:0}.row .t b{display:block;font-weight:600;overflow:hidden;text-overflow:ellipsis}
.row .chev{color:var(--abu);font-size:20px}
.pill{display:inline-block;font-size:12px;font-weight:600;border-radius:999px;padding:2px 9px;white-space:nowrap}
.p-ok{background:#E3F1E4;color:var(--ok)}.p-no{background:#F4ECDC;color:#8A6416}.p-bad{background:#F6E1DA;color:var(--merah)}
.bar{height:8px;background:#EEE7D8;border-radius:9px;overflow:hidden;margin-top:6px}.bar i{display:block;height:100%;background:var(--ok)}
.btn{display:block;width:100%;border:0;border-radius:12px;padding:14px;font-size:16px;font-weight:600;background:var(--hijau);color:#fff;text-align:center;cursor:pointer}
.btn.alt{background:#fff;color:var(--hijau);border:1.5px solid var(--hijau)}
.btn.warn{background:var(--merah)}
label{display:block;font-weight:600;font-size:14px;margin:12px 0 6px}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=search],select,textarea{width:100%;font-size:16px;padding:12px;border:1px solid #D6CCB8;border-radius:10px;background:#fff;color:var(--tinta)}
textarea{min-height:80px}
.seg{display:grid;grid-template-columns:repeat(5,1fr);gap:6px}
.seg.two{grid-template-columns:1fr 1fr}
.seg input{display:none}.seg label{margin:0;text-align:center;border:1.5px solid #D6CCB8;border-radius:10px;padding:11px 4px;font-weight:600;background:#fff}
.seg input:checked+label{background:var(--hijau);border-color:var(--hijau);color:#fff}
.seg.two input[value="0"]:checked+label{background:var(--merah);border-color:var(--merah)}
.flash{border-radius:12px;padding:12px 14px;margin-bottom:12px;font-size:15px}
.flash.ok{background:#E3F1E4;color:#1F5A2B}.flash.err{background:#F6E1DA;color:#7C2E1B}
.err-t{color:var(--merah);font-size:13px;margin-top:4px}
.kv{display:grid;grid-template-columns:auto 1fr;gap:4px 12px;font-size:14px}.kv dt{color:var(--abu)}.kv dd{margin:0;font-weight:500}
.photo{width:100%;border-radius:12px;display:block;margin-bottom:10px;max-height:300px;object-fit:cover;background:#eee}
.tabs{display:flex;gap:8px;margin-bottom:10px}.tabs a{flex:1;text-align:center;padding:8px;border-radius:10px;border:1px solid var(--garis);background:#fff;color:var(--tinta);font-size:14px}.tabs a.on{background:var(--hijau);color:#fff;border-color:var(--hijau)}
nav.bottom{position:fixed;left:0;right:0;bottom:0;z-index:6;background:#fff;border-top:1px solid var(--garis);display:grid;grid-template-columns:repeat(5,1fr);padding:6px 4px calc(6px + env(safe-area-inset-bottom))}
nav.bottom a{display:flex;flex-direction:column;align-items:center;gap:2px;font-size:11px;color:var(--abu);padding:4px 0}
nav.bottom a svg{width:24px;height:24px}
nav.bottom a.on{color:var(--hijau);font-weight:700}
nav.bottom a.scan .ring{width:50px;height:50px;margin-top:-22px;border-radius:50%;background:var(--hijau);color:#fff;display:grid;place-items:center;box-shadow:0 3px 10px rgba(0,0,0,.2)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.stat b{font-size:24px;display:block}
</style>
@stack('head')
</head>
<body>
<header class="top">
    @hasSection('back')<a class="back" href="@yield('back')" aria-label="Kembali">‹</a>@endif
    <h1>@yield('heading', 'Identifikasi Aset')@hasSection('sub')<small>@yield('sub')</small>@endif</h1>
</header>
<main class="wrap">
    @if (session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
    @if (session('err'))<div class="flash err">{{ session('err') }}</div>@endif
    @yield('content')
</main>
@auth
@php $r = request()->route()?->getName(); @endphp
<nav class="bottom">
    <a href="{{ route('field.home') }}" class="{{ $r === 'field.home' ? 'on' : '' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>Beranda</a>
    <a href="{{ route('field.location') }}" class="{{ in_array($r, ['field.location', 'field.new']) ? 'on' : '' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>Lokasi</a>
    <a href="{{ route('field.scan') }}" class="scan {{ $r === 'field.scan' ? 'on' : '' }}"><span class="ring"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/></svg></span>Scan</a>
    <a href="{{ route('field.search') }}" class="{{ $r === 'field.search' ? 'on' : '' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4-4"/></svg>Cari</a>
    <a href="{{ route('field.password') }}" class="{{ $r === 'field.password' ? 'on' : '' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>Akun</a>
</nav>
@endauth
@stack('scripts')
</body>
</html>
