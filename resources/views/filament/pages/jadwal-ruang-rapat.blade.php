<x-filament-panels::page>
<div class="rac-dash">
    <p class="rac-muted">Minggu {{ $range }} · <span class="rac-chip approved">Disetujui</span> <span class="rac-chip requested">Menunggu persetujuan</span></p>

    @if ($rooms->isEmpty())
        <div class="rac-panel"><p class="rac-muted">Belum ada ruang rapat. Manajer dapat menambahkannya di Konfigurasi › Ruang Rapat.</p></div>
    @else
        <div class="rac-panel rac-schedule-wrap">
            <table class="rac-schedule">
                <thead>
                    <tr>
                        <th>Ruang</th>
                        @foreach ($days as $day)
                            <th @class(['today' => $day->isToday()])>{{ $day->translatedFormat('D') }}<small>{{ $day->format('d/m') }}</small></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rooms as $room)
                        <tr>
                            <th>{{ $room->name }}<small>{{ $room->capacity }} orang</small></th>
                            @foreach ($days as $day)
                                <td @class(['today' => $day->isToday()])>
                                    @foreach ($bookings->get($room->id.'|'.$day->toDateString(), []) as $booking)
                                        <a class="rac-slot {{ $booking->state->value }}" href="{{ $listUrl }}?tableSearch={{ urlencode($booking->number) }}&activeTab=semua" title="{{ $booking->title }} · {{ $booking->organizer?->name }}">
                                            <b>{{ $booking->starts_at->format('H:i') }}–{{ $booking->ends_at->format('H:i') }}</b>
                                            <span>{{ $booking->title }}</span>
                                        </a>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
</x-filament-panels::page>
