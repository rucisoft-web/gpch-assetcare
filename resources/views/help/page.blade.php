<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bantuan · {{ $h['title'] }} · Asset Care</title>
<link rel="stylesheet" href="{{ asset('css/assetcare.css') }}">
<style>body{margin:0;background:#FAF6EC;color:#2A1A14;font-family:Inter,system-ui,sans-serif}.wrap{max-width:820px;margin:0 auto;padding:1rem}.top{display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid #E7DDCB}.top a{color:#006632;font-weight:600;text-decoration:none}</style>
</head>
<body><div class="wrap">
<div class="top"><span style="font-weight:700;color:#006632">Bantuan Asset Care</span>@if ($h['url'])<a href="{{ $h['url'] }}">Buka halaman {{ $h['title'] }} →</a>@endif</div>
@include('help.panel')
</div></body></html>
