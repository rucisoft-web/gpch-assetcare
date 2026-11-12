<?php

namespace App\Services;

use App\Enums\RoomBookingState;
use App\Enums\VehicleBookingState;
use App\Enums\VehicleStatus;
use App\Models\LegalDocument;
use App\Models\RoomBooking;
use App\Models\SupplyItem;
use App\Models\SupplyRequest;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Models\VehicleFuelLog;
use App\Models\VehicleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Angka dan daftar untuk Dasbor Bagian Umum. Semua query sudah dibatasi ke perusahaan aktif. */
class GeneralAffairsDashboard
{
    public function data(): array
    {
        $today = CarbonImmutable::today();
        $activeRoom = [RoomBookingState::Requested->value, RoomBookingState::Approved->value];

        $roomsToday = RoomBooking::query()->with(['room', 'organizer'])
            ->whereIn('state', $activeRoom)
            ->where('starts_at', '<', $today->addDay())->where('ends_at', '>', $today)
            ->orderBy('starts_at')->get();

        $tripsToday = VehicleBooking::query()->with(['vehicle', 'requester'])
            ->whereIn('state', VehicleBooking::OPEN_STATES)
            ->where('starts_at', '<', $today->addDay())->where('ends_at', '>', $today)
            ->orderBy('starts_at')->get();

        $vehicles = Vehicle::query()->get();
        $inUse = VehicleBooking::query()->where('state', VehicleBookingState::InUse->value)->distinct()->count('vehicle_id');

        $lowStock = SupplyItem::query()->lowStock()->orderByRaw('stock / min_stock')->limit(8)->get();

        return [
            'today' => $today,
            'kpi' => [
                'meetings_today' => $roomsToday->count(),
                'pending_approvals' => RoomBooking::where('state', RoomBookingState::Requested->value)->count()
                    + VehicleBooking::where('state', VehicleBookingState::Requested->value)->count()
                    + SupplyRequest::where('state', 'submitted')->count(),
                'vehicles_ready' => max(0, $vehicles->where('status', VehicleStatus::Available)->count() - $inUse),
                'vehicles_total' => $vehicles->count(),
                'vehicles_in_use' => $inUse,
                'low_stock' => SupplyItem::query()->lowStock()->count(),
                'stock_value' => (float) SupplyItem::query()->where('is_active', true)->sum(DB::raw('stock * unit_cost')),
                'documents_action' => LegalDocument::query()->needsAction()->count(),
                'fuel_month' => (float) VehicleFuelLog::query()->whereDate('filled_at', '>=', $today->startOfMonth())->sum('amount'),
                'service_ytd' => (float) VehicleService::query()->whereDate('service_date', '>=', $today->startOfYear())->sum('cost'),
            ],
            'rooms_today' => $roomsToday,
            'trips_today' => $tripsToday,
            'deadlines' => $this->deadlines($today, $vehicles),
            'low_stock_items' => $lowStock,
            'open_requests' => SupplyRequest::query()->open()->with(['requester', 'department', 'lines.item'])->orderBy('requested_at')->limit(6)->get(),
        ];
    }

    /** Jatuh tempo gabungan: dokumen dalam masa pengingat dan kewajiban kendaraan ≤ 30 hari, terlambat lebih dulu. */
    protected function deadlines(CarbonImmutable $today, Collection $vehicles): Collection
    {
        $documents = LegalDocument::query()->needsAction()->with('vehicle')->get()->map(fn (LegalDocument $doc) => [
            'date' => $doc->expiry_date,
            'title' => $doc->name,
            'subtitle' => $doc->document_type->getLabel().($doc->number ? ' · '.$doc->number : ''),
            'kind' => 'document',
            'id' => $doc->id,
        ]);

        $vehicleItems = $vehicles->flatMap(fn (Vehicle $vehicle) => $vehicle->dueItems($today->addDays(30))->map(fn (array $item) => [
            'date' => $item['date'],
            'title' => "{$item['label']} {$vehicle->plate_number}",
            'subtitle' => $vehicle->name,
            'kind' => 'vehicle',
            'id' => $vehicle->id,
        ]));

        return $documents->concat($vehicleItems)
            ->map(fn (array $row) => $row + ['days' => (int) $today->diffInDays($row['date'], false)])
            ->sortBy('date')
            ->values()
            ->take(12);
    }
}
