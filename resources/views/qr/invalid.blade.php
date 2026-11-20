<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Label tidak berlaku · {{ \App\Support\Brand::panelName() }}</title>
<link rel="icon" href="{{ asset('favicon.png') }}">
<style>
    body { margin:0; font-family: Inter, system-ui, sans-serif; background:#f5f1e8; color:#1c1917; display:flex; min-height:100vh; align-items:center; justify-content:center; padding:1rem; }
    .card { max-width:26rem; background:#fffdf8; border:1px solid #e7dfcf; border-radius:1rem; padding:1.6rem; text-align:center; box-shadow:0 6px 24px rgba(0,0,0,.06); }
    h1 { font-size:1.15rem; margin:.8rem 0 .4rem; color:#A8432A; }
    p { color:#57534e; font-size:.92rem; line-height:1.5; }
    a { display:inline-block; margin-top:.8rem; padding:.6rem 1rem; border-radius:.5rem; background:#006632; color:#fff; text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="card">
    <img src="{{ \App\Support\Brand::logoUrl() }}" alt="" style="height:44px">
    <h1>Label tidak dapat digunakan</h1>
    <p>{{ $message ?? 'Label ini sudah tidak berlaku.' }}</p>
    <p>Bila ada kerusakan, laporkan melalui aplikasi atau hubungi Engineering.</p>
    <a href="{{ url('/admin') }}">Buka Asset Care</a>
</div>
</body>
</html>
