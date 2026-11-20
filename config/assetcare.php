<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GPCH Asset Care
    |--------------------------------------------------------------------------
    | Pengelolaan aset & pemeliharaan Grand Pasundan Convention Hotel.
    | Nilai di bawah adalah konfigurasi awal usulan (lihat spesifikasi bagian 9),
    | harus disahkan GM & Engineering sebelum produksi.
    */
    'brand_name' => env('ASSETCARE_BRAND_NAME', 'Asset Care'), // nama aplikasi; identitas perusahaan diatur di Pengaturan › Identitas perusahaan
    'brand_tagline' => env('ASSETCARE_BRAND_TAGLINE', 'Pengelolaan aset & pemeliharaan'),
    'property_code' => env('ASSETCARE_PROPERTY_CODE', 'GPCH'),
    'audit_enabled' => (bool) env('ASSETCARE_AUDIT_ENABLED', true),
    'display_timezone' => env('ASSETCARE_DISPLAY_TIMEZONE', 'Asia/Jakarta'),
    'notify_mail' => (bool) env('ASSETCARE_NOTIFY_MAIL', false),
    'seed_demo' => (bool) env('ASSETCARE_SEED_DEMO', false),

    // Domain resolver label QR. Pakai domain milik GPCH agar label tetap berlaku saat hosting pindah.
    'qr_base_url' => rtrim(env('ASSETCARE_QR_BASE_URL') ?: env('APP_URL', 'http://localhost'), '/'),
    // Host yang diterima pembaca QR di aplikasi (selain host qr_base_url). Pisahkan dengan koma.
    'qr_extra_hosts' => array_filter(array_map('trim', explode(',', (string) env('ASSETCARE_QR_EXTRA_HOSTS', '')))),

    // Wajib 2FA untuk peran berikut bila ASSETCARE_MFA_REQUIRED=true (administrator & approver)
    'mfa_required' => (bool) env('ASSETCARE_MFA_REQUIRED', false),
    'mfa_roles' => ['super-admin', 'it-admin', 'owner', 'gm', 'chief-engineering', 'finance'],

    // Kalender kerja untuk P3/P4 (P1/P2 memakai kalender 24 jam)
    'business_calendar' => [
        'days' => [1, 2, 3, 4, 5, 6],      // Senin..Sabtu (ISO: 1 = Senin)
        'start' => '08:00',
        'end' => '17:00',
        'holidays' => array_filter(array_map('trim', explode(',', (string) env('ASSETCARE_HOLIDAYS', '')))), // YYYY-MM-DD
    ],

    // Eskalasi SLA: persentase target untuk peringatan pertama
    'sla_warning_pct' => 80,

    // Jendela kegagalan berulang (hari)
    'repeat_failure_days' => 30,

    // Profil media label (mm). Kolom/baris untuk A4; roll = satu label per halaman.
    'label_profiles' => [
        // Lembar label Tom & Jerry No.103 (32 × 64 mm, 10 label: 2 kolom × 5 baris) ditempel di kertas A4 pembawa.
        // Posisi & jarak dikalibrasi di halaman Cetak label (disimpan per profil), lalu uji dengan lembar uji.
        'tj-103' => [
            'name' => 'Tom & Jerry No.103 · 64 × 32 mm (3 × 4 = 12 label, tempel di A4)', 'type' => 'a4', 'carrier' => true, 'page_w' => 210, 'page_h' => 297,
            'label_w' => 64, 'label_h' => 32, 'cols' => 3, 'rows' => 4, 'radius' => 2.5,
            'margin_top' => 20, 'margin_left' => 6, 'gap_x' => 3, 'gap_y' => 3, 'qr_mm' => 27,
        ],
        'a4-50x30' => [
            'name' => 'A4 · 50 × 30 mm (3 × 9)', 'type' => 'a4', 'page_w' => 210, 'page_h' => 297,
            'label_w' => 50, 'label_h' => 30, 'cols' => 3, 'rows' => 9,
            'margin_top' => 13.5, 'margin_left' => 25, 'gap_x' => 5, 'gap_y' => 0, 'qr_mm' => 23,
        ],
        'a4-75x50' => [
            'name' => 'A4 · 75 × 50 mm (2 × 5)', 'type' => 'a4', 'page_w' => 210, 'page_h' => 297,
            'label_w' => 75, 'label_h' => 50, 'cols' => 2, 'rows' => 5,
            'margin_top' => 23.5, 'margin_left' => 25, 'gap_x' => 10, 'gap_y' => 0, 'qr_mm' => 25,
        ],
        'roll-50x30' => [
            'name' => 'Printer label roll · 50 × 30 mm', 'type' => 'roll', 'page_w' => 50, 'page_h' => 30,
            'label_w' => 50, 'label_h' => 30, 'cols' => 1, 'rows' => 1,
            'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0, 'qr_mm' => 23,
        ],
        'roll-40x30' => [
            'name' => 'Printer label roll · 40 × 30 mm', 'type' => 'roll', 'page_w' => 40, 'page_h' => 30,
            'label_w' => 40, 'label_h' => 30, 'cols' => 1, 'rows' => 1,
            'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0, 'qr_mm' => 20,
        ],
        'roll-50x25' => [
            'name' => 'Printer label roll · 50 × 25 mm', 'type' => 'roll', 'page_w' => 50, 'page_h' => 25,
            'label_w' => 50, 'label_h' => 25, 'cols' => 1, 'rows' => 1,
            'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0, 'qr_mm' => 21,
        ],
        'roll-60x40' => [
            'name' => 'Printer label roll · 60 × 40 mm (mesin & aset besar)', 'type' => 'roll', 'page_w' => 60, 'page_h' => 40,
            'label_w' => 60, 'label_h' => 40, 'cols' => 1, 'rows' => 1,
            'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0, 'qr_mm' => 30,
        ],
        // Pita laminasi (Brother TZe / Epson LK): lebar pita = tinggi label, panjang dipotong printer.
        'tape-24' => [
            'name' => 'Pita laminasi 24 mm · panjang 60 mm (Brother TZe / Epson LK)', 'type' => 'roll', 'page_w' => 60, 'page_h' => 24,
            'label_w' => 60, 'label_h' => 24, 'cols' => 1, 'rows' => 1,
            'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0, 'qr_mm' => 20,
        ],
        'tape-18' => [
            'name' => 'Pita laminasi 18 mm · panjang 50 mm (Brother TZe / Epson LK)', 'type' => 'roll', 'page_w' => 50, 'page_h' => 18,
            'label_w' => 50, 'label_h' => 18, 'cols' => 1, 'rows' => 1,
            'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0, 'qr_mm' => 15,
        ],
        'roll-75x50' => [
            'name' => 'Printer label roll · 75 × 50 mm', 'type' => 'roll', 'page_w' => 75, 'page_h' => 50,
            'label_w' => 75, 'label_h' => 50, 'cols' => 1, 'rows' => 1,
            'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0, 'qr_mm' => 25,
        ],
    ],
    'label_template_version' => 'LBL-1.0',
];
