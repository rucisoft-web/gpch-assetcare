@extends('field.layout')
@section('title', 'Scan QR')
@section('heading', 'Scan label QR')
@section('content')
@include('field._scanner')
<form class="card" method="get" action="{{ route('field.resolve') }}">
    <label style="margin-top:0">Atau ketik kode aset</label>
    <input type="text" name="kode" placeholder="MB-08-819-TV-01" autocapitalize="characters">
    <button class="btn" style="margin-top:10px">Buka</button>
</form>
@endsection
@push('scripts')
<script>window.fieldOnScan = (t) => { location.href = @json(route('field.resolve')) + '?kode=' + encodeURIComponent(t); };</script>
@endpush
