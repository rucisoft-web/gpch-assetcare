<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\DepreciationClass;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\FiscalGroup;
use App\Models\FixedAssetPolicy;
use App\Models\Property;
use Illuminate\Database\Seeder;

/**
 * Data awal Kebijakan Penyusutan & Register Dokumen Aset (draf FC November 2026).
 * Aman diulang pada setiap rilis: hanya menambah yang belum ada, tidak menimpa angka yang sudah diubah FC.
 */
class FixedAssetSeeder extends Seeder
{
    /** kode => [nama, contoh, umur komersial (bulan) | null, residu %, kelompok fiskal, catatan fiskal, bagian kebijakan, tak berwujud] */
    public const CLASSES = [
        'LND' => ['Tanah', 'Tanah hotel, lahan parkir', null, 0, 'NONE', 'Tidak disusutkan; biaya perpanjangan HGB diamortisasi selama masa hak (konfirmasi konsultan pajak)', '2.6', false],
        'BLD' => ['Bangunan, struktur', 'Struktur gedung, basement', 480, 0, 'BP', 'Bangunan permanen: 20 tahun, atau 40 tahun bila dipilih umur sesuai buku', '3', false],
        'BLD-F' => ['Bangunan, fasad dan atap', 'Fasad, atap, waterproofing', 240, 0, 'BP', 'Bangunan permanen', '3', false],
        'BLD-R' => ['Renovasi dan fit-out', 'Renovasi kamar, lobi, ballroom', 120, 0, 'BP', 'Mengikuti bangunan permanen (perlu dikonfirmasi)', '3, 4.2', false],
        'ME-V' => ['Lift dan eskalator', 'Lift tamu, lift servis', 240, 0, 'K3', 'Cocokkan dengan Lampiran PMK 72/2023', '3', false],
        'ME-H' => ['Mesin utama', 'Chiller, AHU, genset, boiler, trafo, pompa', 180, 0, 'K3', 'Cocokkan dengan Lampiran PMK 72/2023', '3', false],
        'ME-I' => ['Instalasi bangunan', 'Listrik, plumbing, fire alarm, sprinkler, CCTV kabel', 180, 0, 'K3', 'Cocokkan dengan Lampiran PMK 72/2023', '3', false],
        'FFE-W' => ['Perabot kayu dan soft furnishing', 'Tempat tidur, lemari, sofa, meja kamar', 84, 0, 'K1', null, '3', false],
        'FFE-M' => ['Perabot dan peralatan logam', 'Kursi banquet, troli, rak stainless', 96, 0, 'K2', null, '3', false],
        'FFE-S' => ['Karpet, gorden, kasur', 'Karpet koridor, blackout, matras', 60, 0, 'K1', null, '3', false],
        'ELC' => ['Peralatan elektronik kamar', 'AC split, TV, kulkas minibar, brankas', 60, 0, 'K1', 'AC: Kelompok 2 (atur di kategori AC)', '3', false],
        'KIT' => ['Peralatan dapur dan laundry', 'Combi oven, cold room, mesin cuci industri, ironer', 120, 0, 'K2', null, '3', false],
        'MICE' => ['Peralatan banquet dan MICE', 'Sound system, proyektor, LED wall, panggung', 60, 0, 'K1', null, '3', false],
        'ICT' => ['Perangkat TI', 'Komputer, server, jaringan, Wi-Fi, POS, kunci kamar elektronik', 48, 0, 'K1', null, '3', false],
        'SW' => ['Perangkat lunak', 'Lisensi, implementasi sistem yang dikapitalisasi', 48, 0, 'K1', 'Amortisasi harta tak berwujud', '3', true],
        'VEH' => ['Kendaraan', 'Mobil antar-jemput, mobil operasional, minibus', 96, 10, 'K2', null, '3', false],
        'REC' => ['Fasilitas rekreasi', 'Alat gym, peralatan kolam renang, spa', 60, 0, 'K1', null, '3', false],
        'CIP' => ['Aset dalam penyelesaian', 'Proyek renovasi berjalan', null, 0, 'NONE', 'Tidak disusutkan sampai selesai dan dipindah ke kelas aset', '4.4', false],
        // perlakuan khusus bagian 4.1 (stok dasar operating equipment), di luar 18 kelas tabel bagian 3
        'OE' => ['Operating equipment (stok dasar)', 'China, glass, silver: stok dasar saat pembukaan / penambahan outlet', 36, 0, 'K1', 'Penggantian rutin dibebankan, bukan aset', '4.1', false],
        'OE-L' => ['Linen dan uniform (stok dasar)', 'Linen kamar & F&B, uniform: stok dasar', 24, 0, 'K1', 'Penggantian rutin dibebankan, bukan aset', '4.1', false],
    ];

    /** kode kategori operasional => [kelas, kelompok fiskal khusus | null] */
    public const CATEGORY_MAP = [
        'AC-SPLIT' => ['ELC', 'K2'], 'TV' => ['ELC', null], 'MINIBAR' => ['ELC', null], 'TEL' => ['ELC', null], 'BED' => ['FFE-W', null],
        'LAMP' => ['ELC', null], 'SAFE' => ['ELC', null], 'DOORLOCK' => ['ICT', null], 'FURN' => ['FFE-W', null], 'SANIT' => ['ME-I', null],
        'LINEN' => ['OE-L', null],
        'PROJ' => ['MICE', null], 'SCREEN' => ['MICE', null], 'SOUND' => ['MICE', null], 'MIC' => ['MICE', null],
        'CHAIR-B' => ['FFE-M', null], 'TABLE-B' => ['FFE-M', null], 'PART' => ['BLD-R', null], 'PANEL-MICE' => ['ME-I', null],
        'CHILL' => ['KIT', null], 'FREEZ' => ['KIT', null], 'STOVE' => ['KIT', null], 'OVEN' => ['KIT', null], 'MIXER' => ['KIT', null],
        'EXHAUST' => ['KIT', null], 'GREASE' => ['ME-I', null], 'GAS' => ['ME-I', null],
        'BOILER' => ['ME-H', null], 'PUMP' => ['ME-H', null], 'TANK' => ['ME-I', null], 'GENSET' => ['ME-H', null], 'PANEL' => ['ME-I', null],
        'LIFT' => ['ME-V', null], 'HVAC' => ['ME-H', null],
        'POOL-EQ' => ['REC', null], 'GYM-EQ' => ['REC', null],
        'APAR' => ['ME-I', null], 'ALARM' => ['ME-I', null], 'DETECT' => ['ME-I', null], 'HYDRANT' => ['ME-I', null], 'EMLIGHT' => ['ME-I', null],
        'SERVER' => ['ICT', null], 'AP' => ['ICT', null], 'CCTV' => ['ICT', null], 'SWITCH' => ['ICT', null], 'PABX' => ['ICT', null],
        'PC' => ['ICT', null], 'PRINTER' => ['ICT', null], 'ACCESS' => ['ICT', null],
    ];

    /** kategori tambahan agar setiap kelas kebijakan punya kategori register: kode => [nama, kelompok, kritikalitas, mode, kelas] */
    public const EXTRA_CATEGORIES = [
        'LAND' => ['Tanah', 'bangunan', 'medium', 'individual', 'LND'],
        'BUILDING' => ['Bangunan / struktur gedung', 'bangunan', 'critical', 'individual', 'BLD'],
        'FACADE' => ['Fasad, atap & waterproofing', 'bangunan', 'high', 'individual', 'BLD-F'],
        'RENOV' => ['Renovasi & fit-out', 'bangunan', 'medium', 'individual', 'BLD-R'],
        'ESCAL' => ['Eskalator', 'mep', 'critical', 'individual', 'ME-V'],
        'TRAFO' => ['Trafo', 'mep', 'critical', 'individual', 'ME-H'],
        'CARPET' => ['Karpet', 'kamar', 'low', 'individual', 'FFE-S'],
        'CURTAIN' => ['Gorden & blackout', 'kamar', 'low', 'individual', 'FFE-S'],
        'MATTRESS' => ['Kasur / matras', 'kamar', 'low', 'individual', 'FFE-S'],
        'TROLLEY' => ['Troli & rak stainless', 'mice', 'low', 'individual', 'FFE-M'],
        'LAUNDRY' => ['Mesin laundry industri', 'kitchen', 'high', 'individual', 'KIT'],
        'LEDWALL' => ['LED wall & panggung', 'mice', 'medium', 'individual', 'MICE'],
        'POS' => ['POS & perangkat kasir', 'it', 'medium', 'individual', 'ICT'],
        'SOFTWARE' => ['Perangkat lunak / lisensi', 'it', 'medium', 'individual', 'SW'],
        'VEHICLE' => ['Kendaraan', 'lainnya', 'medium', 'individual', 'VEH'],
        'SPA-EQ' => ['Peralatan spa', 'publik', 'low', 'individual', 'REC'],
        'CIP' => ['Aset dalam penyelesaian (proyek)', 'bangunan', 'medium', 'individual', 'CIP'],
        'OE-CGS' => ['China, glass & silver (stok dasar)', 'kitchen', 'low', 'quantity', 'OE'],
        // dari inventaris departemen (data awal 2026); elemen ke-6 = interval PM default (hari)
        'FURN-OFF' => ['Furnitur kantor & area umum', 'lainnya', 'low', 'individual', 'FFE-W'],
        'OFFICE-EQ' => ['Peralatan kantor (fotokopi, faks, mesin hitung uang)', 'it', 'low', 'individual', 'ICT'],
        'UPS' => ['UPS & stabilizer', 'it', 'high', 'individual', 'ICT', 180],
        'FAN' => ['Exhaust & blower fan', 'mep', 'medium', 'individual', 'ME-I', 90],
        'WATER' => ['Filter & pengolahan air', 'mep', 'medium', 'individual', 'ME-I', 90],
        'KIT-EQ' => ['Peralatan dapur stainless (meja kerja, sink, rak)', 'kitchen', 'low', 'individual', 'FFE-M', 365],
        'TOOLS' => ['Peralatan kerja teknik', 'mep', 'low', 'individual', 'FFE-M'],
        'AV-ACC' => ['Kabel & aksesori audio-visual', 'mice', 'low', 'quantity', 'MICE'],
        'DECOR' => ['Dekorasi, lukisan & cermin', 'lainnya', 'low', 'individual', 'FFE-W'],
        'APPL' => ['Peralatan elektronik umum (dispenser, kulkas, kipas, vacuum)', 'lainnya', 'low', 'individual', 'ELC'],
        'GAS-CYL' => ['Tabung gas LPG', 'kitchen', 'high', 'quantity', 'FFE-M', 30],
        'MED' => ['Peralatan klinik', 'lainnya', 'medium', 'individual', 'FFE-M', 365],
        'RADIO' => ['Handy talky & radio', 'it', 'medium', 'individual', 'ICT'],
        'KARAOKE' => ['Sistem karaoke & hiburan', 'publik', 'low', 'individual', 'MICE', 180],
    ];

    /** [kode, nama, kategori arsip, kelompok tampilan, petunjuk masa berlaku, punya tanggal berakhir, pengingat pertama (hari), flag, klasifikasi, PIC] */
    public const DOCUMENT_TYPES = [
        // 2.1 Tanah & bangunan
        ['LGL-TNH-01', 'Sertifikat HGB atau SHM', 'LGL', 'Tanah & bangunan', 'HGB sesuai tanggal di sertifikat; SHM tanpa batas', true, 1095, null, 'terbatas', 'Legal'],
        ['LGL-TNH-02', 'Akta jual beli / dasar perolehan tanah', 'LGL', 'Tanah & bangunan', 'Tanpa batas', false, 90, null, 'terbatas', 'Legal'],
        ['LGL-TNH-03', 'Sertifikat Hak Tanggungan & perjanjian kredit', 'LGL', 'Tanah & bangunan', 'Sampai kredit lunas', true, 90, null, 'rahasia', 'FC'],
        ['LGL-TNH-04', 'SPPT & bukti bayar PBB', 'LGL', 'Tanah & bangunan', 'Tahunan', true, 60, null, 'internal', 'Accounting'],
        ['LGL-BGN-01', 'IMB (lama) atau PBG', 'LGL', 'Tanah & bangunan', 'Tanpa batas selama bangunan tidak diubah', false, 90, null, 'terbatas', 'Legal'],
        ['LGL-BGN-02', 'PBG perubahan (renovasi / penambahan)', 'LGL', 'Tanah & bangunan', 'Tanpa batas', false, 90, null, 'terbatas', 'Chief Engineering'],
        ['LGL-BGN-03', 'Sertifikat Laik Fungsi (SLF)', 'LGL-IZN', 'Tanah & bangunan', 'Umumnya 5 tahun (cek)', true, 180, null, 'internal', 'Chief Engineering'],
        ['LGL-BGN-04', 'Gambar as-built arsitektur, struktur & M&E', 'PML', 'Tanah & bangunan', 'Tanpa batas; perbarui setiap renovasi', false, 90, null, 'internal', 'Chief Engineering'],
        ['LGL-BGN-05', 'Laporan pengkajian teknis bangunan (perpanjangan SLF)', 'LGL-IZN', 'Tanah & bangunan', 'Mengikuti SLF', true, 180, null, 'internal', 'Chief Engineering'],
        // 2.2 Badan usaha & izin hotel
        ['LGL-BU-01', 'Akta pendirian & perubahan, SK Kemenkumham', 'LGL', 'Badan usaha & izin hotel', 'Tanpa batas', false, 90, null, 'terbatas', 'Legal'],
        ['LGL-BU-02', 'NIB & Sertifikat Standar usaha hotel (OSS RBA)', 'LGL', 'Badan usaha & izin hotel', 'Selama usaha berjalan', false, 90, null, 'internal', 'Legal'],
        ['LGL-BU-03', 'NPWP & SKT, pengukuhan PKP', 'LGL', 'Badan usaha & izin hotel', 'Tanpa batas', false, 90, null, 'internal', 'Accounting'],
        ['LGL-BU-04', 'NPWPD & pengukuhan wajib pajak PBJT', 'LGL', 'Badan usaha & izin hotel', 'Tanpa batas', false, 90, null, 'internal', 'Accounting'],
        ['LGL-IZN-01', 'Sertifikat usaha hotel / klasifikasi bintang', 'LGL-IZN', 'Badan usaha & izin hotel', 'Sesuai sertifikat (cek, lazimnya 3 tahun)', true, 120, null, 'internal', 'GM'],
        ['LGL-IZN-02', 'Sertifikat Laik Higiene Sanitasi (SLHS)', 'LGL-IZN', 'Badan usaha & izin hotel', 'Sesuai sertifikat (cek)', true, 120, null, 'internal', 'Executive Chef'],
        ['LGL-IZN-03', 'Sertifikat halal per outlet / produk', 'LGL-IZN', 'Badan usaha & izin hotel', 'Sesuai sertifikat & aturan BPJPH (cek)', true, 90, null, 'internal', 'Executive Chef'],
        ['LGL-IZN-04', 'Izin penjualan minuman beralkohol', 'LGL-IZN', 'Badan usaha & izin hotel', 'Sesuai izin (lazimnya tahunan)', true, 90, null, 'internal', 'Director of F&B'],
        ['LGL-IZN-05', 'Izin reklame & papan nama', 'LGL-IZN', 'Badan usaha & izin hotel', 'Tahunan', true, 90, null, 'internal', 'Marketing'],
        ['LGL-IZN-06', 'Izin Stasiun Radio (ISR) untuk HT', 'LGL-IZN', 'Badan usaha & izin hotel', 'Tahunan', true, 90, null, 'internal', 'Chief Security'],
        ['LGL-IZN-07', 'Lisensi royalti musik & lagu (LMKN)', 'LGL-IZN', 'Badan usaha & izin hotel', 'Tahunan', true, 90, null, 'internal', 'Director of F&B'],
        // 2.3 K3
        ['K3-01', 'Izin pakai & riksa uji lift / eskalator (Disnaker)', 'LGL-IZN', 'K3 & kelaikan alat', 'Riksa uji berkala, lazimnya tahunan (cek)', true, 60, 'k3', 'internal', 'Chief Engineering'],
        ['K3-02', 'Riksa uji genset & instalasi listrik, SLO', 'LGL-IZN', 'K3 & kelaikan alat', 'Sesuai sertifikat (cek)', true, 60, 'k3', 'internal', 'Chief Engineering'],
        ['K3-03', 'Riksa uji boiler & bejana tekan', 'LGL-IZN', 'K3 & kelaikan alat', 'Sesuai pengesahan (cek)', true, 60, 'k3', 'internal', 'Chief Engineering'],
        ['K3-04', 'Riksa uji instalasi penyalur petir', 'LGL-IZN', 'K3 & kelaikan alat', 'Berkala (cek)', true, 60, 'k3', 'internal', 'Chief Engineering'],
        ['K3-05', 'Sertifikat / rekomendasi sistem proteksi kebakaran (Damkar)', 'LGL-IZN', 'K3 & kelaikan alat', 'Sesuai rekomendasi (cek)', true, 60, 'k3', 'internal', 'Chief Engineering'],
        ['K3-06', 'Kartu inspeksi & isi ulang APAR', 'LGL-IZN', 'K3 & kelaikan alat', 'Sesuai jadwal, lazimnya tahunan', true, 60, 'k3', 'internal', 'Chief Security'],
        ['K3-07', 'Tera ulang timbangan & alat ukur (Metrologi)', 'LGL-IZN', 'K3 & kelaikan alat', 'Tahunan', true, 60, null, 'internal', 'Purchasing'],
        ['K3-08', 'Lisensi K3 operator & teknisi', 'LGL-IZN', 'K3 & kelaikan alat', 'Sesuai lisensi', true, 60, null, 'internal', 'HR'],
        // 2.4 Lingkungan
        ['LGL-LH-01', 'Dokumen lingkungan (UKL-UPL / AMDAL) & persetujuan', 'LGL-IZN', 'Lingkungan', 'Tanpa batas selama tidak ada perubahan usaha', false, 90, null, 'internal', 'Chief Engineering'],
        ['LGL-LH-02', 'Laporan pelaksanaan RKL-RPL / UKL-UPL', 'LGL-IZN', 'Lingkungan', 'Per semester', true, 30, null, 'internal', 'Chief Engineering'],
        ['LGL-LH-03', 'Persetujuan teknis & SLO pembuangan air limbah (IPAL/STP)', 'LGL-IZN', 'Lingkungan', 'Sesuai persetujuan (cek)', true, 90, null, 'internal', 'Chief Engineering'],
        ['LGL-LH-04', 'Kontrak pengangkut limbah B3 & manifest', 'LGL-KTR', 'Lingkungan', 'Kontrak tahunan', true, 60, 'contract', 'internal', 'Chief Engineering'],
        ['LGL-LH-05', 'Izin pengusahaan air tanah (SIPA)', 'LGL-IZN', 'Lingkungan', 'Sesuai izin (cek)', true, 90, null, 'internal', 'Chief Engineering'],
        ['LGL-LH-06', 'Hasil uji kualitas air bersih, kolam & limbah', 'LGL-IZN', 'Lingkungan', 'Berkala sesuai jadwal', true, 30, null, 'internal', 'Chief Engineering'],
        // 2.5 Perolehan aset
        ['AST-01', 'Purchase order & persetujuan pembelian', 'AST', 'Perolehan aset', 'Tanpa batas', false, 90, null, 'internal', 'Purchasing'],
        ['AST-02', 'Faktur pemasok & faktur pajak', 'AST', 'Perolehan aset', 'Tanpa batas', false, 90, null, 'internal', 'Accounting'],
        ['AST-03', 'Berita acara serah terima (BAST) & uji coba', 'AST', 'Perolehan aset', 'Tanpa batas', false, 90, null, 'internal', 'Chief Engineering'],
        ['AST-04', 'Kartu garansi', 'AST', 'Perolehan aset', 'Sesuai garansi', true, 60, null, 'internal', 'Chief Engineering'],
        ['AST-05', 'Manual operasi & spesifikasi teknis', 'PML', 'Perolehan aset', 'Tanpa batas', false, 90, null, 'internal', 'Chief Engineering'],
        ['AST-06', 'Foto aset & label tag', 'AST', 'Perolehan aset', 'Tanpa batas', false, 90, null, 'internal', 'Asset Controller'],
        ['AST-07', 'BPKB, STNK, pajak kendaraan, uji KIR', 'AST', 'Perolehan aset', 'STNK 5 tahun, pajak tahunan, KIR berkala', true, 60, null, 'terbatas', 'GA'],
        ['AST-08', 'Berita acara penghapusan, penjualan & stock opname aset', 'AST', 'Perolehan aset', 'Tanpa batas', false, 90, null, 'internal', 'FC'],
        // 2.6 Kontrak pemeliharaan & vendor
        ['KTR-SVC', 'Kontrak servis / pemeliharaan vendor', 'LGL-KTR', 'Kontrak pemeliharaan & vendor', 'Sesuai kontrak; perhatikan batas pemberitahuan', true, 90, 'contract', 'terbatas', 'Chief Engineering'],
        ['PML-RPT', 'Laporan servis / kunjungan vendor', 'PML', 'Pemeliharaan', 'Tanpa batas', false, 90, null, 'internal', 'Chief Engineering'],
        // 2.7 Perangkat lunak & TI
        ['TI-01', 'Perjanjian lisensi perangkat lunak', 'LGL-KTR', 'Perangkat lunak & TI', 'Perpetual atau langganan tahunan', true, 60, 'contract', 'internal', 'IT Manager'],
        ['TI-02', 'Langganan SaaS (channel manager, booking engine, e-mail, WhatsApp API)', 'LGL-KTR', 'Perangkat lunak & TI', 'Bulanan atau tahunan', true, 30, 'contract', 'internal', 'IT Manager'],
        ['TI-03', 'Kontrak pengembangan & pemeliharaan sistem (termasuk kode sumber)', 'LGL-KTR', 'Perangkat lunak & TI', 'Sesuai kontrak', true, 90, 'contract', 'terbatas', 'IT Manager'],
        ['TI-04', 'Kontrak hosting / VPS & SLA', 'LGL-KTR', 'Perangkat lunak & TI', 'Tahunan', true, 60, 'contract', 'internal', 'IT Manager'],
        ['TI-05', 'Perjanjian pemrosesan data pribadi (DPA) sesuai UU PDP', 'LGL-KTR', 'Perangkat lunak & TI', 'Mengikuti kontrak utama', true, 60, 'contract', 'terbatas', 'IT Manager'],
        ['TI-06', 'Nama domain & sertifikat SSL', 'LGL-KTR', 'Perangkat lunak & TI', 'Tahunan', true, 30, null, 'internal', 'IT Manager'],
        ['TI-07', 'Kontrak internet ISP utama & cadangan', 'LGL-KTR', 'Perangkat lunak & TI', 'Tahunan', true, 60, 'contract', 'internal', 'IT Manager'],
        ['TI-08', 'Kontrak dukungan interface (door lock, PABX, IPTV, Wi-Fi)', 'LGL-KTR', 'Perangkat lunak & TI', 'Tahunan', true, 60, 'contract', 'internal', 'IT Manager'],
        ['TI-09', 'Catatan lisensi sumber terbuka', 'LGL-KTR', 'Perangkat lunak & TI', 'Tanpa batas', false, 90, null, 'internal', 'IT Manager'],
        ['TI-10', 'Perjanjian kerahasiaan (NDA) vendor TI', 'LGL-KTR', 'Perangkat lunak & TI', 'Sesuai perjanjian', true, 60, null, 'terbatas', 'Legal'],
        // 2.8 Asuransi
        ['ASR-01', 'Polis Property / Industrial All Risk (termasuk gempa bumi)', 'LGL-KTR', 'Asuransi', 'Tahunan', true, 60, 'insurance', 'terbatas', 'FC'],
        ['ASR-02', 'Polis Business Interruption', 'LGL-KTR', 'Asuransi', 'Tahunan', true, 60, 'insurance', 'terbatas', 'FC'],
        ['ASR-03', 'Polis Machinery Breakdown', 'LGL-KTR', 'Asuransi', 'Tahunan', true, 60, 'insurance', 'terbatas', 'FC'],
        ['ASR-04', 'Polis Public Liability', 'LGL-KTR', 'Asuransi', 'Tahunan', true, 60, 'insurance', 'terbatas', 'FC'],
        ['ASR-05', 'Polis asuransi kendaraan', 'LGL-KTR', 'Asuransi', 'Tahunan', true, 60, 'insurance', 'terbatas', 'FC'],
        ['ASR-06', 'Polis Money Insurance', 'LGL-KTR', 'Asuransi', 'Tahunan', true, 60, 'insurance', 'terbatas', 'FC'],
        ['ASR-07', 'Polis Electronic Equipment', 'LGL-KTR', 'Asuransi', 'Tahunan', true, 60, 'insurance', 'terbatas', 'FC'],
        // 2.9 Kontrak lain
        ['KTR-SEWA', 'Kontrak sewa ruang / atap kepada penyewa', 'LGL-KTR', 'Kontrak lain', 'Sesuai kontrak', true, 90, 'contract', 'terbatas', 'Legal'],
        ['KTR-LEASE', 'Sewa guna usaha (leasing) kendaraan / peralatan', 'LGL-KTR', 'Kontrak lain', 'Sesuai kontrak', true, 90, 'contract', 'terbatas', 'FC'],
        ['KTR-KREDIT', 'Perjanjian kredit bank yang menjaminkan aset', 'LGL-KTR', 'Kontrak lain', 'Sampai lunas', true, 90, 'contract', 'rahasia', 'FC'],
    ];

    /** Dokumen wajib per kelas (bagian 3.2): kelas => [kode jenis => [wajib?, syarat]] */
    public const REQUIREMENTS = [
        'LND' => ['LGL-TNH-01' => [true, null], 'LGL-TNH-02' => [true, null], 'LGL-TNH-04' => [true, 'tahun berjalan']],
        'BLD' => ['LGL-BGN-01' => [true, null], 'LGL-BGN-03' => [true, 'masih berlaku'], 'LGL-BGN-04' => [true, null], 'ASR-01' => [true, null]],
        'BLD-R' => ['LGL-BGN-02' => [false, 'bila mengubah struktur atau fungsi'], 'KTR-SVC' => [false, 'kontrak kontraktor'], 'AST-03' => [true, null], 'LGL-BGN-04' => [true, 'gambar as-built baru']],
        'ME-V' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'K3-01' => [true, 'masih berlaku'], 'KTR-SVC' => [true, 'masih berlaku'], 'AST-05' => [true, null]],
        'ME-H' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-05' => [true, null], 'K3-02' => [false, 'genset / instalasi listrik'], 'K3-03' => [false, 'boiler / bejana tekan'], 'KTR-SVC' => [true, null], 'ASR-03' => [true, null]],
        'ME-I' => ['LGL-BGN-04' => [true, null], 'K3-02' => [true, 'SLO listrik'], 'K3-05' => [true, null], 'K3-04' => [true, null]],
        'FFE-W' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-06' => [true, null], 'AST-04' => [false, 'bila ada']],
        'FFE-M' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-06' => [true, null], 'AST-04' => [false, 'bila ada']],
        'FFE-S' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-06' => [true, null], 'AST-04' => [false, 'bila ada']],
        'ELC' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-06' => [true, null], 'AST-04' => [false, 'bila ada']],
        'MICE' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-06' => [true, null], 'AST-04' => [false, 'bila ada']],
        'REC' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-06' => [true, null], 'AST-04' => [false, 'bila ada']],
        'KIT' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-05' => [true, null], 'AST-04' => [true, null], 'KTR-SVC' => [false, 'bila ada']],
        'ICT' => ['AST-02' => [true, null], 'AST-03' => [true, null], 'AST-04' => [true, null]],
        'SW' => ['TI-01' => [true, 'perjanjian lisensi atau langganan'], 'TI-03' => [false, 'kontrak implementasi'], 'TI-05' => [false, 'bila menyimpan data pribadi']],
        'VEH' => ['AST-07' => [true, null], 'ASR-05' => [true, null]],
        'CIP' => ['KTR-SVC' => [true, 'kontrak proyek'], 'LGL-BGN-02' => [false, 'bila perlu'], 'AST-03' => [false, 'saat selesai']],
    ];

    public function run(): void
    {
        foreach ([
            ['K1', 'Kelompok 1 (bukan bangunan)', 48, 'Garis lurus 25%'],
            ['K2', 'Kelompok 2 (bukan bangunan)', 96, 'Garis lurus 12,5%'],
            ['K3', 'Kelompok 3 (bukan bangunan)', 192, 'Garis lurus 6,25%; jenis yang tidak tercantum di Lampiran PMK masuk kelompok ini'],
            ['K4', 'Kelompok 4 (bukan bangunan)', 240, 'Garis lurus 5%'],
            ['BP', 'Bangunan permanen', 240, '20 tahun (5%), atau umur sesuai buku bila > 20 tahun; diatur di Kebijakan aset tetap'],
            ['BTP', 'Bangunan tidak permanen', 120, 'Garis lurus 10%'],
            ['NONE', 'Tidak disusutkan', null, 'Tanah, aset dalam penyelesaian'],
        ] as $i => [$code, $name, $life, $note]) {
            FiscalGroup::firstOrCreate(['code' => $code], ['name' => $name, 'life_months' => $life, 'note' => $note, 'sort' => $i + 1]);
        }

        foreach (Property::all() as $property) {
            FixedAssetPolicy::current($property->id);

            $sort = 0;
            $classIds = [];
            foreach (self::CLASSES as $code => [$name, $examples, $life, $residual, $fiscal, $fiscalNote, $ref, $intangible]) {
                $classIds[$code] = DepreciationClass::withoutGlobalScopes()->firstOrCreate(['property_id' => $property->id, 'code' => $code], [
                    'name' => $name, 'examples' => $examples, 'is_depreciable' => $life !== null, 'is_intangible' => $intangible,
                    'commercial_life_months' => $life, 'residual_pct' => $residual, 'fiscal_group_code' => $fiscal, 'fiscal_note' => $fiscalNote,
                    'policy_ref' => $ref, 'sort' => ++$sort,
                ])->id;
            }

            foreach (self::EXTRA_CATEGORIES as $code => $def) {
                [$name, $group, $crit, $mode, $class] = $def;
                AssetCategory::withoutGlobalScopes()->firstOrCreate(['property_id' => $property->id, 'code' => $code], [
                    'name' => $name, 'group' => $group, 'default_criticality' => $crit, 'tracking_mode' => $mode, 'is_active' => true,
                    'depreciation_class_id' => $classIds[$class], 'default_pm_interval_days' => $def[5] ?? null,
                ]);
            }
            // pemetaan kategori lama hanya bila belum diatur (tidak menimpa keputusan FC)
            foreach (self::CATEGORY_MAP as $code => [$class, $override]) {
                $cat = AssetCategory::withoutGlobalScopes()->where(['property_id' => $property->id, 'code' => $code])->whereNull('depreciation_class_id')->first();
                $cat?->update(['depreciation_class_id' => $classIds[$class], 'fiscal_group_override' => $cat->fiscal_group_override ?? $override]);
            }

            foreach (self::DOCUMENT_TYPES as $i => [$code, $name, $archive, $section, $hint, $expiry, $first, $flag, $classification, $pic]) {
                DocumentType::firstOrCreate(['code' => $code], [
                    'name' => $name, 'archive_category' => $archive, 'section' => $section, 'validity_hint' => $hint, 'has_expiry' => $expiry,
                    'first_reminder_days' => $first, 'is_k3' => $flag === 'k3', 'is_insurance' => $flag === 'insurance', 'is_contract' => $flag === 'contract',
                    'default_classification' => $classification, 'default_pic_role' => $pic, 'retention' => self::retention($archive), 'sort' => $i + 1,
                ]);
            }
            $types = DocumentType::pluck('id', 'code');
            foreach (self::REQUIREMENTS as $class => $docs) {
                foreach ($docs as $code => [$mandatory, $condition]) {
                    DocumentRequirement::firstOrCreate(['depreciation_class_id' => $classIds[$class], 'document_type_id' => $types[$code]], ['is_mandatory' => $mandatory, 'condition' => $condition]);
                }
            }
        }
    }

    /** Retensi usulan Lampiran A SRS ARSIP (bagian 5.1). */
    public static function retention(string $archive): string
    {
        return match ($archive) {
            'LGL' => 'Permanen',
            'LGL-IZN' => 'Berlaku + 10 tahun',
            'LGL-KTR' => 'Berakhir + 10 tahun',
            'AST' => '10 tahun setelah aset dilepas',
            'PML' => 'Umur aset + 5 tahun',
            default => '—',
        };
    }
}
