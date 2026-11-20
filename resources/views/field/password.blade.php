@extends('field.layout')
@section('title', 'Akun')
@section('heading', 'Akun')
@section('sub', auth()->user()->name)
@section('content')
@if (auth()->user()->must_change_password)
<div class="flash err">Ganti kata sandi Anda dulu sebelum mulai bekerja.</div>
@endif
<div class="card">
    <dl class="kv"><dt>Nama</dt><dd>{{ auth()->user()->name }}</dd><dt>E-mail</dt><dd>{{ auth()->user()->email }}</dd></dl>
</div>
<div class="card">
    <b>Ganti kata sandi</b>
    <form method="post" action="{{ route('field.password.save') }}">
        @csrf
        <label>Kata sandi lama</label><input type="password" name="current" autocomplete="current-password" required>
        @error('current')<div class="err-t">{{ $message }}</div>@enderror
        <label>Kata sandi baru (min. 10 karakter, huruf & angka)</label><input type="password" name="password" autocomplete="new-password" required>
        <label>Ulangi kata sandi baru</label><input type="password" name="password_confirmation" autocomplete="new-password" required>
        @error('password')<div class="err-t">{{ $message }}</div>@enderror
        <button class="btn" style="margin-top:14px">Simpan kata sandi</button>
    </form>
</div>
<a class="big" href="{{ route('field.guide') }}"><span class="ic">?</span><span><b>Panduan petugas</b><span class="muted">Langkah pasang label & identifikasi, dengan gambar</span></span></a>
<form method="post" action="{{ route('field.logout') }}">@csrf<button class="btn alt">Keluar</button></form>
@endsection
