<?php

/*
 * Panduan penggunaan per peran. Gambar ada di public/help/peran/<nama>.jpg (w- = layar web, m- = layar ponsel).
 * 'perangkat': web = komputer/laptop di /admin, ponsel = HP, keduanya = bisa dua-duanya.
 * 'sama' = memakai isi panduan peran lain.
 */
return [
    'field-officer' => [
        'perangkat' => 'ponsel', 'alamat' => '/lapangan',
        'ringkas' => 'Menempel label QR, memeriksa kondisi, dan memotret aset di setiap kamar/ruang. Hanya memakai menu Lapangan di ponsel; tidak melihat panel admin.',
        'tugas' => [
            ['Masuk & lihat progres', ['Buka alamat /lapangan di Chrome ponsel, masuk dengan email & sandi dari admin.', 'Beranda menampilkan progres identifikasi dan pekerjaan Anda hari ini.', 'Tip: menu titik tiga Chrome › "Tambahkan ke layar utama" agar seperti aplikasi.'], 'm-lap-home', 'ponsel'],
            ['Buka kamar yang dikerjakan', ['Menu bawah Lokasi › pilih gedung › lantai › kamar.', 'Terlihat daftar aset di kamar itu dan status Sudah/Belum.', 'Tekan "Pasang label QR" untuk mulai menempel label.'], 'm-lap-kamar', 'ponsel'],
            ['Tempel label lalu scan', ['Ambil label dari amplop kamar. Tempel pada aset yang namanya sama dengan tulisan di label, di permukaan bersih & kering (lihat SOP: posisi per jenis aset).', 'Arahkan kamera ke label. Label tercatat "Terpasang".', 'Label milik kamar lain ditolak: jangan ditempel, kembalikan ke amplop yang benar.', 'Pakai handheld scanner? Nyalakan sakelar "Pakai handheld scanner" di atas layar; kamera mati dan hasil scan handheld masuk otomatis.', 'Label rusak/hilang: buka "Label bermasalah?" pada aset itu lalu Catat.'], 'm-lap-pasang', 'ponsel'],
            ['Periksa kondisi & foto', ['Setelah scan, form aset terbuka otomatis.', 'Pilih Ada / Tidak ditemukan, kondisi A–E, status operasional, isi merek/serial bila terlihat.', 'Ambil foto, tulis catatan bila perlu, lalu Simpan. Aplikasi kembali ke pemindai untuk label berikutnya.'], 'm-lap-aset', 'ponsel'],
            ['Scan cepat & aset belum terdaftar', ['Tombol Scan di tengah menu bawah membuka data aset dari label mana pun (tidak mencatat pemasangan).', 'Ada aset di kamar yang tidak ada di daftar? Tekan "+ Ada aset yang belum terdaftar", isi nama, kategori, kondisi, foto.'], 'm-lap-temuan', 'ponsel'],
        ],
    ],
    'label-printer' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Mencetak label QR per lantai/kamar di ruang IT dan menyerahkannya ke petugas lapangan dalam amplop per kamar.',
        'tugas' => [
            ['Siapkan label Tom & Jerry No.103 (sekali)', ['Cetak label QR › Kalibrasi posisi label: isi jarak kiri/atas ke label pertama dan jarak antar-label (ukur dengan penggaris), Simpan.', '"Cetak lembar uji" di kertas HVS skala 100%, tumpuk dengan lembar label, terawang ke lampu. Ulangi sampai setiap kotak tepat di tengah label.', 'Tempel lembar label di kotak jingga pada kertas A4 (selotip di tepi atas). Satu lembar = 12 label (3 × 4).'], 'w-cetak', 'web'],
            ['Cetak label per area', ['Menu Pengendalian Aset › Cetak label QR. Media: Tom & Jerry No.103.', 'Pilih lantai atau kamar sesuai permintaan Asset Controller. Status cetak: "Belum pernah dicetak".', 'Biarkan "Sertakan aset yang belum dicek di lapangan" tercentang.', 'Pilih media label, cek jumlah lembar, lalu "Buat PDF label". Cetak dengan skala 100%; uji satu lembar dulu.'], 'w-cetak', 'web'],
            ['Konfirmasi cetak & cetak ulang', ['Menu Riwayat cetak label › buka batch › "Konfirmasi sudah dicetak" setelah semua lembar tercetak.', 'Label rusak/hilang dari lapangan: Cetak label QR › status cetak "Belum terpasang" untuk mencetak ulang.', 'Potong label dan masukkan ke amplop per kamar.'], 'w-riwayat-cetak', 'web'],
        ],
    ],
    'asset-controller' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Pemilik register aset: menentukan area kerja, menyetujui hasil lapangan, mengelola template kamar dan data aset.',
        'tugas' => [
            ['Setujui hasil lapangan', ['Menu Pengendalian Aset › Persetujuan hasil lapangan (angka di menu = jumlah yang menunggu).', 'Filter per gedung/lantai. Periksa foto, kondisi, catatan petugas.', 'Centang aset yang benar › "Setujui & aktifkan". Yang meragukan › "Kembalikan untuk dicek ulang" dengan alasan.', 'Anda tidak bisa menyetujui aset yang Anda periksa sendiri.', 'Tahap lain: "Aset tidak ditemukan" dan "Label rusak / hilang" untuk ditindaklanjuti.'], 'w-persetujuan', 'web'],
            ['Kelola register aset', ['Menu Aset › Register aset. Cari dengan kode baru (mis. MB-08-819-TV-01) atau kode lama.', 'Buka kartu aset untuk melengkapi departemen, penanggung jawab, spesifikasi, dokumen.', 'Aksi massal: verifikasi fisik, aktifkan, cetak label.'], 'w-assets', 'web'],
            ['Template aset kamar', ['Menu Template aset kamar: daftar aset tetap standar per tipe kamar.', 'Klik template terbit untuk "Lihat isi". Perubahan lewat "Buat versi baru", lalu diterbitkan oleh pemeriksa lain.', 'Generate aset kamar untuk membuat aset dari template.'], 'w-template', 'web'],
            ['Peta aset', ['Menu Peta aset: pohon lokasi, klik gedung/lantai/kamar untuk melihat isinya, atau klik jenis aset untuk melihat sebarannya.'], 'w-peta', 'web'],
        ],
    ],
    'chief-engineering' => [
        'perangkat' => 'keduanya', 'alamat' => '/admin',
        'ringkas' => 'Menerima laporan kerusakan, menentukan prioritas, menugaskan teknisi, memverifikasi pekerjaan, dan mengatur pemeliharaan terencana.',
        'tugas' => [
            ['Triase laporan kerusakan', ['Menu Pemeliharaan › Laporan kerusakan.', 'Buka laporan baru › "Akui laporan" › "Triase" (prioritas P1–P4) › "Buat work order".'], 'w-sr-triage', 'web'],
            ['Tugaskan & verifikasi Work Order', ['Menu Work order. Tab Terbuka / Menunggu verifikasi / Melewati target.', '"Tugaskan" PIC teknisi dan jadwal. Setelah teknisi menekan Selesai, periksa hasil › "Terima hasil (verifikasi)" › "Tutup work order".', 'Biaya di atas batas otomatis meminta persetujuan GM/Finance.'], 'w-wo-list', 'web'],
            ['Rencana PM & pemantauan kondisi', ['Rencana PM: interval, checklist, PIC. WO preventive dibuat otomatis sesuai jadwal.', 'Pemantauan kondisi: catat suhu chiller/freezer, tekanan boiler, jam genset.'], 'w-pm', 'web'],
        ],
    ],
    'technician' => [
        'perangkat' => 'ponsel', 'alamat' => '/admin',
        'ringkas' => 'Mengerjakan Work Order yang ditugaskan, mencatat diagnosis, waktu, material, dan foto bukti. Paling praktis lewat ponsel.',
        'tugas' => [
            ['Lihat tugas saya', ['Buka /admin di ponsel, masuk. Menu bawah "Tugas Saya".', 'Tab Tugas saya berisi WO yang ditugaskan kepada Anda, urut prioritas.'], 'm-tek-wo', 'ponsel'],
            ['Kerjakan Work Order', ['Buka WO › "Mulai kerja (check-in)" saat tiba di lokasi.', 'Catat diagnosis, tindakan, material, dan foto sebelum/sesudah.', 'Tekan "Selesai". Chief Engineering lalu menekan "Terima hasil (verifikasi)" atau mengembalikan ke Anda.'], 'm-tek-wo-view', 'ponsel'],
            ['Scan QR aset', ['Menu bawah Scan › Mulai kamera › arahkan ke label. Kartu aset terbuka: riwayat perbaikan, komponen, WO terbuka.'], 'm-tek-scan', 'ponsel'],
        ],
    ],
    'reporter' => [
        'perangkat' => 'ponsel', 'alamat' => '/admin',
        'ringkas' => 'Melaporkan kerusakan aset lewat ponsel, cukup scan QR pada aset.',
        'tugas' => [
            ['Scan QR lalu lapor', ['Scan label QR di aset dengan kamera ponsel (atau menu Scan). Kartu aset terbuka.', 'Tekan "Lapor kerusakan".'], 'm-qr-publik', 'ponsel'],
            ['Isi laporan', ['Aset dan lokasi terisi otomatis bila lewat QR. Pilih kategori, tulis gejala, dampak, dan foto.', 'Kirim. Status laporan bisa dipantau di menu Laporan kerusakan.'], 'm-lapor', 'ponsel'],
        ],
    ],
    'front-office' => [
        'perangkat' => 'keduanya', 'alamat' => '/admin',
        'ringkas' => 'Melaporkan kerusakan dari tamu dan mengonfirmasi blokir kamar (out of order) dari Engineering.',
        'tugas' => [
            ['Blokir kamar', ['Menu Blokir kamar: daftar kamar yang diusulkan diblokir karena perbaikan.', '"Konfirmasi blokir" di PMS/FO, lalu "Lepas blokir" setelah WO selesai.'], 'm-fo-blok', 'ponsel'],
            ['Lapor kerusakan', ['Sama seperti Pelapor: scan QR › Lapor kerusakan, atau menu Laporan kerusakan › Buat.'], 'm-lapor', 'ponsel'],
        ],
    ],
    'hod' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Kepala departemen: melapor kerusakan, memantau pekerjaan departemen, mengusulkan dan menyetujui mutasi aset.',
        'tugas' => [
            ['Beranda', ['Beranda menampilkan laporan dan pekerjaan departemen Anda. Tombol Scan QR dan Lapor kerusakan di atas.'], 'w-hod-beranda', 'web'],
            ['Mutasi aset', ['Menu Mutasi aset › Buat: pilih aset, lokasi/departemen tujuan, alasan.', 'Mutasi disetujui dan diterima sesuai alur. Kode aset ikut berubah mengikuti lokasi baru.'], 'w-mutasi', 'web'],
        ],
    ],
    'gm' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Memantau kinerja aset & pemeliharaan, menyetujui Work Order berbiaya besar, mutasi, dan penerbitan template.',
        'tugas' => [
            ['Dashboard pimpinan', ['Menu Dashboard pimpinan: ringkasan aset, backlog WO, SLA, biaya, dan risiko dokumen.'], 'w-dashboard', 'web'],
            ['Setujui Work Order', ['Menu Work order › tab Menunggu persetujuan › buka WO › periksa estimasi biaya › Setujui.'], 'w-wo-approve', 'web'],
            ['Laporan & KPI', ['Menu Laporan & KPI: pilih periode dan jenis laporan, Unduh CSV atau Cetak.'], 'w-laporan', 'web'],
        ],
    ],
    'owner' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Melihat kondisi aset, pemeliharaan, dan laporan keuangan aset (hanya baca).',
        'tugas' => [
            ['Beranda & dashboard', ['Beranda dan Dashboard pimpinan menampilkan ringkasan aset dan pemeliharaan.'], 'w-owner-beranda', 'web'],
            ['Laporan', ['Menu Laporan & KPI untuk laporan aset, biaya pemeliharaan, penyusutan.'], 'w-laporan', 'web'],
        ],
    ],
    'owner-rep' => ['sama' => 'owner'],
    'finance' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Mengelola nilai aset, kebijakan penyusutan, dokumen legal, dan finalisasi biaya Work Order.',
        'tugas' => [
            ['Kebijakan & kelas penyusutan', ['Menu Kebijakan penyusutan & Kelompok fiskal: umur manfaat komersial & fiskal. Biaya WO difinalkan lewat "Finalkan biaya (Finance)".'], 'w-penyusutan', 'web'],
            ['Register dokumen', ['Menu Register dokumen: dokumen legal & perolehan aset, masa berlaku, pengingat.'], 'w-dokumen', 'web'],
            ['Laporan keuangan aset', ['Menu Laporan & KPI › penyusutan komersial/fiskal, rekonsiliasi, biaya pemeliharaan.'], 'w-laporan', 'web'],
        ],
    ],
    'purchasing' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Mengelola data vendor dan melihat Work Order yang membutuhkan pembelian.',
        'tugas' => [
            ['Vendor', ['Menu Vendor: tambah dan perbarui data vendor/kontraktor.'], 'w-vendor', 'web'],
        ],
    ],
    'storekeeper' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Melihat Work Order untuk menyiapkan material dan suku cadang; bisa melapor kerusakan.',
        'tugas' => [
            ['Work Order', ['Menu Work order › tab Terbuka atau Semua untuk melihat kebutuhan material.'], 'w-gudang-wo', 'web'],
        ],
    ],
    'auditor' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Memeriksa register aset, laporan, dan jejak audit (hanya baca).',
        'tugas' => [
            ['Laporan', ['Menu Laporan & KPI: unduh laporan sesuai periode audit.'], 'w-auditor-laporan', 'web'],
            ['Log audit', ['Menu Log audit: siapa mengubah apa dan kapan.'], 'w-audit', 'web'],
        ],
    ],
    'it-admin' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Membuat akun pengguna, mengatur peran & hak akses, data referensi, dan identitas perusahaan.',
        'tugas' => [
            ['Pengguna', ['Menu Pengguna › Buat: nama, email, sandi awal, peran.', 'Petugas lapangan: beri HANYA peran "Petugas Identifikasi Aset" (mereka memakai /lapangan).', 'Staf ruang IT: peran "Staf Cetak Label (IT)".'], 'w-users', 'web'],
            ['Peran & hak akses', ['Menu Peran & izin: lihat dan sesuaikan izin tiap peran.'], 'w-roles', 'web'],
        ],
    ],
    'super-admin' => [
        'perangkat' => 'web', 'alamat' => '/admin',
        'ringkas' => 'Akses penuh: semua menu, pengaturan, pengguna. Gunakan panduan peran lain untuk tugas harian.',
        'tugas' => [
            ['Pengguna & peran', ['Menu Pengguna: buat akun dan beri peran sesuai tugas.'], 'w-users', 'web'],
            ['Identitas perusahaan', ['Menu Identitas perusahaan: nama, logo, warna; tampil di laporan dan label.'], 'w-identitas', 'web'],
        ],
    ],
];
