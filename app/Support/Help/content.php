<?php

/*
 * Isi Bantuan per halaman (tombol "Bantuan" di pojok kanan atas).
 * Kunci = slug resource Filament (mis. "assets") atau "pages.<slug>" untuk halaman khusus.
 * - konsep  : untuk apa halaman ini & bagaimana ia terhubung dengan proses lain
 * - langkah : cara pengisian / pemakaian yang disarankan
 * - fields  : penjelasan per field (nama field form). Field yang tidak dijelaskan di sini tetap tampil
 *             otomatis dari form (label, wajib/tidak, teks bantuan, pilihan).
 * - tips    : catatan penting
 * - shots   : tangkapan layar di public/help/
 */

return [

    // ================================================================ UMUM
    'pages.beranda' => [
        'title' => 'Beranda',
        'konsep' => [
            'Beranda menampilkan pekerjaan yang relevan dengan peran Anda: tugas work order, laporan yang perlu ditriase, persetujuan tertunda, dan ringkasan KPI bila Anda berhak melihat dashboard.',
            'Gunakan Beranda sebagai titik awal setiap hari. Angka berwarna merah menandakan sesuatu yang sudah melewati target dan perlu ditindaklanjuti.',
        ],
        'langkah' => [
            'Periksa kotak "Tugas saya" dan buka work order yang ditugaskan kepada Anda.',
            'Bila Anda penanggung jawab triase, buka laporan kerusakan yang belum ditriase.',
            'Klik tautan "lewat target" untuk melihat work order yang melewati SLA.',
            'Tombol Scan QR dan Lapor kerusakan tersedia untuk pekerjaan lapangan dari ponsel.',
        ],
        'shots' => [['beranda.jpg', 'Beranda untuk Chief Engineer']],
    ],

    'pages.dashboard-pimpinan' => [
        'title' => 'Dashboard pimpinan',
        'konsep' => [
            'Ringkasan untuk pengambilan keputusan: berapa aset yang dimiliki, bagaimana kondisinya, seberapa jauh verifikasi fisik berjalan, beban dan tren pemeliharaan, biaya, serta dokumen legalitas yang akan berakhir.',
            'Semua kartu dan grafik dapat diklik. Klik akan membuka daftar data yang sudah terfilter (mis. aset kondisi D, WO minggu tertentu, dokumen kedaluwarsa), sehingga pimpinan dapat langsung menelusuri sampai ke kartu aset atau work order.',
        ],
        'langkah' => [
            'Baca baris kartu KPI di atas: angka merah/kuning menunjukkan area yang perlu perhatian.',
            'Klik irisan "D — perlu kajian penggantian" untuk melihat kandidat penggantian aset.',
            'Pada "Tren work order", bila garis Masuk terus di atas Diselesaikan, antrean pekerjaan menumpuk; klik titik untuk melihat WO minggu itu.',
            'Pada "Aset paling sering rusak", klik batang untuk membuka kartu aset dan riwayat perbaikannya; pertimbangkan penggantian atau kontrak servis.',
            'Gunakan "Progres verifikasi per departemen" untuk memantau implementasi: target semua aset terverifikasi & berlabel.',
        ],
        'tips' => [
            'Biaya hanya tampil untuk pengguna yang berhak melihat data keuangan.',
            'Angka dihitung langsung dari data saat halaman dibuka; muat ulang untuk data terbaru.',
        ],
        'shots' => [['dashboard-pimpinan.jpg', 'Dashboard pimpinan']],
    ],

    'pages.scan-qr' => [
        'title' => 'Scan QR aset',
        'konsep' => [
            'Membaca label QR aset atau kelompok jumlah dengan kamera ponsel. Hasil scan membuka kartu aset, tempat Anda dapat melihat data, melapor kerusakan, atau memverifikasi fisik.',
            'Scan biasa tidak mengubah status pemasangan label; konfirmasi pemasangan dilakukan di halaman Konfirmasi pemasangan label.',
        ],
        'langkah' => [
            'Izinkan akses kamera saat diminta browser.',
            'Arahkan kamera ke label QR sampai terbaca (atau ketik kode aset).',
            'Di kartu aset, pilih tindakan: Lapor kerusakan, Verifikasi fisik, atau lihat riwayat.',
        ],
        'tips' => ['Kamera hanya berfungsi lewat HTTPS. Bila kamera tidak muncul, periksa izin kamera di pengaturan browser.'],
        'shots' => [['scan-qr.jpg', 'Halaman Scan QR']],
    ],

    'pages.panduan-implementasi' => [
        'title' => 'Panduan implementasi',
        'konsep' => [
            'Urutan langkah menerapkan Asset Care dari awal: persiapan, struktur & referensi, data awal, identifikasi fisik & label, operasional pemeliharaan, aset tetap & dokumen, sampai pemantauan.',
            'Setiap langkah menampilkan status berdasarkan data nyata di aplikasi (mis. berapa aset sudah terverifikasi), sehingga tim aset dapat melihat sejauh mana implementasi berjalan.',
        ],
        'langkah' => [
            'Kerjakan fase berurutan. Langkah bertanda hijau sudah memenuhi syarat minimal.',
            'Klik tombol "Buka" untuk masuk ke halaman terkait, dan tombol Bantuan di halaman itu untuk penjelasan field.',
        ],
    ],

    // ================================================================ PEMELIHARAAN
    'service-requests' => [
        'title' => 'Laporan kerusakan',
        'konsep' => [
            'Pintu masuk semua keluhan dan kerusakan dari departemen (HK, FO, F&B, dll.). Laporan dibuat cepat dari ponsel, idealnya dengan scan QR aset, lalu ditriase Engineering dan dijadikan work order.',
            'Target respons SLA mulai dihitung saat server menerima laporan. Prioritas diusulkan otomatis dari dampak dan tanda keselamatan.',
        ],
        'langkah' => [
            'Klik "Buat" (atau scan QR aset lalu "Lapor kerusakan").',
            'Pilih aset bila diketahui; bila tidak, pilih lokasi.',
            'Pilih kategori, tulis gejala sejelas mungkin, pilih dampak operasional.',
            'Centang "Ada bahaya keselamatan" untuk listrik terbuka, bau gas, alarm, dsb. Laporan otomatis menjadi P1.',
            'Lampirkan foto bila ada, lalu simpan. Engineering menerima notifikasi.',
            'Petugas triase: buka laporan → Triase (ubah prioritas bila perlu, dengan alasan) → Buat work order. Beberapa laporan sejenis dapat digabung ke satu WO.',
        ],
        'fields' => [
            'asset_id' => 'Aset yang rusak. Paling cepat dengan scan QR; atau ketik kode, nama, atau nomor kamar. Kosongkan bila asetnya tidak diketahui.',
            'location_id' => 'Lokasi kejadian. Wajib bila aset tidak dipilih; bila aset dipilih, lokasi aset dipakai.',
            'category' => 'Jenis masalah untuk mengarahkan ke teknisi yang tepat.',
            'symptom' => 'Uraikan gejala: apa yang terjadi, sejak kapan, seberapa parah. Contoh: "AC kamar 301 tidak dingin sejak pagi, tamu komplain".',
            'impact' => 'Dampak ke operasional; menentukan prioritas & target SLA. "Kamar tidak dapat dijual" dan "Acara aktif terganggu" menjadi P2.',
            'is_safety' => 'Centang bila ada risiko keselamatan. Prioritas langsung P1 dan pelapor sebaiknya juga menelepon Engineering.',
            'room_block_proposed' => 'Usulkan agar kamar tidak dijual sementara. Keputusan tetap di Front Office.',
            'reporter_contact' => 'Nomor yang bisa dihubungi teknisi; terisi dari profil pengguna.',
            'photos' => 'Foto kerusakan (maks beberapa berkas). Sangat membantu teknisi menyiapkan alat & suku cadang.',
            'client_event_uuid' => 'Kode teknis anti-duplikat saat jaringan putus-sambung. Tidak perlu diisi.',
        ],
        'shots' => [['service-requests.jpg', 'Daftar laporan kerusakan'], ['service-requests-create.jpg', 'Formulir laporan']],
    ],

    'work-orders' => [
        'title' => 'Work order',
        'konsep' => [
            'Work order (WO) adalah perintah kerja pemeliharaan: perbaikan, darurat, preventive (dari Rencana PM), inspeksi, atau predictive (dari pemantauan kondisi).',
            'Status berjalan terkontrol: Draft → (Menunggu persetujuan) → Disetujui → Ditugaskan → Dikerjakan → (Ditunda) → Selesai teknisi → Terverifikasi → Ditutup. Setiap perubahan tercatat beserta pelaku, waktu dan alasan.',
            'SLA dihitung dari waktu laporan diterima; jam kerja teknisi, biaya material/vendor/tenaga, foto bukti, dan status aset setelah pekerjaan tercatat di WO.',
        ],
        'langkah' => [
            'WO biasanya dibuat dari Laporan kerusakan (tombol Buat work order). WO langsung dapat dibuat lewat "Buat".',
            'Tanpa biaya: "Setujui langsung". Dengan estimasi biaya: "Ajukan persetujuan" → penyetuju sesuai Batas persetujuan.',
            'Tugaskan ke teknisi (PIC) dan tim bila perlu. Pekerjaan berisiko (hot work, ketinggian, LOTO) wajib lebih dari satu orang.',
            'Teknisi: Mulai kerja → (Tunda bila menunggu suku cadang, dengan alasan) → isi biaya → Selesai (diagnosis, tindakan, hasil uji fungsi).',
            'Supervisor: Terima hasil (verifikasi) dan konfirmasi status aset setelah pekerjaan → Tutup work order.',
        ],
        'fields' => [
            'title' => 'Ringkasan pekerjaan yang mudah dicari, mis. "AC kamar 301 tidak dingin".',
            'type' => 'Jenis pekerjaan. Korektif = perbaikan kerusakan; Darurat = P1/keselamatan; Preventive & Inspeksi biasanya dibuat otomatis dari Rencana PM; Predictive dari pembacaan kondisi.',
            'priority' => 'P1 darurat (respons tercepat) sampai P4 terencana. Menentukan target respons & pemulihan (lihat Target SLA).',
            'asset_id' => 'Aset yang dikerjakan. Riwayat pemeliharaan aset terbentuk dari isian ini; usahakan selalu diisi.',
            'location_id' => 'Lokasi pekerjaan bila aset tidak spesifik (mis. kebocoran plafon).',
            'description' => 'Uraian kebutuhan pekerjaan, lingkup, dan informasi akses.',
            'estimated_cost' => 'Perkiraan biaya. Bila diisi, WO harus disetujui oleh penyetuju yang batas nilainya mencukupi.',
            'needs_vendor' => 'Centang bila pekerjaan memerlukan pihak luar.',
            'is_safety' => 'Pekerjaan terkait keselamatan; diverifikasi lebih ketat.',
        ],
        'tips' => [
            'Tab "Melewati target" menampilkan WO yang melewati SLA.',
            'Filter "Tanggal masuk" dipakai dashboard pimpinan saat Anda mengklik grafik tren.',
            'WO yang ditutup tidak dapat diubah biayanya kecuali oleh Finance (baris koreksi terpisah).',
        ],
        'shots' => [['work-orders.jpg', 'Daftar work order'], ['work-orders-view.jpg', 'Detail work order dengan riwayat status']],
    ],

    'pm-plans' => [
        'title' => 'Rencana PM (preventive maintenance)',
        'konsep' => [
            'Rencana pemeliharaan berkala per aset atau per kategori aset, berbasis interval hari dan/atau meter (mis. jam operasi genset). Saat jatuh tempo, sistem membuat WO preventive otomatis lengkap dengan checklist.',
            'Jadwal berikutnya dihitung dari tanggal pekerjaan benar-benar selesai, sehingga keterlambatan tidak menumpuk.',
        ],
        'langkah' => [
            'Cara cepat: tombol "Buat dari interval kategori" membuat rencana untuk semua kategori yang punya interval PM bawaan.',
            'Atau klik "Buat": isi kode & nama pekerjaan, pilih cakupan (satu aset / semua aset dalam kategori).',
            'Isi interval hari (dan meter bila ada), jatuh tempo pertama, dan berapa hari sebelumnya WO dibuat.',
            'Susun checklist PM (butir yang harus dicentang teknisi), PIC, estimasi jam, vendor bila dikerjakan pihak luar.',
        ],
        'fields' => [
            'code' => 'Kode singkat unik, mis. PM-GEN-250.',
            'name' => 'Nama pekerjaan seperti tampil di WO, mis. "Servis genset bulanan".',
            'wo_type' => 'Preventive (servis/penggantian berkala) atau Inspeksi (pemeriksaan tanpa servis).',
            'scope' => '"Satu aset" untuk mesin tertentu; "Semua aset dalam kategori" membuat jadwal untuk setiap aset di kategori tersebut (mis. semua APAR).',
            'interval_days' => 'Jarak antar-PM dalam hari, mis. 30 (bulanan), 90 (3 bulanan), 180, 365.',
            'meter_parameter_id' => 'Opsional: parameter meter (mis. jam operasi). WO juga dibuat saat meter mencapai interval, mana yang lebih dulu.',
            'interval_meter' => 'Interval meter, mis. 250 jam operasi.',
            'starts_on' => 'Tanggal jatuh tempo pertama.',
            'lead_days' => 'WO dibuat H-sekian sebelum jatuh tempo agar sempat dijadwalkan (umumnya 7).',
            'priority' => 'Prioritas WO yang dibuat; PM biasanya P3/P4.',
            'pic_id' => 'Teknisi penanggung jawab default.',
            'checklist' => 'Butir pekerjaan yang wajib dicentang saat WO diselesaikan. Butir yang tidak dikerjakan harus diberi tindak lanjut.',
            'item' => 'Satu butir checklist, mis. "Cek level oli & coolant".',
            'needs_vendor' => 'Centang bila PM dikerjakan vendor (pilih vendornya).',
            'instructions' => 'Instruksi kerja / SOP singkat dan perhatian keselamatan.',
        ],
        'shots' => [['pm-plans.jpg', 'Daftar rencana PM'], ['pm-plans-create.jpg', 'Formulir rencana PM']],
    ],

    'condition-parameters' => [
        'title' => 'Parameter kondisi & meter',
        'konsep' => [
            'Mendefinisikan apa yang diukur dari aset (suhu chiller, tekanan boiler, jam operasi genset) beserta batas waspada dan kritis.',
            'Pembacaan yang melewati batas kritis otomatis membuat WO predictive; parameter meter kumulatif dipakai Rencana PM berbasis meter.',
        ],
        'fields' => [
            'code' => 'Kode singkat, mis. SUHU-CHILL.',
            'name' => 'Nama parameter yang tampil di formulir pembacaan.',
            'unit' => 'Satuan: °C, bar, jam, A, dsb.',
            'category_id' => 'Kategori aset yang memakai parameter ini; pembacaan hanya bisa untuk aset di kategori itu.',
            'is_meter' => 'Centang untuk nilai kumulatif yang tidak boleh turun (jam operasi, km).',
            'direction' => 'Bahaya bila naik (suhu chiller, tekanan) atau bila turun (tekanan air, level oli).',
            'warning_value' => 'Batas waspada: status kuning.',
            'critical_value' => 'Batas kritis: status merah dan WO predictive dibuat.',
            'critical_priority' => 'Prioritas WO yang dibuat saat kritis.',
            'wo_on_warning' => 'Buat WO sejak batas waspada (untuk aset sangat kritis).',
            'reading_interval_days' => 'Seberapa sering pembacaan rutin diharapkan.',
            'guidance' => 'Tindakan yang dianjurkan bila nilai di luar batas.',
        ],
        'shots' => [['condition-parameters.jpg', 'Parameter kondisi']],
    ],

    'condition-readings' => [
        'title' => 'Pemantauan kondisi',
        'konsep' => ['Log pembacaan kondisi/meter aset. Teknisi mencatat nilai (mis. suhu chiller tiap pagi); status normal/waspada/kritis dihitung otomatis dan pembacaan kritis membuat WO predictive.'],
        'langkah' => ['Klik "Catat pembacaan", pilih aset dan parameter, isi nilai, simpan.', 'Pembacaan kritis berikutnya ditautkan ke WO predictive yang sama sampai WO selesai.'],
        'shots' => [['condition-readings.jpg', 'Log pembacaan kondisi']],
    ],

    'room-blocks' => [
        'title' => 'Blokir kamar',
        'konsep' => ['Usulan Engineering agar kamar tidak dijual sementara karena pekerjaan perbaikan. Keputusan dan status di PMS tetap di Front Office; halaman ini mencatat usulan, persetujuan, dan pelepasan.'],
        'shots' => [['room-blocks.jpg', 'Daftar blokir kamar']],
    ],

    // ================================================================ ASET
    'assets' => [
        'title' => 'Register aset',
        'konsep' => [
            'Daftar induk aset individual: satu baris = satu unit fisik dengan kode dan label QR. Kode aset = LOKASI-JENIS-URUT (mis. MB-08-819-TV-01) dibuat server; bila aset dipindah atau ganti kategori, kode ikut berubah dan kode lama tetap bisa dicari (tertulis "dulu: …"). QR pada label tidak berubah.',
            'Siklus aset: Draft (data awal / baru) → Verifikasi fisik → Aktif → (Berhenti dipakai / Dihapus / Hilang). Aset draft tetap dapat dipakai untuk work order.',
            'Kartu aset mengumpulkan semuanya: foto, lokasi, kondisi, label, riwayat pemeliharaan, jadwal PM, kondisi & meter, dokumen legalitas, komponen, dan mutasi.',
        ],
        'langkah' => [
            'Data awal sudah dimuat dari inventaris departemen sebagai Draft. Saat identifikasi lapangan, scan label atau buka kartu aset.',
            'Klik "Ubah" untuk melengkapi merek, model, nomor seri, spesifikasi, foto. Catatan "Diasumsikan saat data awal" menunjukkan isian yang perlu dikoreksi.',
            'Klik "Verifikasi fisik" setelah aset dilihat langsung: konfirmasi lokasi & kondisi.',
            'Klik "Aktifkan" bila syarat lengkap (nama, kategori, lokasi, departemen, kepemilikan, kondisi, kritikalitas, penanggung jawab, verifikasi).',
            'Aset baru: tombol "Buat". Untuk banyak kamar sekaligus gunakan Generate aset.',
        ],
        'fields' => [
            'name' => 'Nama yang dikenali pengguna, mis. "AC split kamar 701" atau "Genset utama".',
            'category_id' => 'Kategori menentukan kelas penyusutan, interval PM bawaan, kritikalitas default, dan dokumen wajib.',
            'brand' => 'Merek pabrikan, mis. Daikin, Samsung. Kosongkan bila memang tidak bermerek.',
            'model' => 'Tipe/model dari nameplate, mis. FTKC25. Penting untuk pemesanan suku cadang.',
            'serial_number' => 'Nomor seri dari nameplate. Sistem memperingatkan bila sama dengan aset lain (kemungkinan duplikat).',
            'photo_path' => 'Foto utama aset yang tampil di kartu aset. Foto lain unggah di tab Foto & dokumen.',
            'location_id' => 'Lokasi saat ini (gedung › lantai › ruang). Perpindahan resmi dilakukan lewat Mutasi aset.',
            'department_id' => 'Departemen pemilik/pengguna aset.',
            'custodian_id' => 'Orang penanggung jawab aset (opsional).',
            'custodian_unit' => 'Unit penanggung jawab bila tidak ada orang tertentu, mis. "Housekeeping".',
            'parent_id' => 'Isi bila aset ini komponen dari aset lain (mis. kompresor dari chiller).',
            'condition_grade' => 'Kondisi fisik saat dinilai: A baik, B kerusakan ringan, C perlu perbaikan, D perlu kajian penggantian, E tidak layak.',
            'criticality' => 'Dampak bila aset gagal. Kritis: keselamatan atau operasi hotel berhenti (genset, APAR, lift).',
            'operational_status' => 'Apakah aset saat ini dapat dipakai. Berubah otomatis saat WO dimulai/diverifikasi.',
            'ownership' => 'Milik hotel, sewa, milik vendor, atau konsinyasi.',
            'purchase_date' => 'Tanggal pembelian (dari faktur).',
            'in_service_date' => 'Tanggal mulai dipakai; dasar umur aset.',
            'vendor_id' => 'Pemasok atau penyedia servis.',
            'invoice_ref' => 'Nomor faktur/PO pembelian.',
            'funding_source' => 'Sumber dana, mis. Capex 2026, renovasi.',
            'warranty_until' => 'Tanggal akhir garansi. WO pada aset bergaransi meminta keputusan klaim garansi.',
            'contract_ref' => 'Nomor kontrak servis bila ada.',
            'pm_interval_days' => 'Interval PM khusus aset ini; kosong = mengikuti kategori.',
            'specification' => 'Kapasitas/daya/ukuran, mis. "1 PK, 220V", "500 kVA".',
            'legacy_code' => 'Kode dari register lama / data awal. Dipakai agar impor ulang tidak menggandakan aset.',
            'migration_source' => 'Asal data (file › sheet › baris) untuk penelusuran.',
            'notes' => 'Catatan bebas, termasuk remark inventaris & asumsi data awal.',
        ],
        'tips' => [
            'Filter "Verifikasi = Belum terverifikasi" untuk daftar kerja tim identifikasi.',
            'Kode aset dan token QR tidak pernah dipakai ulang.',
        ],
        'shots' => [['assets.jpg', 'Register aset'], ['assets-view.jpg', 'Kartu aset dengan foto utama'], ['assets-create.jpg', 'Formulir aset']],
    ],

    'pages.peta-aset' => [
        'title' => 'Peta aset',
        'konsep' => ['Melihat aset berdasarkan pohon lokasi (gedung › lantai › ruang) atau berdasarkan jenis aset. Jumlah di setiap lokasi termasuk sub-lokasinya.'],
        'langkah' => ['Mode Lokasi: klik gedung/lantai/ruang untuk melihat aset di sana.', 'Mode Jenis aset: klik kelas atau kategori untuk melihat di lokasi mana saja aset itu berada.', 'Gunakan kotak cari untuk menemukan kode atau nama.'],
        'shots' => [['peta-aset.jpg', 'Peta aset per lokasi']],
    ],

    'asset-quantity-groups' => [
        'title' => 'Kelompok jumlah',
        'konsep' => ['Untuk barang sejenis yang tidak praktis diberi label satu per satu (linen, gelas, kursi banquet, kabel). Satu kelompok = satu QR per lokasi, dengan jumlah standar (par) dan jumlah aktual hasil hitung.'],
        'langkah' => ['Buat kelompok per lokasi & kategori.', 'Catat hitungan aktual secara berkala; selisih terhadap jumlah standar terlihat di daftar.'],
        'fields' => [
            'name' => 'Nama barang, mis. "Bath towel" atau "Kursi banquet".',
            'category_id' => 'Kategori berjenis kelompok kuantitas.',
            'location_id' => 'Lokasi penyimpanan/pemakaian.',
            'unit' => 'Satuan hitung: pcs, set, lusin.',
            'expected_qty' => 'Jumlah standar/par yang seharusnya ada.',
            'status' => 'Draft, Aktif, atau Berhenti.',
        ],
        'shots' => [['asset-quantity-groups.jpg', 'Kelompok jumlah']],
    ],

    'asset-transfers' => [
        'title' => 'Mutasi aset',
        'konsep' => ['Memindahkan aset antarlokasi/departemen secara tercatat: ajukan → setujui → kirim → terima (scan & cek kondisi) → selesai. Lokasi aset baru berubah saat diterima.'],
        'fields' => [
            'asset_ids' => 'Satu atau beberapa aset yang dipindah.',
            'to_location_id' => 'Lokasi tujuan.',
            'to_department_id' => 'Isi bila departemen pemilik ikut berubah.',
            'reason' => 'Alasan mutasi, mis. "renovasi kamar 7xx".',
            'submit_now' => 'Langsung kirim ke penyetuju; matikan untuk menyimpan sebagai draft.',
        ],
        'shots' => [['asset-transfers.jpg', 'Daftar mutasi aset']],
    ],

    // ================================================================ PENGENDALIAN ASET
    'pages.generate-assets' => [
        'title' => 'Generate aset untuk banyak kamar',
        'konsep' => ['Wizard membuat aset kamar secara massal dari Template aset kamar: pilih template → pilih kamar → lihat selisih → cocokkan dengan aset lama → buat aset draft → cetak label.'],
        'langkah' => ['Pastikan template tipe kamar sudah dipublikasikan.', 'Pilih template dan kamar, periksa pratinjau.', 'Cocokkan aset lama yang sudah ada agar tidak dobel, lalu jalankan.'],
        'shots' => [['generate-assets.jpg', 'Wizard generate aset']],
    ],

    'room-asset-templates' => [
        'title' => 'Template aset kamar',
        'konsep' => ['Standar isi aset per tipe kamar (mis. Deluxe: 1 AC, 1 TV, 1 minibar, 2 lampu tidur). Dipakai Generate aset. Template yang dipublikasikan terkunci; revisi menjadi versi baru.'],
        'fields' => [
            'code' => 'Kode template, mis. TPL-DLX.',
            'room_type_id' => 'Tipe kamar yang memakai template ini.',
            'item_key' => 'Kunci unik butir dalam template, mis. AC, TV, LAMP-BED.',
            'standard_name' => 'Nama aset yang akan dibuat, mis. "AC split".',
            'quantity' => 'Jumlah per kamar.',
            'mode' => 'Individual (1 QR per unit) atau Kelompok jumlah (1 QR untuk semua).',
            'positions' => 'Label posisi bila lebih dari satu, mis. "kiri, kanan".',
        ],
        'tips' => ['Template yang sudah terbit tidak bisa diedit; klik "Lihat isi" untuk melihat jenis aset tetap dan jumlahnya per tipe kamar.',
            'Kamar yang isinya berbeda dari standar dicatat sebagai pengecualian (jumlah berbeda, tidak tersedia, atau tambahan) sehingga Generate aset membuat jumlah yang benar.',
            'Template awal GPCH diimpor dari workbook data-awal-template-kamar.xlsx dengan perintah assetcare:import-room-templates.'],
        'shots' => [['room-asset-templates.jpg', 'Template aset kamar']],
    ],

    'pages.persetujuan-lapangan' => [
        'title' => 'Persetujuan hasil lapangan',
        'konsep' => ['Aset yang labelnya sudah ditempel & di-scan petugas di menu Lapangan, serta sudah dicek kondisinya, muncul di sini untuk disetujui. Persetujuan mengaktifkan aset. Penyetuju harus berbeda dari petugas yang memeriksa.'],
        'langkah' => ['Filter per gedung/lantai; tahap bawaan "Siap disetujui".', 'Periksa foto, kondisi, catatan; klik baris untuk membuka kartu aset.', 'Centang lalu "Setujui & aktifkan", atau "Kembalikan untuk dicek ulang" dengan alasan.', 'Pantau tahap "Aset tidak ditemukan" dan "Label rusak / hilang" setiap hari.'],
    ],
    'pages.backup-database' => [
        'title' => 'Backup database',
        'konsep' => ['Seluruh database disimpan sebagai satu berkas .sql bertanggal yang dikemas ZIP. Backup otomatis dibuat setiap hari pukul 01:30 dan disimpan 30 hari.'],
        'langkah' => ['Klik "Buat backup sekarang" sebelum pembaruan aplikasi atau perubahan besar.', 'Klik "Unduh" lalu simpan ZIP di NAS / drive IT, di luar server.', 'Memulihkan: ekstrak ZIP lalu jalankan mysql -u USER -p NAMA_DATABASE < berkas.sql (oleh IT).'],
        'tips' => ['Berkas memuat data pengguna: simpan di tempat terbatas dan jangan dibagikan.', 'Foto & dokumen lampiran tidak termasuk; dicadangkan terpisah oleh skrip server (deploy/backup-db.sh).'],
    ],
    'pages.panduan-peran' => [
        'title' => 'Panduan per peran',
        'konsep' => ['Panduan langkah demi langkah untuk setiap peran, dengan tangkapan layar web atau ponsel sesuai cara kerja peran itu. Peran Anda tampil paling atas.'],
    ],
    'pages.cetak-label' => [
        'title' => 'Cetak label QR',
        'konsep' => ['Mencetak label QR massal: pilih filter (lokasi, kategori, status), lihat pratinjau urutan & jumlah lembar, lalu unduh PDF ukuran aktual untuk kertas label.'],
        'langkah' => ['Pilih aset/kelompok dengan filter (mis. lantai 7, belum dicetak).', 'Pilih ukuran label dan sel awal (untuk lembar yang sudah terpakai sebagian).', 'Unduh PDF, cetak dengan skala 100% (tanpa "fit to page"), lalu konfirmasi tercetak.'],
        'tips' => ['Cetak satu lembar uji dan scan dengan ponsel sebelum cetak massal.'],
        'shots' => [['cetak-label.jpg', 'Cetak label QR']],
    ],

    'pages.konfirmasi-pemasangan' => [
        'title' => 'Konfirmasi pemasangan label',
        'konsep' => ['Mengonfirmasi bahwa label sudah ditempel pada aset yang benar, per kamar/lokasi. Scan biasa tidak dianggap pemasangan.'],
        'langkah' => ['Pilih lokasi/kamar.', 'Scan setiap label yang ditempel; sistem mencocokkan dengan aset di lokasi itu.', 'Tandai yang tidak ditemukan atau salah lokasi.'],
        'shots' => [['konfirmasi-pemasangan.jpg', 'Konfirmasi pemasangan label']],
    ],

    'generation-batches' => ['title' => 'Riwayat generate', 'konsep' => ['Catatan setiap proses Generate aset: template & versi, kamar, hasil per kamar. Untuk penelusuran dan audit.']],
    'label-print-batches' => ['title' => 'Riwayat cetak label', 'konsep' => ['Catatan setiap batch cetak label: operator, ukuran media, jumlah, alasan cetak ulang. Dapat mengunduh ulang PDF batch.']],

    // ================================================================ ASET TETAP & DOKUMEN
    'asset-documents' => [
        'title' => 'Register dokumen',
        'konsep' => [
            'Tempat menyimpan dokumen legalitas & pendukung aset secara digital: sertifikat tanah/bangunan, izin, riksa uji K3, BPKB/STNK, kontrak servis, polis asuransi, faktur & BAST.',
            'Satu dokumen dapat menaungi banyak aset (mis. satu polis untuk semua genset) dan satu aset dapat punya banyak dokumen. Setiap berkas disimpan privat, berversi, dengan hash SHA-256.',
            'Masa berlaku dipantau otomatis: pengingat dikirim ke PIC sebelum berakhir dan dieskalasi ke GM bila terlambat.',
        ],
        'langkah' => [
            'Cara cepat: "Unggah cepat (banyak berkas)" → pilih jenis dokumen, pilih aset, seret beberapa berkas sekaligus. Pilih "Satu dokumen per berkas" bila tiap berkas dokumen berbeda.',
            'Cara lengkap: "Daftarkan dokumen" → isi identitas, masa berlaku, PIC, lokasi dokumen asli, aset yang ditaungi, dan berkas.',
            'Dari kartu aset: tab Dokumen → "Unggah dokumen legalitas".',
            'Versi baru (mis. pindaian ulang, adendum): buka dokumen → "Unggah berkas / versi baru" (boleh banyak berkas).',
            'Saat diperpanjang: "Catat pengajuan perpanjangan", lalu "Dokumen baru terbit" setelah terbit.',
        ],
        'fields' => [
            'document_type_id' => 'Jenis dokumen menentukan klasifikasi default, pola masa berlaku, dan pengingat.',
            'register_no' => 'Nomor register dibuat otomatis.',
            'title' => 'Judul/uraian, mis. "SLO genset 500 kVA".',
            'classification' => 'Siapa boleh melihat. Terbatas/Rahasia hanya untuk pengguna berizin khusus.',
            'document_number' => 'Nomor resmi yang tertera di dokumen.',
            'issuer' => 'Instansi/pihak penerbit, mis. Disnaker Kota Bandung.',
            'issued_at' => 'Tanggal terbit.',
            'no_expiry' => 'Centang bila dokumen berlaku tanpa batas (mis. akta, faktur).',
            'valid_until' => 'Tanggal berakhir sesuai dokumen; pengingat dihitung dari tanggal ini.',
            'notice_days' => 'Untuk kontrak: batas pemberitahuan sebelum berakhir.',
            'pic_id' => 'Penanggung jawab perpanjangan; menerima pengingat.',
            'backup_pic_id' => 'Atasan/cadangan yang ikut menerima eskalasi.',
            'original_location' => 'Di mana dokumen asli disimpan.',
            'original_location_note' => 'Detail lokasi asli: nama bank & nomor perjanjian, notaris, nomor brankas.',
            'vendor_id' => 'Pihak kontrak/penerbit polis.',
            'location_id' => 'Lokasi terkait (mis. gedung untuk SLF).',
            'contract_value' => 'Nilai kontrak.',
            'premium' => 'Premi polis.',
            'sum_insured' => 'Nilai pertanggungan polis.',
            'files' => 'Berkas PDF/JPG/PNG, boleh beberapa sekaligus, maks 20 MB per berkas.',
            'per_file' => 'Aktif: setiap berkas menjadi dokumen sendiri (judul dari nama berkas). Nonaktif: semua berkas masuk satu dokumen.',
            'asset_ids' => 'Aset yang dinaungi dokumen. Boleh kosong untuk dokumen tingkat perusahaan.',
            'notes' => 'Catatan tambahan.',
        ],
        'tips' => ['Beri nama berkas yang jelas sebelum unggah (mis. "SLO-genset-2026.pdf") agar judul otomatis rapi.'],
        'shots' => [['asset-documents.jpg', 'Register dokumen']],
    ],

    'document-types' => [
        'title' => 'Jenis dokumen',
        'konsep' => ['Daftar jenis dokumen dengan klasifikasi default, pola masa berlaku, retensi, dan jadwal pengingat. Sudah terisi 64 jenis sesuai register dokumen; ubah bila kebijakan berubah.'],
        'fields' => [
            'has_expiry' => 'Dokumen punya tanggal berakhir (izin, polis, kontrak).',
            'first_reminder_days' => 'Pengingat pertama dikirim sekian hari sebelum berakhir.',
            'is_k3' => 'Izin/riksa uji K3: aset terkait ditandai "Tidak boleh dioperasikan" bila kedaluwarsa.',
            'is_insurance' => 'Polis asuransi: menampilkan isian premi & nilai pertanggungan.',
            'is_contract' => 'Kontrak: menampilkan nilai kontrak & batas pemberitahuan.',
        ],
    ],

    'depreciation-classes' => [
        'title' => 'Kelas aset & penyusutan',
        'konsep' => ['Kelas aset sesuai Kebijakan Aset Tetap: umur manfaat komersial, nilai residu, kelompok fiskal, dan dokumen wajib per kelas. Kategori aset dipetakan ke kelas ini.'],
        'fields' => [
            'commercial_life_months' => 'Umur manfaat komersial (bulan) untuk penyusutan buku.',
            'residual_pct' => 'Nilai sisa di akhir umur sebagai persen biaya perolehan.',
            'fiscal_group_code' => 'Kelompok harta fiskal (PMK 72/2023) untuk penyusutan pajak.',
            'requirements' => 'Daftar dokumen yang wajib/disarankan untuk aset di kelas ini.',
        ],
    ],

    'fiscal-groups' => ['title' => 'Kelompok fiskal', 'konsep' => ['Kelompok harta fiskal (UU PPh pasal 11, PMK 72/2023) dengan masa manfaat pajak. Umur bangunan permanen diatur di kebijakan aset tetap.']],

    // ================================================================ LAPORAN
    'pages.laporan' => [
        'title' => 'Laporan & KPI',
        'konsep' => ['Ringkasan KPI periode dan laporan tabel yang dapat diunduh CSV: SLA, MTTR, kepatuhan PM, kondisi di luar batas, riwayat aset, penyusutan, dokumen, dan lainnya.'],
        'langkah' => ['Pilih laporan dan periode.', 'Klik Unduh untuk CSV (dapat dibuka di Excel).'],
        'shots' => [['laporan.jpg', 'Laporan & KPI']],
    ],

    'audit-logs' => ['title' => 'Log audit', 'konsep' => ['Catatan hanya-baca: siapa mengubah apa, kapan, nilai lama/baru, dan alasannya. Dipakai auditor dan untuk menelusuri perubahan data.']],

    // ================================================================ PENGATURAN
    'pages.identitas-perusahaan' => [
        'title' => 'Identitas perusahaan',
        'konsep' => ['Nama, badan hukum, alamat, kontak, logo, favicon, dan warna utama yang tampil di aplikasi, laporan, dan label QR.'],
        'fields' => [
            'company_name' => 'Nama properti lengkap, tampil di judul laporan.',
            'company_short' => 'Nama singkat untuk tempat sempit (label QR, badge).',
            'legal_name' => 'Nama badan hukum (PT ...).',
            'primary_color' => 'Warna utama tema aplikasi.',
            'label_heading' => 'Judul kecil di label QR; kosong = nama singkat.',
            'show_ornament' => 'Ornamen khas tema Pasundan di sidebar dan halaman login.',
        ],
        'shots' => [['identitas-perusahaan.jpg', 'Identitas perusahaan']],
    ],

    'locations' => [
        'title' => 'Lokasi & kamar',
        'konsep' => [
            'Pohon lokasi properti: Gedung/Zona → Lantai → Kamar/Ruang/Venue → Titik pemasangan. Semua aset, laporan, dan WO menunjuk ke lokasi ini.',
            'Data awal membuat dua gedung: Main Building (Extention) dan West Wing (Existing), lengkap dengan lantai, kamar, dan area BOH.',
        ],
        'langkah' => ['Tambah lokasi dengan "Buat": isi kode unik, nama, jenis, dan induk.', 'Untuk kamar, isi nomor kamar (sama dengan PMS) dan tipe kamar.', 'Nonaktifkan lokasi yang tidak dipakai, jangan dihapus.'],
        'fields' => [
            'code' => 'Kode unik, mis. MB-07-701 (gedung-lantai-kamar). Tercetak di laporan; buat konsisten.',
            'name' => 'Nama yang mudah dikenali, mis. "Kamar 701", "Main kitchen".',
            'location_type' => 'Gedung, Zona, Lantai, Kamar, Venue/ruang rapat, Area/ruang, atau Titik pemasangan.',
            'parent_id' => 'Lokasi induk; menentukan jalur lengkap (gedung › lantai › ruang).',
            'room_type_id' => 'Tipe kamar (untuk Template aset kamar & Generate aset).',
            'room_ref' => 'Nomor kamar persis seperti di PMS.',
            'floor_label' => 'Label lantai seperti di lift/denah, mis. 7, B, UM.',
            'pms_room_status' => 'Informasi status kamar dari PMS (bila diintegrasikan).',
            'is_active' => 'Nonaktif = tidak muncul di pilihan baru, data lama tetap.',
        ],
        'shots' => [['locations.jpg', 'Daftar lokasi']],
    ],

    'asset-categories' => [
        'title' => 'Kategori aset',
        'konsep' => ['Pengelompokan aset (AC split, TV, genset, furnitur kantor, dll.) yang menentukan cara pelacakan, kritikalitas default, interval PM usulan, dan kelas penyusutan.'],
        'fields' => [
            'code' => 'Kode singkat unik, mis. AC-SPLIT.',
            'group' => 'Kelompok tampilan (kamar, MICE, kitchen, MEP, publik, IT, dll.).',
            'default_criticality' => 'Kritikalitas yang diusulkan untuk aset baru di kategori ini.',
            'tracking_mode' => 'Per unit (setiap unit punya kode & QR) atau Kelompok kuantitas per lokasi (linen, gelas).',
            'default_pm_interval_days' => 'Interval PM usulan; dipakai "Buat dari interval kategori" di Rencana PM.',
            'depreciation_class_id' => 'Kelas penyusutan sesuai kebijakan aset tetap.',
            'fiscal_group_override' => 'Kelompok fiskal khusus bila berbeda dari kelas.',
        ],
        'shots' => [['asset-categories.jpg', 'Kategori aset']],
    ],

    'departments' => [
        'title' => 'Departemen',
        'konsep' => ['Departemen pemilik/pengguna aset dan asal pengguna. Dipakai untuk filter, laporan, dan dashboard.'],
        'fields' => ['cost_center' => 'Kode cost center akuntansi untuk alokasi biaya.'],
    ],

    'users' => [
        'title' => 'Pengguna',
        'konsep' => ['Akun pengguna dan perannya. Akses ditolak secara default; pengguna hanya melihat menu sesuai peran. Pengguna yang keluar dinonaktifkan, tidak dihapus, agar riwayat tetap utuh.'],
        'langkah' => ['Klik "Buat", isi nama, email, jabatan, departemen, kata sandi awal.', 'Pilih peran (bisa lebih dari satu).', 'Biarkan "Wajib ganti kata sandi" aktif; pengguna akan mengganti sandi dan memasang 2FA saat login pertama.', 'Untuk petugas yang mendata aset lewat ponsel, beri HANYA peran "Petugas Identifikasi Aset". Mereka login di alamat /lapangan dan hanya melihat menu Lapangan (Beranda, Lokasi, Scan, Cari, Akun), tidak melihat panel admin.'],
        'fields' => [
            'email' => 'Dipakai untuk login dan notifikasi.',
            'phone' => 'Nomor telepon/WhatsApp untuk dihubungi teknisi.',
            'labor_rate' => 'Tarif tenaga per jam untuk alokasi biaya tenaga internal di WO.',
            'must_change_password' => 'Pengguna wajib mengganti sandi saat login berikutnya.',
            'password' => 'Sandi awal minimal 10 karakter; sampaikan secara pribadi.',
            'roles' => 'Peran menentukan menu dan tindakan yang diizinkan.',
        ],
        'shots' => [['users.jpg', 'Daftar pengguna']],
    ],

    'roles' => [
        'title' => 'Peran & izin',
        'konsep' => ['Peran (Chief Engineering, Teknisi, Asset Controller, GM, Finance, Auditor, dll.) berisi kumpulan izin. Sudah tersedia 15 peran standar; ubah hanya bila struktur organisasi berbeda.'],
        'fields' => ['slug' => 'Kode teknis peran; jangan diubah untuk peran bawaan.', 'permissions' => 'Centang izin yang diberikan.'],
    ],

    'vendors' => [
        'title' => 'Vendor',
        'konsep' => ['Daftar vendor servis & pemasok. Dipakai di aset, WO (pekerjaan vendor), rencana PM, dan dokumen kontrak.'],
        'fields' => ['specialization' => 'Bidang, mis. AC & HVAC, lift, genset.', 'service_hours' => 'Jam layanan/darurat.', 'sla_note' => 'Target respons sesuai kontrak.'],
    ],

    'sla-policies' => [
        'title' => 'Target SLA',
        'konsep' => ['Target waktu respons dan pemulihan per prioritas P1–P4. Dipakai untuk menghitung tenggat laporan & WO, peringatan 80%, dan eskalasi ke GM.'],
        'fields' => ['response_minutes' => 'Batas waktu respons pertama (menit).', 'recovery_minutes' => 'Batas waktu sampai fungsi pulih (menit).', 'calendar' => '24 jam atau hanya jam kerja.'],
    ],

    'approval-limits' => [
        'title' => 'Batas persetujuan',
        'konsep' => ['Siapa boleh menyetujui WO berbiaya sampai nilai berapa, dengan masa berlaku dan pengganti saat cuti. Nominal contoh wajib disahkan manajemen.'],
        'fields' => ['max_amount' => 'Nilai maksimal yang boleh disetujui.', 'delegate_user_id' => 'Pengganti sementara selama masa berlaku.', 'note' => 'Dasar penetapan, mis. SK GM no. ...'],
    ],

    'room-types' => ['title' => 'Tipe kamar', 'konsep' => ['Tipe kamar (Deluxe, Suite, dll.) untuk template aset kamar dan pengelompokan kamar.']],
];
