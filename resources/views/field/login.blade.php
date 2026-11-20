@extends('field.layout')
@section('title', 'Masuk')
@section('heading', 'Identifikasi Aset')
@section('sub', \App\Support\Brand::companyName())
@section('content')
<div style="text-align:center;margin:18px 0 10px"><img src="{{ \App\Support\Brand::logoUrl() }}" alt="" style="max-height:70px;max-width:70%"></div>
<div class="card">
    <p class="muted" style="margin-top:0">Menu khusus petugas identifikasi aset di lapangan. Masuk dengan akun yang diberikan Asset Controller.</p>
    <form method="post" action="{{ route('field.login.post') }}">
        @csrf
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        <label for="password">Kata sandi</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        @error('email')<div class="err-t">{{ $message }}</div>@enderror
        <label style="font-weight:400;display:flex;gap:8px;align-items:center"><input type="checkbox" name="remember" value="1" checked> Tetap masuk di ponsel ini</label>
        <button class="btn" style="margin-top:14px">Masuk</button>
    </form>
</div>
@endsection
