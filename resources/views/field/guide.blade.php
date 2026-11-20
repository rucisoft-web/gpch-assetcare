@extends('field.layout')
@section('title', 'Panduan')
@section('heading', 'Panduan petugas')
@section('back', route('field.password'))
@section('content')
<div class="card"><p class="muted" style="margin:0">{{ $g['ringkas'] }}</p></div>
@foreach ($g['tugas'] as $i => [$judul, $langkah, $img, $dev])
<div class="card">
    <b style="font-size:17px">{{ $i + 1 }}. {{ $judul }}</b>
    <ol style="padding-left:20px;margin:8px 0 12px">@foreach ($langkah as $l)<li style="margin-bottom:4px">{{ $l }}</li>@endforeach</ol>
    <a href="{{ \App\Support\Help\RoleGuide::image($img) }}" target="_blank"><img src="{{ \App\Support\Help\RoleGuide::image($img) }}" alt="{{ $judul }}" loading="lazy" style="display:block;width:70%;margin:0 auto;border:5px solid #222;border-radius:18px;max-height:480px;object-fit:cover;object-position:top"></a>
</div>
@endforeach
@endsection
