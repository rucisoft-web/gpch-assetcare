<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\AssignmentState;
use App\Enums\IdentificationSource;
use App\Enums\Role;
use App\Enums\SurveyState;
use App\Enums\VerificationStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRisk;
use App\Models\AssetSurvey;
use App\Models\AssetTemplate;
use App\Models\AuditLog;
use App\Models\CapexProposal;
use App\Models\Company;
use App\Models\Department;
use App\Models\Inspection;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\PreventivePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AssetIntake;
use App\Support\CurrentCompany;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh: Grand Pasundan Convention Hotel (GPCH), Bandung.
 * Fiktif tetapi realistis, dibangkitkan deterministik (seed acak tetap) agar hasilnya sama di setiap instalasi.
 */
class DemoSeeder extends Seeder
{
    protected Company $company;

    /** @var array<string, int> */
    protected array $dept = [];

    /** @var array<string, int> */
    protected array $cat = [];

    /** @var array<string, Location> */
    protected array $loc = [];

    /** @var array<string, AssetTemplate> templat per jenis ruang */
    protected array $template = [];

    /** @var array<string, string> kunci lokasi kamar => jenis kamar */
    protected array $roomType = [];

    /** @var array<string, int> kunci lokasi kamar => lantai */
    protected array $roomFloor = [];

    /** @var array<string, User> */
    protected array $users = [];

    protected CarbonImmutable $today;

    public function run(): void
    {
        mt_srand(2026);
        $this->today = CarbonImmutable::today();

        if (Company::where('slug', 'gpch')->exists()) {
            $this->command?->warn('Data contoh GPCH sudah ada. Lewati.');

            return;
        }

        AuditLog::$enabled = false;

        $this->company = Company::create([
            'name' => 'Grand Pasundan Convention Hotel',
            'code' => 'GPCH',
            'slug' => 'gpch',
            'industry' => 'Perhotelan & MICE',
            'city' => 'Bandung',
            'address' => 'Jl. Peta No. 147–149, Bandung, Jawa Barat (data contoh)',
            'is_demo' => true,
        ]);

        app(CurrentCompany::class)->runAs($this->company, function () {
            DB::transaction(function () {
                $this->users();
                $this->masterData();
                $this->locations();
                $this->templates();
                $this->assets();
                $this->operations();
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
        $password = config('asset.demo.password');
        $accounts = [
            'manajer' => ['Dedi Kurniawan', 'manajer@gpch.demo', Role::Manajer, 'Chief Engineer'],
            'teknisi' => ['Asep Saepudin', 'teknisi@gpch.demo', Role::Staf, 'Teknisi HVAC & Listrik'],
            'teknisi2' => ['Rina Marlina', 'supervisor@gpch.demo', Role::Staf, 'Supervisor Housekeeping'],
            'gm' => ['Hendra Wijaya', 'gm@gpch.demo', Role::Eksekutif, 'General Manager'],
        ];

        foreach ($accounts as $key => [$name, $email, $role, $title]) {
            $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'is_active' => true]);
            $user->memberships()->updateOrCreate(['company_id' => $this->company->id], ['role' => $role, 'position_title' => $title, 'is_active' => true]);
            $this->users[$key] = $user;
        }
    }

    protected function masterData(): void
    {
        $departments = [
            'rooms' => 'Rooms Division', 'engineering' => 'Engineering', 'fnb' => 'Food & Beverage', 'convention' => 'Convention & Banquet',
            'housekeeping' => 'Housekeeping & Laundry', 'front_office' => 'Front Office', 'recreation' => 'Recreation (Kolam, Fitness & Spa)',
            'security' => 'Security', 'it' => 'IT', 'admin' => 'Administrasi',
        ];

        $i = 0;
        foreach ($departments as $code => $name) {
            $this->dept[$code] = Department::create(['name' => $name, 'code' => strtoupper($code), 'sort' => ++$i * 10])->id;
        }

        $categories = [
            'hvac' => ['HVAC & Tata Udara', 15, 'high'],
            'elec' => ['Kelistrikan & Genset', 20, 'critical'],
            'lift' => ['Lift & Transportasi Vertikal', 25, 'critical'],
            'hotw' => ['Boiler & Air Panas', 15, 'high'],
            'plmb' => ['Plumbing, Pompa & STP', 15, 'high'],
            'gas' => ['Instalasi Gas Dapur', 15, 'critical'],
            'fire' => ['Proteksi Kebakaran', 15, 'critical'],
            'kitchen' => ['Peralatan Dapur', 10, 'high'],
            'room' => ['Peralatan Kamar', 7, 'medium'],
            'furn' => ['Furnitur & Interior', 8, 'low'],
            'av' => ['Audio Visual', 7, 'medium'],
            'it' => ['TI & Jaringan', 5, 'medium'],
            'laundry' => ['Mesin Laundry', 12, 'medium'],
            'pool' => ['Kolam, Fitness & Spa', 10, 'medium'],
            'sec' => ['Keamanan & CCTV', 7, 'medium'],
            'eqp' => ['Peralatan Operasional HK & Engineering', 7, 'low'],
        ];

        foreach ($categories as $code => [$name, $life, $crit]) {
            $this->cat[$code] = AssetCategory::create([
                'name' => $name, 'code' => strtoupper($code), 'expected_life_years' => $life, 'default_criticality' => $crit,
            ])->id;
        }
    }

    /**
     * Denah kamar per lantai (proyeksi dari profil GPCH: 155 kamar + 7 suite + 3-Bedroom Pasundan Suite,
     * Standard 3×5 m, Deluxe/Executive 4×8 m, executive floor lt. 10–11, President Suite lt. 11).
     *
     * @return array<int, array<int, array{0: string, 1: int}>> lantai => [[jenis, jumlah], ...]
     */
    protected function roomPlan(): array
    {
        return [
            2 => [['standard', 20]],
            3 => [['standard', 20]],
            4 => [['deluxe', 16]], 5 => [['deluxe', 16]], 6 => [['deluxe', 16]], 7 => [['deluxe', 16]], 8 => [['deluxe', 16]], 9 => [['deluxe', 16]],
            10 => [['executive', 14], ['junior', 2]],
            11 => [['executive', 5], ['junior', 2], ['family', 2], ['president', 1], ['pasundan', 1]],
        ];
    }

    protected function locations(): void
    {
        $make = fn (string $key, string $name, ?string $parent = null) => $this->loc[$key] = Location::create([
            'name' => $name, 'parent_id' => $parent ? $this->loc[$parent]->id : null,
        ]);

        $make('tower', 'Gedung Hotel');
        $make('lobby', 'Lantai 1 · Lobby', 'tower');
        $make('fo', 'Front Office', 'lobby');
        $make('lounge', 'Lobby Lounge', 'lobby');
        $make('cafe', 'Cafe Delapan (Coffee Shop)', 'lobby');
        $make('pustaka', 'Pustaka Pasundan', 'lobby');
        $make('kids', 'Kids Corner', 'lobby');
        $make('karaoke', 'Family Karaoke', 'lobby');

        $suffix = ['junior' => ' (Junior Suite)', 'family' => ' (Grand Family Suite)', 'president' => ' (President Suite)', 'pasundan' => ' (3-Bedroom Pasundan Suite)'];

        foreach ($this->roomPlan() as $floor => $groups) {
            $make("f{$floor}", "Lantai {$floor} · ".($floor >= 10 ? 'Executive Floor' : 'Kamar'), 'tower');
            $make("c{$floor}", "Koridor & Pantry HK Lantai {$floor}", "f{$floor}");
            $n = 0;

            foreach ($groups as [$type, $count]) {
                for ($i = 0; $i < $count; $i++) {
                    $number = $floor * 100 + (++$n);
                    $make("r{$number}", "Kamar {$number}".($suffix[$type] ?? ''), "f{$floor}");
                    $this->roomType["r{$number}"] = $type;
                    $this->roomFloor["r{$number}"] = $floor;
                }
            }
        }
        $make('exlounge', 'Executive Lounge', 'f10');

        $make('cc', 'Malibu Dome Convention Hall');
        $make('dome', 'Malibu Dome Hall 1.152 m² (Full / Half Malibu)', 'cc');
        $make('prefunction', 'Pre-function Malibu Dome', 'cc');
        $make('banquet', 'Banquet Kitchen & Gudang Banquet', 'cc');
        $make('jayagiri', 'Ruang Jayagiri 272 m²', 'cc');
        foreach (['Wastukantjana', 'Niskala', 'Citraganda', 'Sri Baduga', 'Sangkuriang', 'Arimbi', 'Linggabumi', 'Linggawarman', 'Dayang Sumbi', 'Raga Suci', 'Layungwangi'] as $i => $room) {
            $make('mr'.($i + 1), "Ruang Rapat {$room}", 'cc');
        }

        $make('fnb', 'Food & Beverage');
        $make('kitchen', 'Main Kitchen', 'fnb');
        $make('pastry', 'Pastry & Bakery', 'fnb');
        $make('poolcafe', "D'Linda Poolside Cafetaria", 'fnb');

        $make('boh', 'Back of House');
        $make('laundry', 'Laundry', 'boh');
        $make('workshop', 'Engineering Workshop', 'boh');
        $make('server', 'Ruang Server', 'boh');
        $make('store', 'Gudang Housekeeping & Umum', 'boh');
        $make('office', 'Kantor Administrasi', 'boh');

        $make('plant', 'Plant & Utilitas');
        $make('chiller', 'Chiller Plant', 'plant');
        $make('boiler', 'Ruang Boiler & Air Panas', 'plant');
        $make('genset', 'Ruang Genset', 'plant');
        $make('panel', 'Ruang Panel MV & LVMDP', 'plant');
        $make('pump', 'Ruang Pompa & Ground Tank', 'plant');
        $make('stp', 'STP & IPAL', 'plant');
        $make('gas', 'Stasiun Gas Dapur (LPG, rencana CNG)', 'plant');
        $make('liftroom', 'Lift Machine Room', 'plant');
        $make('roof', 'Rooftop & Cooling Tower', 'plant');

        $make('public', 'Area Publik & Rekreasi');
        $make('pool', 'Kolam Renang', 'public');
        $make('fitness', 'Fitness Center', 'public');
        $make('spa', 'Spa', 'public');
        $make('parking', 'Parkir Basement & Halaman (±200 kendaraan)', 'public');
    }

    /**
     * Templat per jenis kamar dan jenis ruang. Setiap baris: [nama, kategori, jumlah, departemen, merek, model].
     * Lokasi ditautkan ke templatnya (jenis ruang), sehingga petugas cukup memilih lokasi lalu
     * "Bangkitkan sesuai jenis ruang" tanpa mengetik ulang daftar aset setiap kamar.
     */
    protected function templates(): void
    {
        $bath = ['Shower mixer, wastafel & kloset', 'plmb', 1, 'engineering', 'TOTO', 'Hotel series'];
        $fan = ['Exhaust fan kamar mandi', 'hvac', 1, 'engineering', 'KDK', '25CUH'];
        $smoke = fn (int $qty) => ['Smoke detector', 'fire', $qty, 'security', 'Hochiki', 'SLR-E3N'];
        $lock = ['Kunci pintu RFID', 'sec', 1, 'front_office', 'Salto', 'XS4'];
        $phone = fn (int $qty) => ['Telepon kamar', 'it', $qty, 'it', 'Panasonic', 'KX-TS500'];
        $dryer = fn (int $qty) => ['Hair dryer dinding', 'room', $qty, 'housekeeping', 'Panasonic', 'EH-ND30'];

        $rooms = [
            'standard' => ['Kamar Standard', 'Kamar Standard 3×5 m, 1 queen atau 2 twin. Lantai 2–3.', [
                ['AC split 1 PK', 'hvac', 1, 'engineering', 'Daikin', 'FTKC25'],
                ['Smart TV 32"', 'room', 1, 'rooms', 'Samsung', 'HG32'],
                ['Minibar 30 L', 'room', 1, 'rooms', 'Dometic', 'RH430'],
                ['Brankas kamar', 'room', 1, 'rooms', 'Onity', 'ES1000'],
                $lock,
                ['Tempat tidur & kasur queen', 'furn', 1, 'housekeeping', 'Serta', 'Hotel Series'],
                ['Meja kerja & kursi', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Lemari pakaian', 'furn', 1, 'housekeeping', '—', 'Built-in'],
                $phone(1), $dryer(1), $bath, $fan, $smoke(1),
            ]],
            'deluxe' => ['Kamar Deluxe', 'Kamar Deluxe 4×8 m, king atau twin. Lantai 4–9.', [
                ['AC split 1,5 PK', 'hvac', 1, 'engineering', 'Daikin', 'FTKC35'],
                ['Smart TV 43"', 'room', 1, 'rooms', 'Samsung', 'HG43'],
                ['Minibar 40 L', 'room', 1, 'rooms', 'Dometic', 'RH439'],
                ['Brankas kamar (muat laptop)', 'room', 1, 'rooms', 'Onity', 'ES1000L'],
                $lock,
                ['Tempat tidur & kasur king', 'furn', 1, 'housekeeping', 'Serta', 'Hotel Series'],
                ['Sofa / kursi santai', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Meja kerja & kursi', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Lemari pakaian', 'furn', 1, 'housekeeping', '—', 'Built-in'],
                $phone(1), $dryer(1), $bath, $fan, $smoke(1),
            ]],
            'executive' => ['Kamar Executive', 'Kamar Executive 4×8 m di executive floor lt. 10–11, akses Executive Lounge.', [
                ['AC split 1,5 PK', 'hvac', 1, 'engineering', 'Daikin', 'FTKC35'],
                ['Smart TV 50"', 'room', 1, 'rooms', 'Samsung', 'HG50'],
                ['Minibar 40 L', 'room', 1, 'rooms', 'Dometic', 'RH439'],
                ['Mesin kopi kapsul', 'room', 1, 'rooms', 'Nespresso', 'Zenius'],
                ['Brankas kamar (muat laptop)', 'room', 1, 'rooms', 'Onity', 'ES1000L'],
                $lock,
                ['Tempat tidur & kasur king', 'furn', 1, 'housekeeping', 'Serta', 'Hotel Series Plus'],
                ['Sofa set', 'furn', 1, 'housekeeping', '—', 'Custom ornamen Pasundan'],
                ['Meja kerja & kursi ergonomis', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Lemari pakaian', 'furn', 1, 'housekeeping', '—', 'Built-in'],
                ['Bathtub', 'plmb', 1, 'engineering', 'TOTO', 'PJY1604'],
                $phone(1), $dryer(1), $bath, $fan, $smoke(1),
            ]],
            'junior' => ['Junior Suite', 'Suite satu kamar tidur dengan ruang duduk. Lantai 10–11.', [
                ['AC split 1,5 PK', 'hvac', 2, 'engineering', 'Daikin', 'FTKC35'],
                ['Smart TV 50"', 'room', 1, 'rooms', 'Samsung', 'HG50'],
                ['Smart TV 43"', 'room', 1, 'rooms', 'Samsung', 'HG43'],
                ['Minibar 40 L', 'room', 1, 'rooms', 'Dometic', 'RH439'],
                ['Mesin kopi kapsul', 'room', 1, 'rooms', 'Nespresso', 'Zenius'],
                ['Brankas kamar (muat laptop)', 'room', 1, 'rooms', 'Onity', 'ES1000L'],
                $lock,
                ['Tempat tidur & kasur king', 'furn', 1, 'housekeeping', 'Serta', 'Hotel Series Plus'],
                ['Living set (sofa & meja tamu)', 'furn', 1, 'housekeeping', '—', 'Custom ornamen Pasundan'],
                ['Meja kerja & kursi ergonomis', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Lemari pakaian', 'furn', 1, 'housekeeping', '—', 'Built-in'],
                ['Bathtub', 'plmb', 1, 'engineering', 'TOTO', 'PJY1604'],
                $phone(2), $dryer(1), $bath, $fan, $smoke(2),
            ]],
            'family' => ['Grand Family Suite', 'Suite keluarga dua kamar tidur + ruang keluarga & pantry. Lantai 11.', [
                ['AC split 1,5 PK', 'hvac', 3, 'engineering', 'Daikin', 'FTKC35'],
                ['Smart TV 50"', 'room', 1, 'rooms', 'Samsung', 'HG50'],
                ['Smart TV 43"', 'room', 2, 'rooms', 'Samsung', 'HG43'],
                ['Minibar 40 L', 'room', 1, 'rooms', 'Dometic', 'RH439'],
                ['Kulkas pantry 2 pintu', 'room', 1, 'rooms', 'Sharp', 'SJ-IG'],
                ['Microwave', 'room', 1, 'rooms', 'Sharp', 'R-21D0'],
                ['Mesin kopi kapsul', 'room', 1, 'rooms', 'Nespresso', 'Zenius'],
                ['Brankas kamar (muat laptop)', 'room', 1, 'rooms', 'Onity', 'ES1000L'],
                $lock,
                ['Tempat tidur & kasur king', 'furn', 1, 'housekeeping', 'Serta', 'Hotel Series Plus'],
                ['Tempat tidur & kasur twin', 'furn', 2, 'housekeeping', 'Serta', 'Hotel Series'],
                ['Sofa bed', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Living set (sofa & meja tamu)', 'furn', 1, 'housekeeping', '—', 'Custom ornamen Pasundan'],
                ['Meja makan 4 kursi', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Lemari pakaian', 'furn', 2, 'housekeeping', '—', 'Built-in'],
                ['Bathtub', 'plmb', 1, 'engineering', 'TOTO', 'PJY1604'],
                ['Shower mixer, wastafel & kloset', 'plmb', 2, 'engineering', 'TOTO', 'Hotel series'],
                ['Exhaust fan kamar mandi', 'hvac', 2, 'engineering', 'KDK', '25CUH'],
                $phone(2), $dryer(2), $smoke(3),
            ]],
            'president' => ['President Suite', 'Suite utama lt. 11: ruang tamu, ruang makan, ruang kerja, pantry, kamar mandi jacuzzi.', [
                ['AC split 2 PK ruang tamu', 'hvac', 2, 'engineering', 'Daikin', 'FTKC50'],
                ['AC split 1,5 PK', 'hvac', 2, 'engineering', 'Daikin', 'FTKC35'],
                ['Smart TV 65" ruang tamu', 'room', 1, 'rooms', 'Samsung', 'HG65'],
                ['Smart TV 55" kamar tidur', 'room', 1, 'rooms', 'Samsung', 'HG55'],
                ['Smart TV 43" ruang kerja', 'room', 1, 'rooms', 'Samsung', 'HG43'],
                ['Soundbar ruang tamu', 'av', 1, 'rooms', 'Bose', 'Smart Soundbar 600'],
                ['Minibar 40 L', 'room', 1, 'rooms', 'Dometic', 'RH439'],
                ['Kulkas pantry 2 pintu', 'room', 1, 'rooms', 'Sharp', 'SJ-IG'],
                ['Microwave', 'room', 1, 'rooms', 'Sharp', 'R-21D0'],
                ['Mesin kopi espresso otomatis', 'room', 1, 'rooms', 'Jura', 'E8'],
                ['Brankas kamar (muat laptop)', 'room', 1, 'rooms', 'Onity', 'ES1000L'],
                $lock,
                ['Tempat tidur & kasur king premium', 'furn', 1, 'housekeeping', 'Serta', 'Presidential'],
                ['Living set premium', 'furn', 1, 'housekeeping', '—', 'Custom ornamen Pasundan'],
                ['Meja makan 6 kursi', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Meja kerja eksekutif & kursi', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Walk-in closet', 'furn', 1, 'housekeeping', '—', 'Built-in'],
                ['Bathtub jacuzzi', 'plmb', 1, 'engineering', 'Jacuzzi', 'Aura'],
                ['Shower mixer, wastafel & kloset', 'plmb', 2, 'engineering', 'TOTO', 'Hotel series'],
                ['Exhaust fan kamar mandi', 'hvac', 2, 'engineering', 'KDK', '25CUH'],
                $phone(3), $dryer(2), $smoke(4),
            ]],
            'pasundan' => ['3-Bedroom Pasundan Suite', 'Suite tiga kamar tidur bernuansa Pasundan, dengan dapur kecil dan ruang keluarga. Lantai 11.', [
                ['AC split 2 PK ruang keluarga', 'hvac', 2, 'engineering', 'Daikin', 'FTKC50'],
                ['AC split 1,5 PK', 'hvac', 3, 'engineering', 'Daikin', 'FTKC35'],
                ['Smart TV 55" ruang keluarga', 'room', 1, 'rooms', 'Samsung', 'HG55'],
                ['Smart TV 43"', 'room', 3, 'rooms', 'Samsung', 'HG43'],
                ['Minibar 40 L', 'room', 1, 'rooms', 'Dometic', 'RH439'],
                ['Kulkas 2 pintu', 'room', 1, 'rooms', 'Sharp', 'SJ-IG'],
                ['Microwave', 'room', 1, 'rooms', 'Sharp', 'R-21D0'],
                ['Kompor induksi & cooker hood', 'room', 1, 'rooms', 'Rinnai', 'RI-602'],
                ['Mesin kopi kapsul', 'room', 1, 'rooms', 'Nespresso', 'Zenius'],
                ['Brankas kamar (muat laptop)', 'room', 1, 'rooms', 'Onity', 'ES1000L'],
                $lock,
                ['Tempat tidur & kasur king', 'furn', 1, 'housekeeping', 'Serta', 'Hotel Series Plus'],
                ['Tempat tidur & kasur queen', 'furn', 1, 'housekeeping', 'Serta', 'Hotel Series'],
                ['Tempat tidur & kasur twin', 'furn', 2, 'housekeeping', 'Serta', 'Hotel Series'],
                ['Living set ornamen Pasundan', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Meja makan 8 kursi', 'furn', 1, 'housekeeping', '—', 'Custom'],
                ['Lemari pakaian', 'furn', 3, 'housekeeping', '—', 'Built-in'],
                ['Bathtub', 'plmb', 1, 'engineering', 'TOTO', 'PJY1604'],
                ['Shower mixer, wastafel & kloset', 'plmb', 3, 'engineering', 'TOTO', 'Hotel series'],
                ['Exhaust fan kamar mandi', 'hvac', 3, 'engineering', 'KDK', '25CUH'],
                $phone(3), $dryer(3), $smoke(5),
            ]],
        ];

        $others = [
            'corridor' => ['Koridor & Pantry HK Lantai Kamar', 'public', 'Perlengkapan tetap per lantai kamar: Wi-Fi, CCTV, proteksi kebakaran, panel listrik, pantry housekeeping.', [
                ['Access point Wi-Fi', 'it', 3, 'it', 'Ruckus', 'R550'],
                ['Kamera CCTV dome koridor', 'sec', 2, 'security', 'Hikvision', 'DS-2CD2143'],
                ['APAR 6 kg', 'fire', 2, 'security', 'Yamato', 'ABC 6 kg'],
                ['Box hidran gedung', 'fire', 1, 'security', '—', 'Tipe B'],
                ['Lampu darurat & exit sign', 'fire', 4, 'security', 'Philips', 'Ready-Lite'],
                ['Speaker evakuasi (voice evacuation)', 'fire', 3, 'security', 'TOA', 'PC-658R'],
                ['Panel listrik SDP lantai', 'elec', 1, 'engineering', 'Schneider', 'Prisma G'],
                ['FCU koridor', 'hvac', 2, 'engineering', 'Daikin', 'FWD'],
                ['Ice dispenser tamu', 'kitchen', 1, 'rooms', 'Hoshizaki', 'DM-4420'],
                ['Troli housekeeping', 'eqp', 2, 'housekeeping', '—', 'Service cart'],
            ]],
            'meeting' => ['Ruang Rapat Standar', 'meeting', 'Ruang rapat 30–80 pax.', [
                ['Proyektor 5.000 lm', 'av', 1, 'convention', 'Epson', 'EB-L630U'],
                ['Layar motorized 120"', 'av', 1, 'convention', 'Screenview', 'Motorized'],
                ['Sound system ceiling & mixer', 'av', 1, 'convention', 'TOA', 'A-2240'],
                ['Mikrofon nirkabel (set 2)', 'av', 1, 'convention', 'Shure', 'BLX288'],
                ['AC split 2 PK', 'hvac', 2, 'engineering', 'Daikin', 'FTKC50'],
                ['Meja & kursi rapat (set)', 'furn', 1, 'convention', '—', 'Banquet'],
                ['Access point Wi-Fi', 'it', 1, 'it', 'Ruckus', 'R550'],
                $smoke(2),
            ]],
            'meeting_large' => ['Ruang Rapat Besar', 'meeting', 'Ruang serbaguna 200–300 pax (mis. Jayagiri 272 m²).', [
                ['Proyektor laser 10.000 lm', 'av', 2, 'convention', 'Epson', 'EB-PU1008'],
                ['Layar motorized 200"', 'av', 2, 'convention', 'Screenline', 'Major'],
                ['Sound system & mixer digital', 'av', 1, 'convention', 'Yamaha', 'TF1'],
                ['Mikrofon nirkabel (set 4)', 'av', 1, 'convention', 'Shure', 'ULXD'],
                ['AC cassette 5 PK', 'hvac', 4, 'engineering', 'Daikin', 'FCNQ48'],
                ['Meja & kursi banquet (set)', 'furn', 1, 'convention', '—', 'Banquet'],
                ['Access point Wi-Fi', 'it', 2, 'it', 'Ruckus', 'R750'],
                $smoke(4),
            ]],
        ];

        $makeTemplate = function (string $key, string $name, string $type, string $note, array $lines) {
            $template = AssetTemplate::create(['name' => $name, 'location_type' => $type, 'note' => $note]);

            foreach ($lines as $i => [$line, $cat, $qty, $dept, $brand, $model]) {
                $template->lines()->create([
                    'sort' => ($i + 1) * 10, 'name' => $line, 'asset_category_id' => $this->cat[$cat], 'quantity' => $qty,
                    'department_id' => $this->dept[$dept], 'brand' => $brand, 'model' => $model,
                    'expected_life_years' => AssetCategory::find($this->cat[$cat])->expected_life_years,
                ]);
            }

            $this->template[$key] = $template;
        };

        foreach ($rooms as $key => [$name, $note, $lines]) {
            $makeTemplate($key, $name, 'room', $note, $lines);
        }

        foreach ($others as $key => [$name, $type, $note, $lines]) {
            $makeTemplate($key, $name, $type, $note, $lines);
        }

        // Tautkan setiap lokasi ke jenis ruangnya.
        foreach ($this->roomType as $locKey => $type) {
            $this->loc[$locKey]->update(['asset_template_id' => $this->template[$type]->id]);
        }
        foreach (array_keys($this->roomPlan()) as $floor) {
            $this->loc["c{$floor}"]->update(['asset_template_id' => $this->template['corridor']->id]);
        }
        foreach (range(1, 11) as $n) {
            $this->loc["mr{$n}"]->update(['asset_template_id' => $this->template['meeting']->id]);
        }
        $this->loc['jayagiri']->update(['asset_template_id' => $this->template['meeting_large']->id]);
    }

    protected function assets(): void
    {
        $survey = AssetSurvey::create([
            'name' => 'Survei Aset Awal 2026', 'date_start' => $this->today->subMonths(5)->startOfMonth(), 'state' => SurveyState::InProgress,
            'manager_id' => $this->users['manajer']->id,
            'note' => 'Inventarisasi awal seluruh kamar (163 unit, 8 jenis kamar), koridor lantai, ruang rapat, dan aset utama gedung.',
        ]);

        // Kamar, koridor, dan ruang rapat: dibangkitkan sesuai jenis ruang (templat) masing-masing lokasi.
        $intake = app(AssetIntake::class);
        $linked = Location::whereNotNull('asset_template_id')->pluck('id')->all();
        $intake->fromLinkedTemplates($linked, $survey->id);

        $floorOf = fn (string $key) => (int) preg_replace('/\D/', '', $key);

        foreach (array_keys($this->roomPlan()) as $floor) {
            $mainType = $this->roomPlan()[$floor][0][0];
            $assignment = $survey->assignments()->create([
                'location_id' => $this->loc["f{$floor}"]->id, 'team_name' => "Tim Lantai {$floor}",
                'asset_template_id' => $this->template[$mainType]->id,
                'state' => $floor <= 5 ? AssignmentState::Done : ($floor <= 7 ? AssignmentState::InProgress : AssignmentState::Assigned),
            ]);
            $assignment->surveyors()->sync([$this->users['teknisi']->id, $this->users['teknisi2']->id]);
        }

        $survey->assignments()->create([
            'location_id' => $this->loc['cc']->id, 'team_name' => 'Tim Convention', 'asset_template_id' => $this->template['meeting']->id, 'state' => AssignmentState::InProgress,
        ])->surveyors()->sync([$this->users['teknisi']->id]);

        // Lantai lokasi untuk menentukan progres verifikasi: kamar & koridor per lantai, ruang rapat dianggap separuh jalan.
        $floorByLocation = [];
        foreach ($this->loc as $key => $location) {
            if (preg_match('/^[rc]\d+$/', $key)) {
                $floorByLocation[$location->id] = $key[0] === 'r' ? $this->roomFloor[$key] : $floorOf($key);
            }
        }

        $verifier = $this->users['teknisi'];

        Asset::where('asset_survey_id', $survey->id)->get()->each(function (Asset $asset) use ($verifier, $floorByLocation) {
            $floor = $floorByLocation[$asset->location_id] ?? 7;
            $roll = $this->rand(1, 100);

            $status = match (true) {
                $floor >= 8 => VerificationStatus::Expected,
                $floor >= 6 && $roll > 55 => VerificationStatus::Expected,
                $roll <= 3 => VerificationStatus::Missing,
                default => VerificationStatus::Verified,
            };

            $this->randomizeHealth($asset, $status === VerificationStatus::Missing ? 30 : null);
            $asset->fill([
                'verification_status' => $status,
                'verified_by_id' => $status === VerificationStatus::Expected ? null : $verifier->id,
                'verified_at' => $status === VerificationStatus::Expected ? null : $this->today->subDays($this->rand(5, 120))->setTime($this->rand(8, 16), $this->rand(0, 59)),
                'purchase_date' => $this->today->subYears($this->rand(2, 9))->subDays($this->rand(0, 300)),
                'serial_no' => $status === VerificationStatus::Verified ? strtoupper(substr(md5((string) $asset->id), 0, 10)) : null,
                'acquisition_cost' => $this->costFor($asset->asset_category_id, $asset->name),
                'status' => $status === VerificationStatus::Missing ? AssetStatus::OutOfService : AssetStatus::Operational,
                'verification_note' => $status === VerificationStatus::Missing ? 'Tidak ditemukan saat survei; diduga dipindah ke gudang.' : null,
            ]);
            $asset->replacement_cost = round($asset->acquisition_cost * 1.25, -3);
            $asset->save();
        });

        // Aset tambahan yang ditemukan di lapangan.
        foreach ([['r301', 'Kulkas tambahan (permintaan tamu tetap)', 'room'], ['r205', 'Kursi santai tambahan', 'furn'], ['mr2', 'Flip chart elektronik', 'av']] as [$loc, $name, $cat]) {
            $asset = new Asset([
                'name' => $name, 'asset_category_id' => $this->cat[$cat], 'location_id' => $this->loc[$loc]->id, 'department_id' => $this->dept['rooms'],
                'verification_status' => VerificationStatus::Additional, 'identification_source' => IdentificationSource::Additional,
                'asset_survey_id' => $survey->id, 'verified_by_id' => $verifier->id, 'verified_at' => $this->today->subDays($this->rand(10, 60)),
                'acquisition_cost' => $this->costFor($this->cat[$cat], $name),
            ]);
            $this->randomizeHealth($asset);
            $asset->save();
        }

        $this->majorAssets();
    }

    /**
     * Aset utama gedung (fixed asset di luar kamar). Dicatat manual.
     * - Terkonfirmasi: disebut dalam laporan manajemen GPCH 2026 → Terverifikasi.
     * - Proyeksi: lazim dimiliki hotel bintang 4 dengan 164 kamar, convention hall 1.152 m², dan 13 ruang rapat
     *   → berstatus Diharapkan, menunggu dicek tim lapangan di menu Verifikasi Aset.
     */
    protected function majorAssets(): void
    {
        // [nama, kategori, lokasi, departemen, merek, model, tahun beli, nilai per unit (juta), profil kondisi|null, dampak|null, jumlah, terkonfirmasi]
        // Dampak: [keselamatan, tamu, pendapatan, operasional, kesulitan penggantian].
        $list = [
            // HVAC
            ['Chiller #1 300 TR', 'hvac', 'chiller', 'engineering', 'York', 'YK water-cooled 300 TR', 2013, 3000, 'fair', [4, 5, 5, 5, 5]],
            ['Chiller #2 300 TR', 'hvac', 'chiller', 'engineering', 'York', 'YK water-cooled 300 TR', 2013, 3000, 'poor', [4, 5, 5, 5, 5]],
            ['Chilled Water Pump', 'hvac', 'chiller', 'engineering', 'Grundfos', 'NBG 150', 2013, 120, null, [2, 4, 4, 5, 3], 3],
            ['Condenser Water Pump', 'hvac', 'chiller', 'engineering', 'Grundfos', 'NBG 150', 2013, 110, null, [2, 4, 4, 5, 3], 3],
            ['Cooling Tower', 'hvac', 'roof', 'engineering', 'Liang Chi', 'LBC-300', 2013, 650, 'fair', [3, 4, 4, 4, 3], 2],
            ['AHU Malibu Dome', 'hvac', 'dome', 'convention', 'Trane', 'CLCP-30', 2013, 850, 'fair', [2, 5, 5, 4, 4], 4],
            ['AHU Lobby & Lounge', 'hvac', 'lobby', 'engineering', 'Trane', 'CLCP-14', 2013, 420, 'good', [2, 4, 3, 3, 3]],
            ['AHU Ruang Jayagiri', 'hvac', 'jayagiri', 'convention', 'Trane', 'CLCP-10', 2013, 320, 'fair', [2, 4, 4, 3, 3]],
            ['AHU Cafe Delapan', 'hvac', 'cafe', 'fnb', 'Trane', 'CLCP-8', 2013, 280, null, [2, 4, 4, 3, 3]],
            ['Jet Fan Ventilasi Basement', 'hvac', 'parking', 'engineering', 'Kruger', 'BSB', 2013, 45, null, [3, 2, 1, 3, 2], 4],
            ['AC Presisi Ruang Server', 'hvac', 'server', 'it', 'Stulz', 'CyberAir', 2020, 160, 'good', [1, 3, 4, 5, 3], 2],
            ['Building Automation System (BAS)', 'hvac', 'panel', 'engineering', 'Honeywell', 'EBI', 2013, 450, 'poor', [2, 3, 3, 4, 4]],

            // Listrik
            ['Kubikel 20 kV (PLN TM)', 'elec', 'panel', 'engineering', 'Schneider', 'RM6', 2013, 650, 'good', [5, 5, 5, 5, 4]],
            ['Trafo 2.000 kVA', 'elec', 'panel', 'engineering', 'Trafindo', '2000 kVA ONAN', 2013, 1300, 'good', [5, 5, 5, 5, 5]],
            ['Panel LVMDP Utama', 'elec', 'panel', 'engineering', 'Schneider', 'Prisma P', 2013, 1500, 'fair', [5, 5, 5, 5, 4]],
            ['Panel Capacitor Bank 600 kVAR', 'elec', 'panel', 'engineering', 'Schneider', 'VarSet', 2013, 260, 'poor', [3, 2, 3, 4, 2]],
            ['Genset #1 1.000 kVA', 'elec', 'genset', 'engineering', 'Cummins', 'C1000D5', 2013, 2800, 'good', [5, 5, 5, 5, 5], 1, true],
            ['Genset #2 1.000 kVA', 'elec', 'genset', 'engineering', 'Cummins', 'C1000D5', 2013, 2800, 'fair', [5, 5, 5, 5, 5], 1, true],
            ['Panel ATS/AMF & Sinkron Genset', 'elec', 'genset', 'engineering', 'Deep Sea', 'DSE8610', 2013, 380, 'fair', [5, 5, 5, 5, 4]],
            ['Tangki Solar Bulanan 20.000 L', 'elec', 'genset', 'engineering', '—', 'Tangki tanam', 2013, 240, 'good', [4, 3, 3, 4, 3]],
            ['Tangki Solar Harian 1.000 L', 'elec', 'genset', 'engineering', '—', 'Day tank', 2013, 35, null, [4, 3, 3, 4, 2], 2],
            ['UPS Ruang Server 20 kVA', 'elec', 'server', 'it', 'APC', 'Galaxy VS', 2020, 180, 'good', [2, 3, 4, 4, 2]],
            ['Sistem Penangkal Petir', 'elec', 'roof', 'engineering', 'Kurn', 'R 150', 2013, 90, 'fair', [4, 2, 2, 3, 2]],
            ['Lampu LED Area Publik', 'elec', 'public', 'engineering', 'Philips', 'CoreLine Downlight', 2025, 420, 'excellent', [1, 3, 2, 2, 1]],

            // Lift
            ['Lift Penumpang #1', 'lift', 'liftroom', 'engineering', 'Mitsubishi', 'Panel kontrol baru (Elpa Global Techno) 2026', 2013, 1350, 'good', [5, 5, 4, 5, 5], 1, true],
            ['Lift Penumpang #2', 'lift', 'liftroom', 'engineering', 'Mitsubishi', 'GPS-III', 2013, 1350, 'fair', [5, 5, 4, 5, 5], 1, true],
            ['Lift Penumpang #3', 'lift', 'liftroom', 'engineering', 'Mitsubishi', 'GPS-III', 2013, 1350, 'poor', [5, 5, 4, 5, 5]],
            ['Lift Service / Barang 1.600 kg', 'lift', 'liftroom', 'engineering', 'Mitsubishi', 'Freight', 2013, 980, 'poor', [4, 2, 3, 5, 4]],
            ['Lift Barang Banquet Malibu Dome', 'lift', 'banquet', 'convention', 'Mitsubishi', 'Freight 2000', 2013, 900, 'fair', [4, 3, 4, 4, 4]],
            ['Dumbwaiter Dapur', 'lift', 'kitchen', 'fnb', 'Fuji', 'DW-100', 2013, 150, 'fair', [2, 2, 3, 4, 2]],
            ['Gondola Pembersih Fasad', 'lift', 'roof', 'engineering', '—', 'BMU rel atap', 2013, 450, 'fair', [5, 1, 1, 2, 3]],

            // Boiler & air panas
            ['Boiler Air Panas #1 600 kW', 'hotw', 'boiler', 'engineering', 'Ferroli', 'Prextherm RSW 600', 2013, 650, 'fair', [4, 5, 4, 4, 4]],
            ['Boiler Air Panas #2 600 kW', 'hotw', 'boiler', 'engineering', 'Ferroli', 'Prextherm RSW 600', 2013, 650, 'poor', [4, 5, 4, 4, 4]],
            ['Burner Boiler (siap CNG)', 'hotw', 'boiler', 'engineering', 'Riello', 'RS 68', 2013, 95, null, [4, 4, 3, 4, 3], 2],
            ['Tangki Air Panas (Calorifier) 3.000 L', 'hotw', 'boiler', 'engineering', 'Ariston', 'Calorifier', 2013, 180, 'fair', [3, 5, 3, 4, 3], 2],
            ['Pompa Sirkulasi Air Panas', 'hotw', 'boiler', 'engineering', 'Grundfos', 'Magna3', 2018, 45, null, [2, 5, 3, 4, 2], 2],
            ['Heat Pump Air Panas Kolam & Spa', 'hotw', 'roof', 'engineering', 'Ariston', 'Nuos 300', 2019, 520, 'good', [2, 4, 3, 3, 3]],
            ['Water Softener Boiler', 'hotw', 'boiler', 'engineering', 'Culligan', 'HI-Flo', 2016, 60, null, [2, 3, 2, 4, 2]],

            // Plumbing & air bersih
            ['Pompa Sumur Dalam', 'plmb', 'pump', 'engineering', 'Grundfos', 'SP 17', 2013, 75, null, [2, 5, 4, 5, 3], 2],
            ['Pompa Transfer Air Bersih', 'plmb', 'pump', 'engineering', 'Grundfos', 'CR 32', 2017, 95, 'fair', [2, 5, 4, 5, 2], 2],
            ['Booster Pump Set Kamar', 'plmb', 'pump', 'engineering', 'Grundfos', 'Hydro MPC-E', 2017, 280, 'good', [2, 5, 4, 5, 3]],
            ['Water Treatment Plant (sand & carbon filter)', 'plmb', 'pump', 'engineering', 'Pentair', 'WTP 10 m³/jam', 2013, 350, 'fair', [2, 4, 3, 4, 3]],
            ['Ground Water Tank 400 m³', 'plmb', 'pump', 'engineering', '—', 'Beton', 2013, 500, 'good', [2, 4, 3, 4, 4]],
            ['Roof Tank 2 × 30 m³', 'plmb', 'roof', 'engineering', '—', 'Panel FRP', 2013, 160, 'fair', [2, 4, 3, 4, 3]],
            ['Sewage Treatment Plant 200 m³/hari', 'plmb', 'stp', 'engineering', 'Biotech', 'STP 200', 2013, 1400, 'poor', [3, 3, 3, 5, 4], 1, true],
            ['Blower STP', 'plmb', 'stp', 'engineering', 'Shinmaywa', 'ARH', 2013, 65, 'poor', [2, 2, 2, 5, 2], 2],
            ['Pompa Sump Pit Basement', 'plmb', 'parking', 'engineering', 'Ebara', 'DVS', 2013, 25, null, [3, 2, 1, 3, 1], 3],
            ['Grease Trap Dapur', 'plmb', 'kitchen', 'fnb', '—', 'GT 5 m³', 2013, 60, 'poor', [2, 2, 3, 4, 2]],

            // Gas
            ['Instalasi Gas LPG Dapur (manifold & regulator)', 'gas', 'gas', 'fnb', '—', 'Manifold 12 tabung 50 kg', 2013, 180, 'fair', [5, 2, 4, 5, 3], 1, true],
            ['Detektor Kebocoran Gas & Solenoid Valve', 'gas', 'kitchen', 'fnb', 'Honeywell', 'Sensepoint', 2018, 45, null, [5, 2, 3, 4, 2]],

            // Proteksi kebakaran
            ['Pompa Pemadam Diesel 750 GPM', 'fire', 'pump', 'security', 'Ebara', 'FSA', 2013, 620, 'good', [5, 4, 3, 4, 4]],
            ['Pompa Pemadam Elektrik 750 GPM', 'fire', 'pump', 'security', 'Ebara', 'FSA', 2013, 450, 'fair', [5, 4, 3, 4, 4]],
            ['Pompa Jockey', 'fire', 'pump', 'security', 'Grundfos', 'CR 5', 2013, 60, 'fair', [5, 3, 2, 3, 2]],
            ['Panel Fire Alarm (MCFA)', 'fire', 'fo', 'security', 'Hochiki', 'FireNET Plus', 2018, 350, 'good', [5, 4, 3, 4, 3]],
            ['Sistem Sprinkler Gedung', 'fire', 'tower', 'security', '—', 'Wet pipe', 2013, 900, 'fair', [5, 4, 3, 3, 4]],
            ['Sistem Hidran Gedung & Halaman', 'fire', 'tower', 'security', '—', 'Hidran pilar & box', 2013, 650, 'fair', [5, 4, 3, 3, 4]],
            ['Sistem Voice Evacuation', 'fire', 'fo', 'security', 'TOA', 'VX-3000', 2016, 280, 'fair', [5, 4, 2, 3, 3]],
            ['Pemadam Otomatis Hood Dapur', 'fire', 'kitchen', 'fnb', 'Ansul', 'R-102', 2018, 220, 'good', [5, 2, 4, 4, 3]],
            ['Pemadam Gas FM-200 Ruang Server', 'fire', 'server', 'it', 'Kidde', 'FM-200', 2020, 210, 'good', [4, 2, 3, 4, 3]],
            ['APAR Area BOH, Dapur & Plant', 'fire', 'boh', 'security', 'Yamato', 'ABC 6 kg / CO2 5 kg', 2023, 1.5, null, [4, 2, 1, 2, 1], 20],

            // Dapur
            ['Combi Oven 20 GN', 'kitchen', 'kitchen', 'fnb', 'Rational', 'iCombi Pro 20-1/1', 2021, 680, 'excellent', [2, 4, 5, 4, 3]],
            ['Combi Oven 10 GN', 'kitchen', 'banquet', 'fnb', 'Rational', 'iCombi Pro 10-1/1', 2019, 420, 'good', [2, 4, 5, 4, 3]],
            ['Kompor Wok Chinese 3 Tungku', 'kitchen', 'kitchen', 'fnb', 'Getra', 'CWR-3', 2013, 45, 'poor', null, 2],
            ['Gas Range 6 Burner + Oven', 'kitchen', 'kitchen', 'fnb', 'Fagor', 'CG9-61', 2015, 85, 'fair', null, 2],
            ['Tilting Bratt Pan 80 L', 'kitchen', 'kitchen', 'fnb', 'Electrolux', 'Thermaline', 2015, 210, 'fair'],
            ['Steam Kettle 150 L', 'kitchen', 'banquet', 'fnb', 'Electrolux', 'PUKG15', 2015, 230, 'fair'],
            ['Deep Fryer Gas 2 Bak', 'kitchen', 'kitchen', 'fnb', 'Frymaster', 'MJ35', 2017, 55, null, null, 2],
            ['Griddle & Salamander', 'kitchen', 'kitchen', 'fnb', 'Roller Grill', 'PL-600', 2017, 40, null],
            ['Rice Cooker Gas 30 L', 'kitchen', 'banquet', 'fnb', 'Rinnai', 'RR-50A', 2019, 12, null, null, 3],
            ['Walk-in Chiller', 'kitchen', 'kitchen', 'fnb', 'Polar', 'WIC-15', 2013, 260, 'fair', [2, 3, 4, 5, 3]],
            ['Walk-in Freezer', 'kitchen', 'kitchen', 'fnb', 'Polar', 'WIF-12', 2016, 300, 'fair', [2, 3, 4, 5, 3]],
            ['Reach-in Chiller 2 Pintu', 'kitchen', 'kitchen', 'fnb', 'GEA', 'RC-1200', 2018, 25, null, null, 4],
            ['Blast Chiller', 'kitchen', 'banquet', 'fnb', 'Irinox', 'EF 10.1', 2021, 160, 'good'],
            ['Ice Machine 150 kg/hari', 'kitchen', 'kitchen', 'fnb', 'Hoshizaki', 'IM-130', 2019, 75, null, null, 2],
            ['Dishwasher Conveyor', 'kitchen', 'kitchen', 'fnb', 'Hobart', 'CS-B', 2017, 390, 'fair', [2, 3, 4, 4, 3]],
            ['Glass Washer', 'kitchen', 'cafe', 'fnb', 'Hobart', 'GXCROI', 2019, 45, null],
            ['Kitchen Exhaust Hood & Fan', 'kitchen', 'kitchen', 'fnb', '—', 'Custom SS', 2013, 250, 'poor', [4, 2, 4, 4, 3]],
            ['Make-up Air Unit Dapur', 'kitchen', 'kitchen', 'fnb', 'Kruger', 'MUA', 2013, 120, 'poor'],
            ['Planetary Mixer 60 L', 'kitchen', 'pastry', 'fnb', 'Hobart', 'HL600', 2016, 140, 'fair'],
            ['Planetary Mixer 20 L', 'kitchen', 'pastry', 'fnb', 'Hobart', 'HL200', 2018, 55, null],
            ['Deck Oven Pastry 3 Dek', 'kitchen', 'pastry', 'fnb', 'Bongard', 'Orion', 2014, 260, 'poor'],
            ['Proofer', 'kitchen', 'pastry', 'fnb', 'Bongard', 'BFC', 2014, 70, 'fair'],
            ['Dough Sheeter', 'kitchen', 'pastry', 'fnb', 'Rondo', 'Econom', 2016, 95, null],
            ['Meat Slicer', 'kitchen', 'kitchen', 'fnb', 'Berkel', 'GL30', 2018, 28, null],
            ['Vegetable Cutter', 'kitchen', 'kitchen', 'fnb', 'Robot Coupe', 'CL50', 2018, 32, null],
            ['Hot Cabinet Banquet', 'kitchen', 'banquet', 'convention', 'Cambro', 'CMBHC', 2018, 25, null, null, 6],
            ['Mesin Kopi Espresso 2 Group', 'kitchen', 'cafe', 'fnb', 'La Marzocco', 'Linea PB', 2022, 140, 'excellent'],
            ['Mesin Kopi Otomatis Executive Lounge', 'kitchen', 'exlounge', 'fnb', 'Jura', 'GIGA X8', 2020, 45, null],
            ['Juice Dispenser Buffet', 'kitchen', 'cafe', 'fnb', 'Ugolini', 'MT2', 2020, 35, null, null, 2],
            ['Chest Freezer Poolside', 'kitchen', 'poolcafe', 'fnb', 'Aqua', 'AQF-500', 2019, 8, null],

            // Laundry
            ['Washer Extractor 60 kg', 'laundry', 'laundry', 'housekeeping', 'Electrolux', 'WH6-60', 2015, 410, 'fair', [2, 3, 3, 4, 3]],
            ['Washer Extractor 33 kg', 'laundry', 'laundry', 'housekeeping', 'Electrolux', 'WH6-33', 2015, 260, 'fair', [2, 3, 3, 4, 3]],
            ['Tumble Dryer 50 kg', 'laundry', 'laundry', 'housekeeping', 'Electrolux', 'T5550', 2015, 190, null, [2, 3, 3, 4, 3], 2],
            ['Flatwork Ironer', 'laundry', 'laundry', 'housekeeping', 'Electrolux', 'IB43316', 2015, 520, 'poor', [3, 3, 3, 4, 3]],
            ['Utility Press & Form Finisher', 'laundry', 'laundry', 'housekeeping', 'Unipress', 'UP-1', 2016, 120, null],
            ['Steam Generator Laundry 100 kW', 'laundry', 'laundry', 'housekeeping', 'Fulton', 'FB-100', 2015, 230, 'fair', [4, 2, 3, 4, 3]],
            ['Mesin Dry Cleaning', 'laundry', 'laundry', 'housekeeping', 'Union', 'HL 8018', 2014, 380, 'poor'],

            // Malibu Dome & audio visual
            ['Sound System Malibu Dome (line array)', 'av', 'dome', 'convention', 'Bose', 'ShowMatch', 2022, 950, 'excellent', [1, 5, 5, 3, 3]],
            ['Mixer Audio Digital', 'av', 'dome', 'convention', 'Yamaha', 'QL5', 2022, 180, 'good', [1, 4, 4, 3, 3]],
            ['Mikrofon Nirkabel 8 Kanal', 'av', 'dome', 'convention', 'Shure', 'ULXD4Q', 2022, 120, 'good'],
            ['LED Videotron P2.9 6×3 m', 'av', 'dome', 'convention', 'Absen', 'A27 Pro', 2022, 1250, 'good', [1, 5, 5, 3, 3]],
            ['Proyektor Laser 20.000 lm', 'av', 'dome', 'convention', 'Epson', 'EB-PU2220B', 2019, 380, 'fair', null, 2],
            ['Layar Motorized 300"', 'av', 'dome', 'convention', 'Screenline', 'Major', 2016, 45, 'poor', null, 2],
            ['Lighting Panggung (moving head & PAR LED)', 'av', 'dome', 'convention', 'Chauvet', 'Rogue', 2019, 320, 'fair'],
            ['Partisi Geser Akustik (Half Malibu)', 'furn', 'dome', 'convention', 'Hufcor', 'Series 7600', 2013, 850, 'fair', [2, 4, 5, 3, 4]],
            ['Karpet Malibu Dome', 'furn', 'dome', 'convention', '—', 'Custom', 2013, 420, 'poor', [1, 4, 4, 2, 3]],
            ['Panggung Portabel Modular', 'furn', 'banquet', 'convention', 'Bil-Jax', 'Stage', 2017, 140, null],
            ['Meja & Kursi Banquet (set 1.200 pax)', 'furn', 'banquet', 'convention', '—', 'Banquet set', 2017, 600, 'fair'],
            ['IPTV / MATV Headend', 'av', 'server', 'it', 'Exterity', 'AvediaServer', 2018, 260, 'fair', [1, 4, 3, 3, 3]],
            ['Sistem Karaoke Keluarga', 'av', 'karaoke', 'recreation', 'Yamaha', 'KMS', 2018, 45, null, null, 3],
            ['Background Music Area Publik', 'av', 'lobby', 'engineering', 'TOA', 'A-2240', 2015, 60, null],

            // TI
            ['Core Switch Jaringan', 'it', 'server', 'it', 'Cisco', 'C9300', 2021, 260, 'good', [1, 4, 4, 5, 3]],
            ['Firewall & Gateway Internet Tamu', 'it', 'server', 'it', 'Fortinet', 'FortiGate 200F', 2022, 180, 'good', [1, 4, 4, 5, 3]],
            ['Wireless LAN Controller', 'it', 'server', 'it', 'Ruckus', 'SmartZone 144', 2022, 140, 'good', [1, 4, 3, 4, 3]],
            ['Server Interface PMS, POS & Kunci', 'it', 'server', 'it', 'Dell', 'R650', 2022, 210, 'good', [1, 4, 5, 5, 3]],
            ['IP-PBX Hotel', 'it', 'server', 'it', 'Panasonic', 'KX-NS1000', 2016, 190, 'fair', [1, 3, 3, 4, 3]],
            ['Encoder Kartu Kunci RFID', 'sec', 'fo', 'front_office', 'Salto', 'Encoder', 2018, 18, null, [1, 4, 4, 4, 2], 2],
            ['Terminal POS Outlet F&B', 'it', 'cafe', 'fnb', 'Posiflex', 'XT-3815', 2021, 14, null, null, 5],
            ['PC Front Office', 'it', 'fo', 'front_office', 'HP', 'ProDesk 400', 2021, 12, null, null, 4],

            // Keamanan
            ['NVR CCTV 64 kanal', 'sec', 'server', 'security', 'Hikvision', 'DS-9664NI', 2020, 85, 'fair', [4, 3, 2, 3, 2]],
            ['Kamera CCTV Area Publik & Perimeter', 'sec', 'public', 'security', 'Hikvision', 'DS-2CD2T47', 2020, 3.5, null, [3, 2, 1, 2, 1], 24],
            ['Barrier Gate Parkir', 'sec', 'parking', 'security', 'Came', 'GARD 4', 2019, 70, 'good', [2, 3, 3, 3, 2], 2],
            ['Walk-through Metal Detector', 'sec', 'prefunction', 'security', 'Garrett', 'PD 6500i', 2018, 85, null],

            // Kolam, fitness & spa
            ['Pompa Filter Kolam 2 HP', 'pool', 'pool', 'recreation', 'Hayward', 'Super Pump 2 HP', 2026, 18, 'excellent', [3, 4, 3, 3, 2], 1, true],
            ['Sand Filter Kolam', 'pool', 'pool', 'recreation', 'Hayward', 'Pro Series', 2016, 35, 'critical', [3, 4, 3, 3, 2], 1, true],
            ['Dosing Klorin Otomatis', 'pool', 'pool', 'recreation', 'Emaux', 'Chlorinator', 2018, 15, null],
            ['Lampu Bawah Air Kolam', 'pool', 'pool', 'recreation', 'Emaux', 'LED', 2018, 2.5, null, null, 8],
            ['Treadmill Komersial', 'pool', 'fitness', 'recreation', 'Life Fitness', 'Integrity', 2018, 95, null, null, 3],
            ['Sepeda Statis & Elliptical', 'pool', 'fitness', 'recreation', 'Life Fitness', 'Integrity', 2018, 60, null, null, 3],
            ['Multi Gym Station', 'pool', 'fitness', 'recreation', 'Life Fitness', 'G7', 2018, 120, null],
            ['Sauna & Steam Generator', 'pool', 'spa', 'recreation', 'Harvia', 'Helix', 2016, 85, 'fair'],
            ['Ranjang Pijat Spa', 'furn', 'spa', 'recreation', '—', 'Massage bed', 2018, 9, null, null, 6],
            ['Playground Kids Corner', 'furn', 'kids', 'recreation', '—', 'Soft play', 2019, 45, null],

            // Interior publik & peralatan operasional
            ['Furnitur Lobby & Lounge', 'furn', 'lobby', 'front_office', '—', 'Custom', 2013, 350, 'poor', [1, 4, 2, 1, 2]],
            ['Furnitur Cafe Delapan', 'furn', 'cafe', 'fnb', '—', 'Custom', 2016, 280, 'fair'],
            ['Furnitur Executive Lounge', 'furn', 'exlounge', 'rooms', '—', 'Custom', 2013, 180, 'poor'],
            ['Scrubber Dryer Lantai', 'eqp', 'store', 'housekeeping', 'Taski', 'swingo 1650', 2018, 140, 'fair'],
            ['Mesin Poles & Kristalisasi Lantai', 'eqp', 'store', 'housekeeping', 'Taski', 'ergodisc', 2019, 35, null, null, 2],
            ['Vacuum Cleaner Wet & Dry', 'eqp', 'store', 'housekeeping', 'Nilfisk', 'VP930', 2021, 6, null, null, 6],
            ['Mesin Las & Peralatan Bengkel', 'eqp', 'workshop', 'engineering', 'Lincoln', 'Invertec', 2016, 45, null],
        ];

        $defaults = [
            'hvac' => [2, 4, 4, 4, 3], 'elec' => [5, 5, 5, 5, 4], 'lift' => [5, 5, 4, 5, 5], 'hotw' => [3, 5, 4, 4, 3], 'plmb' => [2, 4, 3, 4, 3],
            'gas' => [5, 3, 4, 5, 3], 'fire' => [5, 4, 3, 4, 3], 'kitchen' => [2, 3, 4, 4, 3], 'room' => [1, 3, 2, 2, 1], 'furn' => [1, 3, 2, 2, 1],
            'av' => [1, 4, 4, 3, 2], 'it' => [1, 4, 4, 4, 3], 'laundry' => [2, 3, 3, 4, 3], 'pool' => [3, 4, 3, 3, 2], 'sec' => [3, 3, 2, 3, 2], 'eqp' => [1, 1, 1, 3, 1],
        ];

        foreach ($list as $row) {
            [$name, $cat, $loc, $dept, $brand, $model, $year, $value] = $row;
            $profile = $row[8] ?? null;
            $impacts = $row[9] ?? $defaults[$cat];
            $qty = $row[10] ?? 1;
            $confirmed = $row[11] ?? false;

            for ($i = 1; $i <= $qty; $i++) {
                $unitProfile = $profile ?? $this->pick(['excellent', 'good', 'good', 'good', 'fair', 'fair', 'poor']);
                $asset = new Asset([
                    'name' => $qty === 1 ? $name : "{$name} #{$i}",
                    'asset_category_id' => $this->cat[$cat], 'location_id' => $this->loc[$loc]->id, 'department_id' => $this->dept[$dept],
                    'brand' => $brand, 'model' => $model,
                    'serial_no' => $confirmed ? strtoupper(substr(md5($name.$i), 0, 12)) : null,
                    'purchase_date' => CarbonImmutable::create($year, $this->rand(1, 12), $this->rand(1, 28)),
                    'acquisition_cost' => $value * 1_000_000, 'replacement_cost' => round($value * 1_000_000 * 1.35, -5),
                    'verification_status' => $confirmed ? VerificationStatus::Verified : VerificationStatus::Expected,
                    'identification_source' => IdentificationSource::Manual,
                    'verified_by_id' => $confirmed ? $this->users['manajer']->id : null,
                    'verified_at' => $confirmed ? $this->today->subDays($this->rand(20, 150)) : null,
                    'note' => $confirmed
                        ? 'Terkonfirmasi dari laporan manajemen GPCH 2026.'
                        : 'Proyeksi aset hotel bintang 4 sekelas GPCH. Cek di lapangan: lengkapi merek, model, nomor seri, tahun, dan nilai perolehan sebenarnya.',
                    'safety_impact' => $impacts[0], 'guest_impact' => $impacts[1], 'revenue_impact' => $impacts[2],
                    'operational_impact' => $impacts[3], 'replacement_difficulty' => $impacts[4],
                    'status' => match ($unitProfile) { 'critical' => AssetStatus::Repair, 'poor' => AssetStatus::Monitor, default => AssetStatus::Operational },
                ]);
                $this->applyProfile($asset, $unitProfile);
                $asset->save();
            }
        }
    }

    /** Profil kondisi → komponen skor kesehatan yang wajar. */
    protected function applyProfile(Asset $asset, string $profile): void
    {
        [$lo, $hi] = match ($profile) {
            'excellent' => [88, 99], 'good' => [74, 90], 'fair' => [58, 76], 'poor' => [38, 60], default => [15, 42],
        };

        foreach (['physical_condition', 'maintenance_compliance', 'breakdown_reliability', 'remaining_life_score', 'safety_condition', 'operational_reliability', 'documentation_score'] as $field) {
            $asset->{$field} = $this->rand($lo, $hi);
        }
    }

    protected function randomizeHealth(Asset $asset, ?int $cap = null): void
    {
        $roll = $this->rand(1, 100);
        $this->applyProfile($asset, match (true) {
            $cap !== null => 'poor',
            $roll <= 18 => 'excellent',
            $roll <= 70 => 'good',
            $roll <= 90 => 'fair',
            $roll <= 98 => 'poor',
            default => 'critical',
        });

        foreach (['safety_impact', 'guest_impact', 'revenue_impact', 'operational_impact', 'replacement_difficulty'] as $field) {
            $asset->{$field} = $this->rand(1, 3);
        }
    }

    protected function costFor(int $categoryId, string $name = ''): int
    {
        $code = array_search($categoryId, $this->cat, true);

        // Nilai perolehan per unit (juta) untuk aset hasil templat, dibedakan sedikit menurut nama.
        $juta = match (true) {
            str_contains($name, 'TV 65') => $this->rand(18, 25),
            str_contains($name, 'TV 5') => $this->rand(9, 14),
            str_contains($name, 'TV') => $this->rand(4, 8),
            str_contains($name, 'jacuzzi') => $this->rand(45, 70),
            str_contains($name, 'Kunci pintu') => $this->rand(4, 6),
            str_contains($name, 'Panel listrik') => $this->rand(35, 60),
            str_contains($name, 'AC cassette') => $this->rand(20, 28),
            default => match ($code) {
                'hvac' => $this->rand(5, 12), 'room' => $this->rand(2, 7), 'plmb' => $this->rand(3, 9), 'furn' => $this->rand(5, 15),
                'fire' => $this->rand(1, 2), 'sec' => $this->rand(3, 5), 'av' => $this->rand(6, 25), 'it' => $this->rand(2, 6), default => $this->rand(2, 10),
            },
        };

        return $juta * 1_000_000;
    }

    protected function operations(): void
    {
        $assets = Asset::query()->get();
        $major = $assets->where('identification_source', IdentificationSource::Manual)->values();
        $tech = [$this->users['teknisi'], $this->users['manajer']];

        // Rencana pemeliharaan preventif untuk aset utama; sebagian lewat jatuh tempo.
        $frequencies = ['hvac' => 'monthly', 'elec' => 'monthly', 'lift' => 'monthly', 'hotw' => 'monthly', 'gas' => 'monthly', 'fire' => 'quarterly', 'plmb' => 'quarterly', 'kitchen' => 'monthly', 'laundry' => 'quarterly', 'av' => 'semiannual', 'it' => 'quarterly', 'sec' => 'semiannual', 'pool' => 'weekly', 'eqp' => 'semiannual', 'furn' => 'annual'];
        $plans = collect();

        foreach ($major as $i => $asset) {
            $code = array_search($asset->asset_category_id, $this->cat, true);
            $due = $i % 9 === 0 ? $this->today->subDays($this->rand(3, 40)) : $this->today->addDays($this->rand(1, 60));
            $plans->push(PreventivePlan::create([
                'name' => 'PM '.$asset->name,
                'asset_id' => $asset->id,
                'frequency' => $frequencies[$code] ?? 'quarterly',
                'next_due_date' => $due,
                'responsible_id' => $this->pick($tech)->id,
                'checklist' => "1. Periksa kondisi fisik dan kebersihan\n2. Ukur parameter operasi (arus, tekanan, suhu)\n3. Pelumasan dan pengencangan\n4. Uji fungsi dan catat temuan",
            ]));
        }

        // Work order 12 bulan terakhir untuk tren dasbor.
        $corrective = ['Unit tidak dingin, kompresor trip', 'Kebocoran pipa', 'Bunyi abnormal', 'Panel kontrol error', 'Sensor tidak responsif', 'Kabel terbakar', 'Motor overheat', 'Tombol rusak'];
        for ($m = 11; $m >= 0; $m--) {
            $monthStart = $this->today->startOfMonth()->subMonths($m);
            $counts = ['preventive' => $this->rand(7, 13), 'corrective' => $this->rand(3, 9), 'emergency' => $this->rand(0, 3)];

            foreach ($counts as $type => $count) {
                for ($n = 0; $n < $count; $n++) {
                    $planned = $monthStart->addDays($this->rand(0, $monthStart->daysInMonth - 1))->setTime($this->rand(7, 17), 0);
                    if ($planned->greaterThan($this->today->endOfDay())) {
                        $planned = $this->today->setTime(9, 0);
                    }

                    $asset = $type === 'preventive' || $this->rand(1, 3) > 1 ? $major->random() : $assets->random();
                    $isPast = $planned->lessThan($this->today->subDays(3));
                    $state = $isPast ? $this->pick(['closed', 'closed', 'closed', 'closed', 'closed', 'closed', 'verified', 'done']) : $this->pick(['draft', 'assigned', 'progress', 'waiting']);
                    $done = in_array($state, ['done', 'verified', 'closed'], true);
                    $spare = $type === 'preventive' ? $this->rand(0, 15) * 100_000 : $this->rand(5, 120) * 100_000;
                    $vendor = $type === 'emergency' || ($type === 'corrective' && $this->rand(1, 4) === 1) ? $this->rand(10, 250) * 100_000 : 0;

                    WorkOrder::create([
                        'number' => Sequence::next('work_order', $this->company->id, $planned->year),
                        'asset_id' => $asset->id,
                        'preventive_plan_id' => $type === 'preventive' ? $plans->firstWhere('asset_id', $asset->id)?->id : null,
                        'work_type' => $type,
                        'priority' => $type === 'emergency' ? 'urgent' : $this->pick(['low', 'medium', 'medium', 'high']),
                        'state' => $state,
                        'technician_id' => $this->pick($tech)->id,
                        'planned_at' => $planned,
                        'started_at' => $state !== 'draft' && $state !== 'assigned' ? $planned->addHours(1) : null,
                        'completed_at' => $done ? $planned->addHours($this->rand(2, 30)) : null,
                        'description' => $type === 'preventive' ? 'Pemeliharaan preventif terjadwal' : $this->pick($corrective),
                        'root_cause' => $done && $type !== 'preventive' ? $this->pick(['Keausan komponen', 'Kurang pelumasan', 'Fluktuasi tegangan', 'Usia pakai', 'Kesalahan operasi']) : null,
                        'action_taken' => $done ? ($type === 'preventive' ? 'Checklist PM selesai, semua parameter normal' : 'Penggantian komponen dan uji fungsi') : null,
                        'labor_hours' => $done ? $this->rand(1, 12) : 0,
                        'downtime_hours' => $type === 'emergency' ? $this->rand(2, 24) : ($type === 'corrective' ? $this->rand(0, 6) : 0),
                        'sparepart_cost' => $done ? $spare : 0,
                        'vendor_cost' => $done ? $vendor : 0,
                        'other_cost' => $done ? $this->rand(0, 5) * 50_000 : 0,
                    ]);
                }
            }
        }

        // Inspeksi enam bulan terakhir.
        for ($i = 0; $i < 70; $i++) {
            $asset = $this->rand(1, 3) > 1 ? $major->random() : $assets->random();
            $score = max(10, min(100, (int) round($asset->physical_condition + $this->rand(-8, 6))));
            Inspection::create([
                'number' => null,
                'asset_id' => $asset->id,
                'inspected_at' => $this->today->subDays($this->rand(0, 180))->setTime($this->rand(7, 18), $this->rand(0, 59)),
                'inspector_id' => $this->pick($tech)->id,
                'result' => $score >= 70 ? 'pass' : ($score >= 50 ? 'monitor' : 'fail'),
                'condition_score' => $score,
                'finding' => $score >= 70 ? 'Kondisi normal.' : $this->pick(['Getaran berlebih pada bearing', 'Korosi pada rangka', 'Arus motor di atas nominal', 'Rembesan oli', 'Isolasi kabel getas']),
                'recommendation' => $score >= 70 ? null : $this->pick(['Jadwalkan penggantian bearing', 'Lakukan overhaul', 'Pantau mingguan', 'Ajukan CAPEX penggantian']),
                'meter_reading' => $asset->asset_category_id === $this->cat['elec'] ? $this->rand(8000, 26000) : null,
                'meter_unit' => $asset->asset_category_id === $this->cat['elec'] ? 'jam' : null,
            ]);
        }

        // Permintaan pemeliharaan, termasuk dari pindai QR oleh tamu/staf.
        $complaints = ['AC kamar tidak dingin', 'TV tidak menyala', 'Air panas tidak keluar', 'Minibar tidak dingin', 'Kunci kamar tidak terbaca', 'Lampu berkedip', 'Proyektor mati sendiri', 'Bunyi berisik dari unit', 'Kebocoran air di bawah unit'];
        $roomAssets = $assets->where('identification_source', IdentificationSource::Template)->values();

        for ($i = 0; $i < 36; $i++) {
            $viaQr = $this->rand(1, 100) <= 45;
            $requestedAt = $this->today->subDays($this->rand(0, 90))->setTime($this->rand(6, 22), $this->rand(0, 59));
            $state = $requestedAt->lessThan($this->today->subDays(10)) ? $this->pick(['converted', 'converted', 'cancel']) : $this->pick(['new', 'new', 'verified', 'converted']);
            $request = MaintenanceRequest::create([
                'number' => Sequence::next('request', $this->company->id, $requestedAt->year),
                'asset_id' => $roomAssets->random()->id,
                'requested_at' => $requestedAt,
                'reporter_id' => $viaQr ? null : $this->users['teknisi2']->id,
                'reporter_name' => $viaQr ? $this->pick(['Tamu kamar', 'Bu Sari (HK)', 'Pak Ujang (Security)', null]) : null,
                'reporter_contact' => $viaQr ? $this->pick(['Kamar 305', '0812-xxxx-1234', null]) : null,
                'via_qr' => $viaQr,
                'request_type' => $this->rand(1, 10) === 1 ? 'emergency' : 'corrective',
                'priority' => $this->pick(['low', 'medium', 'medium', 'high', 'urgent']),
                'description' => ($viaQr ? '[Laporan QR] ' : '').$this->pick($complaints),
                'state' => in_array($state, ['converted'], true) ? 'verified' : $state,
            ]);

            if ($state === 'converted') {
                $wo = $request->createWorkOrder();
                $wo->forceFill(['planned_at' => $requestedAt->addHours(2)])->save();

                if ($requestedAt->lessThan($this->today->subDays(10))) {
                    $wo->forceFill(['state' => 'closed', 'started_at' => $requestedAt->addHours(3), 'completed_at' => $requestedAt->addHours(6), 'sparepart_cost' => $this->rand(1, 20) * 50_000, 'action_taken' => 'Perbaikan selesai, unit berfungsi normal'])->save();
                }
            }
        }

        // Register risiko tersebar di matriks 5×5.
        $risks = [
            ['Chiller #2 gagal saat beban puncak acara Malibu Dome', 'Chiller #2 300 TR', 4, 5, 'mitigating', 'Overhaul kompresor dan siapkan chiller sewa saat event besar.'],
            ['Panel kontrol baru Lift #1 belum stabil setelah penggantian', 'Lift Penumpang #1', 2, 5, 'mitigating', 'Pantau harian bersama vendor; Lift #2 disiapkan sebagai cadangan.'],
            ['Lift #3 berhenti mendadak, penumpang terjebak', 'Lift Penumpang #3', 3, 5, 'open', 'Ganti tali baja dan modernisasi kontrol seperti Lift #1.'],
            ['Genset #2 gagal start saat PLN padam', 'Genset #2 1.000 kVA', 3, 5, 'mitigating', 'Uji beban bulanan, ganti baterai starter.'],
            ['Boiler #2 gagal, air panas kamar tidak tersedia', 'Boiler Air Panas #2 600 kW', 3, 4, 'open', 'Overhaul burner dan tube; rencanakan penggantian boiler siap CNG.'],
            ['STP tidak memenuhi baku mutu air limbah', 'Sewage Treatment Plant 200 m³/hari', 3, 4, 'open', 'Revitalisasi blower dan media; koordinasi pemanfaatan limbah STP dengan vendor.'],
            ['Kebakaran saluran exhaust dapur', 'Kitchen Exhaust Hood & Fan', 2, 5, 'mitigating', 'Pembersihan ducting triwulanan oleh vendor bersertifikat.'],
            ['Kebocoran gas LPG di dapur', 'Instalasi Gas LPG Dapur (manifold & regulator)', 2, 5, 'mitigating', 'Uji kebocoran bulanan; percepat konversi ke CNG.'],
            ['Sand filter kolam bocor, kolam ditutup saat liburan', 'Sand Filter Kolam', 4, 3, 'open', 'Ganti sand filter; pompa Hayward 2 HP baru sudah terpasang.'],
            ['Pompa jockey bocor, tekanan sprinkler turun', 'Pompa Jockey', 2, 4, 'open', 'Ganti mechanical seal.'],
            ['Ironer merusak linen', 'Flatwork Ironer', 3, 2, 'open', 'Kalibrasi suhu dan ganti padding.'],
            ['Walk-in freezer suhu naik', 'Walk-in Freezer', 2, 3, 'open', 'Pasang alarm suhu jarak jauh.'],
            ['Server interface mati, check-in & kunci kamar terganggu', 'Server Interface PMS, POS & Kunci', 1, 5, 'mitigating', 'PMS sudah VHP Cloud; siapkan server interface cadangan dan UPS terpisah.'],
            ['CCTV area parkir tidak merekam', 'NVR CCTV 64 kanal', 2, 2, 'closed', 'HDD diganti.'],
            ['Cooling tower #1 korosi rangka', 'Cooling Tower #1', 4, 3, 'open', 'Pengecatan ulang dan penggantian panel.'],
            ['Panel LVMDP panas berlebih', 'Panel LVMDP Utama', 1, 4, 'open', 'Thermography rutin.'],
            ['Lift barang aus, operasional BOH terhambat', 'Lift Service / Barang 1.600 kg', 4, 3, 'open', 'Ganti tali baja dan sheave.'],
        ];

        foreach ($risks as [$name, $assetName, $p, $c, $state, $mitigation]) {
            AssetRisk::create([
                'name' => $name, 'asset_id' => $major->firstWhere('name', $assetName)->id, 'probability' => $p, 'consequence' => $c,
                'state' => $state, 'mitigation' => $mitigation, 'owner_id' => $this->users['manajer']->id,
                'target_date' => $this->today->addDays($this->rand(-20, 120)),
            ]);
        }

        // Usulan CAPEX.
        $capex = [
            ['Penggantian Chiller #2 dengan chiller magnetic bearing', 'Chiller #2 300 TR', 'mandatory', 'replace', 4800, [5, 4, 5, 5], 'proposed'],
            ['Penggantian panel kontrol Lift #1', 'Lift Penumpang #1', 'safety', 'overhaul', 650, [5, 5, 3, 5], 'done'],
            ['Modernisasi kontrol Lift #3', 'Lift Penumpang #3', 'safety', 'overhaul', 650, [5, 5, 3, 4], 'proposed'],
            ['Penggantian Boiler Air Panas #2 (siap CNG)', 'Boiler Air Panas #2 600 kW', 'mandatory', 'replace', 850, [5, 3, 3, 4], 'proposed'],
            ['Konversi gas dapur LPG ke CNG', 'Instalasi Gas LPG Dapur (manifold & regulator)', 'efficiency', 'replace', 450, [1, 4, 3, 4], 'approved'],
            ['Revitalisasi STP', 'Sewage Treatment Plant 200 m³/hari', 'mandatory', 'overhaul', 950, [2, 3, 2, 5], 'draft'],
            ['Penggantian sand filter & perapian kolam renang', 'Sand Filter Kolam', 'guest', 'replace', 120, [5, 3, 3, 5], 'proposed'],
            ['Penggantian karpet, layar & AV ruang rapat', null, 'guest', 'replace', 1100, [5, 1, 5, 4], 'proposed'],
            ['Epoxy lantai dapur & pastry', null, 'mandatory', 'overhaul', 380, [2, 4, 3, 4], 'approved'],
            ['Penggantian ironer laundry', 'Flatwork Ironer', 'efficiency', 'replace', 680, [3, 2, 2, 3], 'draft'],
            ['Repaint & refresh 20 kamar', null, 'guest', 'overhaul', 600, [5, 1, 4, 3], 'proposed'],
            ['Retrofit lampu LED area publik', 'Lampu LED Area Publik', 'efficiency', 'replace', 420, [3, 1, 2, 2], 'done'],
            ['Upgrade kamera CCTV ke IP 4K', 'NVR CCTV 64 kanal', 'safety', 'replace', 380, [2, 4, 1, 2], 'rejected'],
        ];

        foreach ($capex as [$name, $assetName, $category, $recommendation, $cost, [$guest, $safety, $revenue, $urgency], $state]) {
            CapexProposal::create([
                'name' => $name, 'asset_id' => $assetName ? $major->firstWhere('name', $assetName)->id : null,
                'category' => $category, 'recommendation' => $recommendation, 'target_year' => $this->today->year + ($state === 'draft' ? 1 : 0),
                'estimated_cost' => $cost * 1_000_000, 'guest_impact' => $guest, 'safety_impact' => $safety, 'revenue_impact' => $revenue,
                'urgency' => $urgency, 'state' => $state, 'justification' => 'Disusun dari hasil inspeksi, register risiko, dan biaya pemeliharaan 12 bulan terakhir.',
            ]);
        }
    }
}
