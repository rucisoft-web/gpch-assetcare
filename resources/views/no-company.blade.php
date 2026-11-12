@extends('qr.layout')
@section('title', 'Belum terhubung ke perusahaan')
@section('content')
<div class="card" style="border-radius:16px;text-align:center;margin-top:40px">
    <h2>Akun belum terhubung ke perusahaan</h2>
    <p>Akun <b>{{ auth()->user()->email }}</b> sudah aktif, tetapi belum menjadi anggota perusahaan mana pun. Minta admin platform menambahkan akun ini ke perusahaan di menu Pengguna.</p>
    <form method="post" action="{{ route('filament.app.auth.logout') }}">
        @csrf
        <button type="submit" class="btn navy">Keluar</button>
    </form>
</div>
@endsection
