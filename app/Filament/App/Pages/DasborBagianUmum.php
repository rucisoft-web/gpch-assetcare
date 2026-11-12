<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Resources\LegalDocuments\LegalDocumentResource;
use App\Filament\App\Resources\RoomBookings\RoomBookingResource;
use App\Filament\App\Resources\StockMovements\StockMovementResource;
use App\Filament\App\Resources\SupplyItems\SupplyItemResource;
use App\Filament\App\Resources\SupplyRequests\SupplyRequestResource;
use App\Filament\App\Resources\VehicleBookings\VehicleBookingResource;
use App\Filament\App\Resources\Vehicles\VehicleResource;
use App\Services\GeneralAffairsDashboard;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Ringkasan harian bagian umum: rapat, kendaraan, persediaan, dan jatuh tempo. */
class DasborBagianUmum extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Layanan Umum';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Dasbor Bagian Umum';

    protected static ?string $title = 'Dasbor Bagian Umum';

    protected static ?string $slug = 'dasbor-bagian-umum';

    protected string $view = 'filament.pages.dasbor-bagian-umum';

    public function getHeading(): string
    {
        return '';
    }

    public function getViewData(): array
    {
        return [
            'd' => app(GeneralAffairsDashboard::class)->data(),
            'company' => filament()->getTenant(),
            'links' => [
                'rooms' => RoomBookingResource::getUrl('index', ['activeTab' => 'hari_ini']),
                'schedule' => JadwalRuangRapat::getUrl(),
                'pending_rooms' => RoomBookingResource::getUrl('index', ['activeTab' => 'menunggu']),
                'trips' => VehicleBookingResource::getUrl('index'),
                'vehicles' => VehicleResource::getUrl('index'),
                'vehicle' => fn ($id) => VehicleResource::getUrl('view', ['record' => $id]),
                'low_stock' => SupplyItemResource::getUrl('index', ['activeTab' => 'menipis']),
                'stock' => SupplyItemResource::getUrl('index'),
                'movements' => StockMovementResource::getUrl('index'),
                'requests' => SupplyRequestResource::getUrl('index'),
                'request' => fn ($id) => SupplyRequestResource::getUrl('view', ['record' => $id]),
                'documents' => LegalDocumentResource::getUrl('index'),
            ],
        ];
    }
}
