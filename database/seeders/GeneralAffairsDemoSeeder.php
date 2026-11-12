<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\LegalDocument;
use App\Models\MeetingRoom;
use App\Models\RoomBooking;
use App\Models\SupplyItem;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Models\VehicleFuelLog;
use App\Models\VehicleService;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh bagian umum GPCH: ruang rapat, kendaraan dinas, ATK & persediaan, dokumen perizinan.
 * Bisa dijalankan terpisah pada instalasi yang sudah berisi data contoh aset.
 */
class GeneralAffairsDemoSeeder extends Seeder
{
    protected Company $company;

    protected CarbonImmutable $today;

    /** @var array<string, User> */
    protected array $users = [];

    /** @var array<string, int|null> */
    protected array $dept = [];

    public function run(): void
    {
        mt_srand(2027);
        $this->today = CarbonImmutable::today();
        $company = Company::where('slug', 'gpch')->first();

        if (! $company) {
            $this->command?->warn('Perusahaan contoh GPCH belum ada. Jalankan DemoSeeder lebih dulu.');

            return;
        }

        if (MeetingRoom::withoutGlobalScopes()->where('company_id', $company->id)->exists()) {
            $this->command?->warn('Data contoh bagian umum GPCH sudah ada. Lewati.');

            return;
        }

        $this->company = $company;
        AuditLog::$enabled = false;

        app(CurrentCompany::class)->runAs($company, function () {
            DB::transaction(function () {
                $this->users();
                $this->rooms();
                $this->vehicles();
                $this->supplies();
                $this->documents();
            });
        });

        AuditLog::$enabled = true;
    }

    protected function rand(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    protected function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    protected function users(): void
    {
        $user = User::updateOrCreate(['email' => 'umum@gpch.demo'], [
            'name' => 'Siti Nurhasanah', 'password' => config('asset.demo.password'), 'is_active' => true,
        ]);
        $user->memberships()->updateOrCreate(['company_id' => $this->company->id], [
            'role' => Role::Staf, 'position_title' => 'Staf General Affairs', 'is_active' => true,
        ]);

        foreach (['umum@gpch.demo' => 'ga', 'manajer@gpch.demo' => 'manajer', 'teknisi@gpch.demo' => 'teknisi', 'supervisor@gpch.demo' => 'supervisor', 'gm@gpch.demo' => 'gm'] as $email => $key) {
            $this->users[$key] = User::where('email', $email)->first() ?? $user;
        }

        $this->dept = Department::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtolower($code) => $id])->all();
    }

    protected function dept(string $code): ?int
    {
        return $this->dept[$code] ?? null;
    }

    protected function rooms(): void
    {
        $rooms = collect([
            ['Boardroom Parahyangan', 20, 'Layar 86", video conference, sound system'],
            ['Ruang Rapat Priangan', 12, 'Proyektor, papan tulis'],
            ['Ruang Training Galunggung', 30, 'Proyektor, 2 mikrofon, meja kelas'],
            ['Ruang Diskusi Tangkuban', 6, 'TV 55", papan tulis'],
        ])->map(fn (array $r) => MeetingRoom::create(['name' => $r[0], 'capacity' => $r[1], 'facilities' => $r[2]]));

        $agendas = [
            ['Briefing harian kepala departemen', 'rooms'], ['Rapat koordinasi event pernikahan', 'convention'], ['Review anggaran bulanan', 'admin'],
            ['Rapat revenue management', 'front_office'], ['Training service excellence', 'fnb'], ['Koordinasi keamanan event', 'security'],
            ['Evaluasi vendor laundry', 'housekeeping'], ['Rapat proyek renovasi ballroom', 'engineering'], ['Interview kandidat staf', 'admin'],
            ['Presentasi vendor PABX', 'it'], ['Rapat menu Ramadan', 'fnb'], ['Sosialisasi K3 dan APAR', 'engineering'],
        ];
        $organizers = array_values($this->users);
        $slots = [[8, 0, 9, 0], [10, 0, 11, 30], [13, 0, 14, 30], [15, 0, 16, 30]];

        for ($offset = -10; $offset <= 12; $offset++) {
            $day = $this->today->addDays($offset);

            if ($day->isWeekend()) {
                continue;
            }

            foreach ($rooms as $room) {
                foreach ($slots as [$h1, $m1, $h2, $m2]) {
                    if ($this->rand(1, 100) > ($room->capacity >= 20 ? 45 : 35)) {
                        continue;
                    }

                    [$title, $dept] = $this->pick($agendas);
                    $state = match (true) {
                        $offset < 0 => $this->pick(['approved', 'approved', 'approved', 'approved', 'cancelled']),
                        $offset <= 1 => 'approved',
                        default => $this->pick(['approved', 'approved', 'approved', 'approved', 'approved', 'requested']),
                    };

                    RoomBooking::create([
                        'meeting_room_id' => $room->id,
                        'title' => $title,
                        'organizer_id' => $this->pick($organizers)->id,
                        'department_id' => $this->dept($dept),
                        'starts_at' => $day->setTime($h1, $m1),
                        'ends_at' => $day->setTime($h2, $m2),
                        'attendees' => max(2, (int) round($room->capacity * $this->rand(30, 90) / 100)),
                        'needs' => $this->pick([null, 'Coffee break pagi', 'Snack dan air mineral', 'Makan siang prasmanan', 'Proyektor dan pointer']),
                        'state' => $state,
                        'approved_by' => $state === 'approved' ? $this->users['manajer']->id : null,
                    ]);
                }
            }
        }

        // Satu permintaan yang ditolak karena agenda dipindah ke ruang lain.
        RoomBooking::create([
            'meeting_room_id' => $rooms[3]->id, 'title' => 'Rapat evaluasi event akhir tahun', 'organizer_id' => $this->users['supervisor']->id,
            'department_id' => $this->dept('convention'), 'starts_at' => $this->today->addDays(15)->setTime(9, 0), 'ends_at' => $this->today->addDays(15)->setTime(11, 0),
            'attendees' => 14, 'state' => 'rejected', 'approved_by' => $this->users['manajer']->id, 'note' => 'Peserta 14 orang, silakan pesan Ruang Rapat Priangan atau Boardroom.',
        ]);
    }

    protected function vehicles(): void
    {
        $d = fn (int $days) => $this->today->addDays($days);
        $vehicles = collect([
            ['D 1234 GPC', 'Toyota Hiace Commuter', 'minibus', 2019, 'Dexlite', 15, 98_450, 'Ujang Suhendar', $d(12), $d(400), $d(25), $d(140), $d(9), 100_000],
            ['D 1688 GPC', 'Toyota Innova Zenix 2.0 V', 'car', 2023, 'Pertamax', 7, 31_270, 'Dadang Sutisna', $d(95), $d(1200), null, $d(-6), $d(40), 35_000],
            ['D 1955 GPC', 'Toyota Avanza 1.5 G', 'car', 2020, 'Pertalite', 7, 76_880, null, $d(-4), $d(180), null, $d(60), $d(70), 80_000],
            ['D 8123 GP', 'Isuzu Traga Pick Up', 'pickup', 2021, 'Solar', 3, 54_120, 'Cecep Hidayat', $d(150), $d(700), $d(18), $d(210), $d(-3), 55_000],
            ['D 3456 GPC', 'Honda Vario 125', 'motorcycle', 2022, 'Pertalite', 2, 18_430, null, $d(220), $d(900), null, null, $d(45), 20_000],
        ])->map(fn (array $v) => Vehicle::create([
            'plate_number' => $v[0], 'name' => $v[1], 'type' => $v[2], 'year' => $v[3], 'fuel_type' => $v[4], 'seats' => $v[5], 'odometer' => $v[6],
            'default_driver' => $v[7], 'tax_due_date' => $v[8], 'stnk_due_date' => $v[9], 'kir_due_date' => $v[10], 'insurance_due_date' => $v[11],
            'next_service_date' => $v[12], 'next_service_km' => $v[13],
        ]));

        $prices = ['Dexlite' => 13_250, 'Pertamax' => 12_500, 'Pertalite' => 10_000, 'Solar' => 6_800];
        $tank = ['minibus' => [45, 60], 'car' => [30, 45], 'pickup' => [30, 40], 'motorcycle' => [3, 5]];
        $stations = ['SPBU 34.401.01 Jl. Peta', 'SPBU 34.402.16 Soekarno-Hatta', 'SPBU 34.401.12 Kopo', 'SPBU 34.403.05 Pasteur'];

        foreach ($vehicles as $vehicle) {
            // Pengisian BBM 6 bulan terakhir, odometer mundur dari angka sekarang.
            $km = $vehicle->odometer - $this->rand(3_000, 6_000);
            for ($date = $this->today->subMonths(6); $date->lt($this->today); $date = $date->addDays($this->rand(6, 12))) {
                $liters = $this->rand(...$tank[$vehicle->type->value]) + $this->rand(0, 9) / 10;
                $km += $this->rand(180, 520);
                VehicleFuelLog::create([
                    'vehicle_id' => $vehicle->id, 'filled_at' => $date, 'odometer' => min($km, $vehicle->odometer), 'liters' => $liters,
                    'amount' => round($liters * $prices[$vehicle->fuel_type]), 'station' => $this->pick($stations), 'recorded_by' => $this->users['ga']->id,
                ]);
            }

            // Riwayat servis; servis terakhir tidak mengubah jadwal yang sudah diisi di atas.
            foreach ([300, 170, 45] as $i => $daysAgo) {
                if ($vehicle->type->value === 'motorcycle' && $i === 0) {
                    continue;
                }
                VehicleService::create([
                    'vehicle_id' => $vehicle->id, 'service_date' => $this->today->subDays($daysAgo + $this->rand(0, 10)),
                    'odometer' => $vehicle->odometer - (int) ($daysAgo * $this->rand(25, 40)),
                    'service_type' => $i === 1 && $vehicle->type->value !== 'motorcycle' ? 'tires' : 'routine',
                    'workshop' => $vehicle->type->value === 'motorcycle' ? 'AHASS Pasirkaliki' : ($vehicle->name === 'Isuzu Traga Pick Up' ? 'Isuzu Astra Soekarno-Hatta' : 'Auto2000 Pasteur'),
                    'description' => $i === 1 ? 'Ganti 2 ban depan dan spooring balancing' : 'Servis berkala: ganti oli mesin, filter oli, cek rem dan kaki-kaki',
                    'cost' => $vehicle->type->value === 'motorcycle' ? $this->rand(9, 18) * 10_000 : $this->rand(9, 32) * 100_000,
                ]);
            }
        }

        $trips = [
            ['Antar tamu VIP ke Bandara Husein Sastranegara', 'Bandara Husein Sastranegara', 'front_office', 4],
            ['Belanja bahan pantry dan dapur', 'Pasar Induk Caringin', 'fnb', 2],
            ['Survei lokasi event outdoor klien', 'Lembang', 'convention', 5],
            ['Ambil sparepart chiller di distributor', 'Jl. Soekarno-Hatta', 'engineering', 2],
            ['Setor laporan pajak ke KPP', 'KPP Pratama Bandung Cibeunying', 'admin', 1],
            ['Kirim linen ke vendor laundry', 'Cimahi', 'housekeeping', 2],
            ['Jemput pembicara seminar', 'Stasiun Bandung', 'convention', 3],
        ];
        $requesters = array_values($this->users);
        $cars = $vehicles->where('type.value', '!=', 'motorcycle')->values();

        for ($offset = -20; $offset <= 6; $offset += $this->rand(1, 2)) {
            [$purpose, $destination, $dept, $pax] = $this->pick($trips);
            $vehicle = $cars[$this->rand(0, $cars->count() - 1)];
            $start = $this->today->addDays($offset)->setTime($this->pick([7, 8, 9, 10, 13]), 0);
            $end = $start->addHours($this->rand(2, 6));
            $state = match (true) {
                $offset < 0 => $this->pick(['returned', 'returned', 'returned', 'cancelled']),
                $offset === 0 => 'in_use',
                $offset <= 2 => 'approved',
                default => 'requested',
            };

            if (VehicleBooking::conflictFor($vehicle->id, $start, $end)) {
                continue;
            }

            $distance = in_array($state, ['returned'], true) ? $this->rand(18, 140) : null;
            $odometerStart = in_array($state, ['returned', 'in_use'], true) ? $vehicle->odometer - $this->rand(400, 2500) : null;

            VehicleBooking::create([
                'vehicle_id' => $state === 'requested' ? null : $vehicle->id,
                'requester_id' => $this->pick($requesters)->id,
                'department_id' => $this->dept($dept),
                'purpose' => $purpose, 'destination' => $destination, 'passengers' => $pax,
                'starts_at' => $start, 'ends_at' => $end,
                'driver_name' => $state === 'requested' ? null : ($vehicle->default_driver ?? 'Pengemudi pool'),
                'state' => $state,
                'odometer_start' => $odometerStart,
                'odometer_end' => $distance ? $odometerStart + $distance : null,
                'distance_km' => $distance,
                'approved_by' => in_array($state, ['returned', 'in_use', 'approved'], true) ? $this->users['manajer']->id : null,
            ]);
        }
    }

    protected function supplies(): void
    {
        // kode, nama, kategori, satuan, stok awal, minimum, harga, pemakaian per bulan
        $items = [
            ['ATK-001', 'Kertas HVS A4 70 gsm', 'stationery', 'rim', 60, 20, 52_000, 18],
            ['ATK-002', 'Kertas HVS F4 70 gsm', 'stationery', 'rim', 25, 10, 58_000, 6],
            ['ATK-003', 'Pulpen gel hitam', 'stationery', 'pcs', 120, 40, 4_500, 30],
            ['ATK-004', 'Spidol whiteboard', 'stationery', 'pcs', 36, 12, 9_000, 10],
            ['ATK-005', 'Map plastik kancing', 'stationery', 'pcs', 80, 30, 3_500, 25],
            ['ATK-006', 'Ordner / binder besar', 'stationery', 'pcs', 24, 10, 28_000, 4],
            ['ATK-007', 'Amplop cokelat folio', 'stationery', 'pak', 15, 5, 35_000, 3],
            ['ATK-008', 'Sticky notes 3x3', 'stationery', 'pak', 30, 10, 12_000, 9],
            ['ATK-009', 'Isi staples No. 10', 'stationery', 'box', 40, 10, 3_000, 8],
            ['CTK-001', 'Toner HP 85A', 'printing', 'pcs', 8, 4, 650_000, 3],
            ['CTK-002', 'Tinta Epson 003 hitam', 'printing', 'botol', 10, 4, 85_000, 3],
            ['CTK-003', 'Kertas thermal 80 mm', 'printing', 'roll', 100, 40, 9_500, 35],
            ['KBR-001', 'Sabun cuci tangan 5 L', 'cleaning', 'galon', 12, 4, 95_000, 4],
            ['KBR-002', 'Tisu toilet jumbo roll', 'cleaning', 'roll', 90, 36, 22_000, 30],
            ['KBR-003', 'Kantong sampah hitam besar', 'cleaning', 'pak', 40, 15, 18_000, 12],
            ['KBR-004', 'Pembersih lantai 5 L', 'cleaning', 'galon', 10, 4, 120_000, 3],
            ['KBR-005', 'Hand sanitizer 500 ml', 'cleaning', 'botol', 30, 10, 38_000, 8],
            ['PTR-001', 'Kopi bubuk 1 kg', 'pantry', 'pak', 12, 4, 145_000, 4],
            ['PTR-002', 'Teh celup isi 50', 'pantry', 'box', 20, 6, 28_000, 6],
            ['PTR-003', 'Gula pasir 1 kg', 'pantry', 'kg', 25, 8, 18_500, 8],
            ['PTR-004', 'Air mineral galon', 'pantry', 'galon', 30, 10, 21_000, 22],
            ['PTR-005', 'Air mineral botol 600 ml', 'pantry', 'box', 15, 6, 52_000, 6],
        ];

        $models = collect();
        foreach ($items as [$code, $name, $category, $unit, $opening, $min, $cost, $monthly]) {
            $item = SupplyItem::create([
                'code' => $code, 'name' => $name, 'category' => $category, 'unit' => $unit, 'min_stock' => $min, 'unit_cost' => $cost,
                'storage_location' => $category === 'pantry' ? 'Pantry lantai 1' : 'Gudang GA, basement',
            ]);
            $item->move(StockMovementType::In, $opening * 2, [
                'moved_at' => $this->today->subMonths(3)->startOfMonth(), 'unit_cost' => $cost, 'reference' => 'Stok awal', 'user_id' => $this->users['ga']->id,
            ]);
            $models->put($code, [$item, $monthly]);
        }

        // Pemakaian 3 bulan dan satu kali pembelian ulang di tengah periode.
        foreach ($models as $code => [$item, $monthly]) {
            for ($week = 12; $week >= 1; $week--) {
                $qty = max(1, (int) round($monthly / 4 * $this->rand(70, 150) / 100));

                if ($item->fresh()->stock - $qty < 0) {
                    break;
                }

                $item->move(StockMovementType::Out, $qty, [
                    'moved_at' => $this->today->subWeeks($week)->addDays($this->rand(0, 4)),
                    'reference' => $this->pick(['Front Office', 'Housekeeping', 'Sales & Marketing', 'Accounting', 'Banquet', 'Engineering']),
                    'user_id' => $this->users['ga']->id,
                ]);

                if ($week === 6 && $this->rand(1, 3) > 1) {
                    $item->move(StockMovementType::In, $monthly * 2, [
                        'moved_at' => $this->today->subWeeks(6), 'unit_cost' => $item->unit_cost,
                        'reference' => 'PO/GA/'.$this->today->subWeeks(6)->format('Y/m').'/'.str_pad((string) $this->rand(10, 99), 3, '0', STR_PAD_LEFT),
                        'user_id' => $this->users['ga']->id,
                    ]);
                }
            }
        }

        // Beberapa barang sengaja dibuat menipis supaya terlihat di dasbor.
        foreach (['CTK-001' => 2, 'KBR-002' => 40, 'PTR-004' => 6, 'ATK-001' => 14] as $code => $target) {
            $item = $models[$code][0]->fresh();
            if ($item->stock > $target) {
                $item->move(StockMovementType::Out, $item->stock - $target, [
                    'moved_at' => $this->today->subDays(2), 'reference' => 'Banquet (event akhir pekan)', 'user_id' => $this->users['ga']->id,
                ]);
            }
        }

        $requests = [
            ['front_office', 'Kebutuhan bulanan front office', [['ATK-001', 5], ['ATK-003', 12], ['CTK-003', 20]], 'issued', -12],
            ['housekeeping', 'Perlengkapan toilet publik lobi', [['KBR-001', 2], ['KBR-002', 24], ['KBR-005', 6]], 'issued', -8],
            ['admin', 'ATK untuk audit internal', [['ATK-006', 6], ['ATK-005', 20], ['ATK-008', 4]], 'issued', -5],
            ['convention', 'Persiapan rapat klien korporat', [['PTR-005', 4], ['ATK-004', 6], ['ATK-002', 2]], 'approved', -1],
            ['fnb', 'Isi ulang pantry karyawan', [['PTR-001', 2], ['PTR-002', 3], ['PTR-003', 4]], 'submitted', 0],
            ['engineering', 'Tinta printer workshop', [['CTK-002', 2], ['CTK-001', 1]], 'submitted', 0],
            ['security', 'Buku laporan dan pulpen pos jaga', [['ATK-003', 10], ['ATK-007', 1]], 'rejected', -3],
        ];

        foreach ($requests as [$dept, $purpose, $lines, $state, $days]) {
            $request = SupplyRequest::create([
                'requester_id' => $this->pick(array_values($this->users))->id,
                'department_id' => $this->dept($dept),
                'requested_at' => $this->today->addDays($days)->setTime($this->rand(8, 15), 0),
                'needed_by' => $this->today->addDays($days + 2),
                'purpose' => $purpose,
            ]);
            foreach ($lines as [$code, $qty]) {
                $request->lines()->create(['supply_item_id' => $models[$code][0]->id, 'quantity' => $qty]);
            }

            match ($state) {
                'issued' => $request->issue(),
                'approved' => $request->approve(),
                'rejected' => $request->reject('Gunakan stok buku laporan yang ada di pos keamanan.'),
                default => null,
            };

            $request->update(['approved_by' => $state === 'submitted' ? null : $this->users['manajer']->id]);
        }
    }

    protected function documents(): void
    {
        $d = fn (int $days) => $this->today->addDays($days);
        $vehicle = fn (string $plate) => Vehicle::where('plate_number', $plate)->value('id');

        // nama, jenis, nomor, penerbit, terbit, berakhir, pengingat
        $documents = [
            ['Nomor Induk Berusaha (NIB)', 'license', '9120001234567', 'OSS RBA', $d(-1400), null, 60],
            ['Sertifikat Standar Usaha Hotel Bintang', 'certificate', 'SS-HTL/3273/2023/0456', 'Kementerian Pariwisata (OSS)', $d(-700), $d(395), 90],
            ['Sertifikat Laik Fungsi (SLF) Gedung Utama', 'certificate', '644/SLF/DPMPTSP/2021/087', 'DPMPTSP Kota Bandung', $d(-1800), $d(25), 90],
            ['SILO Lift Penumpang No. 1–3', 'certificate', '560/SILO/2025/1123', 'Disnakertrans Jawa Barat', $d(-340), $d(26), 60],
            ['SILO Genset 1000 kVA', 'certificate', '560/SILO/2025/1189', 'Disnakertrans Jawa Barat', $d(-380), $d(-12), 60],
            ['Izin Pembuangan Air Limbah (IPAL)', 'license', '660/IPLC/DLH/2024/032', 'DLH Kota Bandung', $d(-600), $d(495), 90],
            ['Sertifikat Halal Dapur Utama', 'certificate', 'ID32110000123450124', 'BPJPH', $d(-500), $d(1300), 120],
            ['Polis Asuransi Properti All Risk', 'insurance', 'PAR/BDG/2026/00871', 'PT Asuransi Contoh Indonesia', $d(-320), $d(45), 60],
            ['Polis Asuransi Kendaraan (5 unit)', 'insurance', 'MV/BDG/2026/02233', 'PT Asuransi Contoh Indonesia', $d(-300), $d(65), 45],
            ['Kontrak Jasa Keamanan Outsourcing', 'contract', 'GPCH/PKS/SEC/2025/014', 'PT Satpam Contoh Sejahtera', $d(-330), $d(35), 60],
            ['Kontrak Jasa Kebersihan Area Publik', 'contract', 'GPCH/PKS/CS/2026/003', 'CV Bersih Contoh Mandiri', $d(-120), $d(245), 60],
            ['Kontrak Pemeliharaan Lift', 'contract', 'GPCH/PKS/ENG/2026/007', 'PT Lift Contoh Teknik', $d(-200), $d(165), 60],
            ['PBB Tahun Berjalan', 'tax', 'SPPT 32.73.050.004.012-0123.0', 'Bapenda Kota Bandung', $d(-150), $d(70), 30],
            ['Izin Reklame Papan Nama Gedung', 'license', '503/REK/2025/0442', 'Bapenda Kota Bandung', $d(-310), $d(55), 45],
            ['STNK D 1955 GPC', 'tax', 'STNK-1955', 'Samsat Bandung Barat', $d(-1650), $d(180), 30, 'D 1955 GPC'],
        ];

        foreach ($documents as $row) {
            [$name, $type, $number, $issuer, $issued, $expiry, $reminder] = $row;
            LegalDocument::create([
                'name' => $name, 'document_type' => $type, 'number' => $number, 'issuer' => $issuer,
                'issued_date' => $issued, 'expiry_date' => $expiry, 'reminder_days' => $reminder,
                'responsible_id' => $this->pick([$this->users['ga'], $this->users['manajer']])->id,
                'vehicle_id' => isset($row[7]) ? $vehicle($row[7]) : null,
                'notes' => $expiry && $expiry->lt($this->today) ? 'Perpanjangan sedang diproses, menunggu jadwal riksa uji.' : null,
            ]);
        }
    }
}
