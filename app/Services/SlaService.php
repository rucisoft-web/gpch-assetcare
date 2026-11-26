<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Support\CurrentProperty;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Target SLA (bagian 9). P1/P2 kalender 24 jam; P3/P4 kalender kerja terkonfigurasi.
 * Timer mulai saat laporan diterima server. Hold hanya menghentikan timer pemulihan (bukan respons pertama).
 */
class SlaService
{
    public const DEFAULTS = [
        'P1' => ['P1 Darurat', 'Bahaya listrik, dugaan kebocoran gas, kegagalan utilitas kritis', 5, null, '24h'],
        'P2' => ['P2 Mendesak', 'Kamar tidak dapat dijual, chiller gagal, gangguan acara aktif', 15, 120, '24h'],
        'P3' => ['P3 Normal', 'Kerusakan dengan layanan alternatif yang aman', 120, 1440, 'business'],
        'P4' => ['P4 Terencana', 'Kosmetik atau penyempurnaan tanpa gangguan layanan', 540, 10080, 'business'],
    ];

    public function policy(string $priority, ?int $propertyId = null): array
    {
        $propertyId ??= app(CurrentProperty::class)->id();
        $p = SlaPolicy::withoutGlobalScopes()->where('property_id', $propertyId)->where('priority', $priority)->first();
        $d = self::DEFAULTS[$priority] ?? self::DEFAULTS['P3'];

        return [
            'response' => $p?->response_minutes ?? $d[2],
            'recovery' => $p ? $p->recovery_minutes : $d[3],
            'calendar' => $p?->calendar ?? $d[4],
        ];
    }

    /** @return array{response_due_at: ?CarbonInterface, due_at: ?CarbonInterface, calendar: string} */
    public function targets(string $priority, CarbonInterface $receivedAt, ?int $propertyId = null): array
    {
        $p = $this->policy($priority, $propertyId);

        return [
            'response_due_at' => $this->addMinutes($receivedAt, $p['response'], $p['calendar']),
            'due_at' => $p['recovery'] ? $this->addMinutes($receivedAt, $p['recovery'], $p['calendar']) : null,
            'calendar' => $p['calendar'],
        ];
    }

    public function addMinutes(CarbonInterface $start, int $minutes, string $calendar): CarbonInterface
    {
        if ($calendar !== 'business') {
            return $start->copy()->addMinutes($minutes);
        }
        $tz = config('assetcare.display_timezone');
        $cfg = config('assetcare.business_calendar');
        $t = CarbonImmutable::instance($start)->timezone($tz);
        $left = $minutes;
        for ($guard = 0; $guard < 400 && $left > 0; $guard++) {
            [$open, $close] = $this->window($t, $cfg);
            if (! $open || $t->greaterThanOrEqualTo($close)) {
                $t = $t->addDay()->setTimeFromTimeString('00:00');

                continue;
            }
            if ($t->lessThan($open)) {
                $t = $open;
            }
            $avail = (int) $t->diffInMinutes($close);
            if ($avail >= $left) {
                return $t->addMinutes($left)->utc();
            }
            $left -= $avail;
            $t = $t->addDay()->setTimeFromTimeString('00:00');
        }

        return $t->utc();
    }

    /** Menit kerja di antara dua waktu (untuk durasi kalender kerja). */
    public function elapsed(CarbonInterface $from, CarbonInterface $to, string $calendar): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }
        if ($calendar !== 'business') {
            return (int) $from->diffInMinutes($to);
        }
        $tz = config('assetcare.display_timezone');
        $cfg = config('assetcare.business_calendar');
        $t = CarbonImmutable::instance($from)->timezone($tz);
        $end = CarbonImmutable::instance($to)->timezone($tz);
        $sum = 0;
        for ($guard = 0; $guard < 800 && $t->lessThan($end); $guard++) {
            [$open, $close] = $this->window($t, $cfg);
            if ($open) {
                $s = $t->greaterThan($open) ? $t : $open;
                $e = $end->lessThan($close) ? $end : $close;
                if ($e->greaterThan($s)) {
                    $sum += (int) $s->diffInMinutes($e);
                }
            }
            $t = $t->addDay()->setTimeFromTimeString('00:00');
        }

        return $sum;
    }

    /** Persentase target terpakai (null bila tanpa target). */
    public function percentUsed(?CarbonInterface $start, ?CarbonInterface $due, string $calendar, int $pausedMinutes = 0, ?CarbonInterface $at = null): ?float
    {
        if (! $start || ! $due) {
            return null;
        }
        $total = $this->elapsed($start, $due, $calendar);
        if ($total <= 0) {
            return 100.0;
        }

        return round(max(0, $this->elapsed($start, $at ?? now(), $calendar) - $pausedMinutes) / $total * 100, 1);
    }

    protected function window(CarbonImmutable $day, array $cfg): array
    {
        $isHoliday = in_array($day->toDateString(), $cfg['holidays'] ?? [], true);
        if ($isHoliday || ! in_array($day->dayOfWeekIso, $cfg['days'], true)) {
            return [null, null];
        }

        return [$day->setTimeFromTimeString($cfg['start']), $day->setTimeFromTimeString($cfg['end'])];
    }
}
