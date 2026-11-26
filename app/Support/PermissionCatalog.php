<?php

namespace App\Support;

/**
 * Katalog izin & peran Asset Care (spesifikasi bagian 3). Deny by default:
 * setiap layar & aksi memeriksa izin; harga/nilai buku hanya untuk asset.view_financial.
 */
class PermissionCatalog
{
    public const PERMISSIONS = [
        // Umum
        'dashboard.view' => ['Beranda manajerial (KPI, risiko, biaya)', 'Umum'],
        'report.view' => ['Melihat laporan', 'Umum'],
        'report.export' => ['Ekspor laporan (CSV)', 'Umum'],
        // Aset
        'asset.view' => ['Melihat register & kartu aset', 'Aset'],
        'asset.edit' => ['Membuat & mengubah data aset', 'Aset'],
        'asset.verify' => ['Verifikasi fisik aset', 'Aset'],
        'asset.activate' => ['Mengaktifkan / menghentikan aset (siklus hidup)', 'Aset'],
        'asset.view_financial' => ['Melihat harga & nilai aset', 'Aset'],
        'asset.edit_financial' => ['Mengubah data finansial aset', 'Aset'],
        'asset.delete' => ['Menghapus entri aset dobel (digabung ke aset yang benar)', 'Aset'],
        'template.manage' => ['Menyusun template aset tipe kamar', 'Aset'],
        'template.publish' => ['Memeriksa & menerbitkan template', 'Aset'],
        'generation.run' => ['Pembuatan aset massal untuk kamar', 'Aset'],
        'label.print' => ['Membuat batch & cetak label QR', 'Aset'],
        'label.rotate' => ['Rotasi token label (nonaktifkan label lama)', 'Aset'],
        'label.install' => ['Konfirmasi pemasangan label', 'Aset'],
        'field.identify' => ['Identifikasi aset di lapangan lewat ponsel (menu Lapangan)', 'Aset'],
        'transfer.request' => ['Mengajukan mutasi aset', 'Aset'],
        'transfer.approve' => ['Menyetujui mutasi aset', 'Aset'],
        'transfer.receive' => ['Mengirim / menerima mutasi aset', 'Aset'],
        // Aset tetap & dokumen
        'policy.manage' => ['Kebijakan aset tetap: kelas penyusutan, kelompok fiskal, batas kapitalisasi', 'Aset tetap & dokumen'],
        'document.view' => ['Melihat register dokumen aset (Umum & Internal)', 'Aset tetap & dokumen'],
        'document.view_restricted' => ['Melihat dokumen Terbatas & Rahasia', 'Aset tetap & dokumen'],
        'document.manage' => ['Mendaftarkan dokumen, mengunggah berkas, memperpanjang', 'Aset tetap & dokumen'],
        'document.escalation' => ['Menerima eskalasi dokumen H-30 & kedaluwarsa', 'Aset tetap & dokumen'],
        // Pemeliharaan
        'request.create' => ['Membuat laporan kerusakan', 'Pemeliharaan'],
        'request.view_all' => ['Melihat semua laporan (lintas departemen)', 'Pemeliharaan'],
        'request.triage' => ['Triase laporan & menetapkan prioritas', 'Pemeliharaan'],
        'wo.view' => ['Melihat work order', 'Pemeliharaan'],
        'wo.create' => ['Membuat work order', 'Pemeliharaan'],
        'wo.assign' => ['Menugaskan & menjadwalkan work order', 'Pemeliharaan'],
        'wo.execute' => ['Mengerjakan work order (teknisi)', 'Pemeliharaan'],
        'wo.verify' => ['Memverifikasi hasil pekerjaan', 'Pemeliharaan'],
        'wo.approve' => ['Menyetujui biaya / lingkup work order', 'Pemeliharaan'],
        'wo.close' => ['Menutup & membuka ulang work order', 'Pemeliharaan'],
        'wo.cost_finalize' => ['Finalisasi biaya (Finance)', 'Pemeliharaan'],
        'pm.manage' => ['Mengelola rencana PM & parameter kondisi', 'Pemeliharaan'],
        'condition.record' => ['Mencatat pembacaan kondisi / meter aset', 'Pemeliharaan'],
        'room.block_confirm' => ['Konfirmasi blokir kamar (Front Office)', 'Pemeliharaan'],
        // Pengaturan
        'reference.manage' => ['Lokasi, kategori, departemen, tipe kamar, vendor', 'Pengaturan'],
        'settings.manage' => ['SLA & batas persetujuan', 'Pengaturan'],
        'user.manage' => ['Pengguna & penugasan peran', 'Pengaturan'],
        'backup.manage' => ['Membuat & mengunduh backup database', 'Pengaturan'],
        'role.manage' => ['Peran & izin', 'Pengaturan'],
        'audit.view' => ['Log audit', 'Pengaturan'],
    ];

    /** slug => [nama, deskripsi, izin[]] ('*' = semua izin) */
    public const ROLES = [
        'super-admin' => ['Super Admin', 'Konsultan / vendor aplikasi — semua izin', ['*']],
        'owner' => ['Owner', 'Melihat seluruh kinerja & biaya; memutuskan investasi/penghapusan', ['dashboard.view', 'report.view', 'report.export', 'asset.view', 'asset.view_financial', 'wo.view', 'request.view_all', 'audit.view', 'document.view', 'document.view_restricted']],
        'owner-rep' => ['Owner Representative', 'Memantau risiko aset & anggaran; mengkaji prioritas & usulan penggantian', ['dashboard.view', 'report.view', 'report.export', 'asset.view', 'asset.view_financial', 'wo.view', 'request.view_all', 'audit.view', 'document.view', 'document.view_restricted', 'document.escalation']],
        'gm' => ['General Manager', 'Mengawasi operasi, backlog, downtime, SLA & persetujuan sesuai batas nilai', ['dashboard.view', 'report.view', 'report.export', 'asset.view', 'asset.view_financial', 'request.create', 'request.view_all', 'wo.view', 'wo.approve', 'wo.close', 'transfer.approve', 'template.publish', 'audit.view', 'document.view', 'document.view_restricted', 'document.escalation']],
        'chief-engineering' => ['Chief Engineering', 'Triase, prioritas, penjadwalan teknisi & verifikasi pekerjaan', ['dashboard.view', 'report.view', 'report.export', 'asset.view', 'asset.edit', 'asset.verify', 'request.create', 'request.view_all', 'request.triage', 'wo.view', 'wo.create', 'wo.assign', 'wo.execute', 'wo.verify', 'wo.approve', 'wo.close', 'label.install', 'transfer.request', 'transfer.receive', 'template.manage', 'document.view', 'document.manage', 'pm.manage', 'condition.record']],
        'technician' => ['Teknisi', 'Melihat tugas & aset relevan; mencatat diagnosis, waktu, material & bukti', ['asset.view', 'request.create', 'wo.view', 'wo.execute', 'label.install', 'transfer.receive', 'condition.record']],
        'hod' => ['HOD', 'Melapor, melihat laporan departemennya, mengonfirmasi layanan & kondisi aset', ['asset.view', 'request.create', 'wo.view', 'report.view', 'transfer.request', 'transfer.approve', 'document.view']],
        'reporter' => ['Pelapor (staf)', 'Membuat laporan kerusakan & melihat laporan sendiri', ['asset.view', 'request.create']],
        'front-office' => ['Front Office', 'Melapor & mengonfirmasi blokir kamar terkait perbaikan', ['asset.view', 'request.create', 'room.block_confirm', 'wo.view']],
        'asset-controller' => ['Asset Controller', 'Memelihara register, label & inventarisasi; mengusulkan mutasi & koreksi', ['dashboard.view', 'report.view', 'report.export', 'asset.view', 'asset.edit', 'asset.delete', 'asset.verify', 'asset.activate', 'field.identify', 'template.manage', 'generation.run', 'label.print', 'label.rotate', 'label.install', 'transfer.request', 'transfer.receive', 'reference.manage', 'request.create', 'document.view', 'document.manage', 'pm.manage', 'condition.record']],
        'label-printer' => ['Staf Cetak Label (IT)', 'Mencetak label QR per area di ruang IT & mengonfirmasi hasil cetak', ['asset.view', 'label.print']],
        'field-officer' => ['Petugas Identifikasi Aset', 'Mendata & memverifikasi aset di lapangan lewat ponsel; hanya melihat menu Lapangan', ['field.identify', 'asset.view', 'asset.verify', 'label.install']],
        'finance' => ['Finance / Chief Accountant', 'Data finansial, validasi biaya, anggaran & rekonsiliasi', ['dashboard.view', 'report.view', 'report.export', 'asset.view', 'asset.view_financial', 'asset.edit_financial', 'wo.view', 'wo.approve', 'wo.cost_finalize', 'request.view_all', 'transfer.approve', 'document.view', 'document.view_restricted', 'document.manage', 'policy.manage']],
        'storekeeper' => ['Storekeeper', 'Mengelola stok engineering (rilis B)', ['asset.view', 'wo.view', 'request.create']],
        'purchasing' => ['Purchasing', 'Vendor & referensi pembelian', ['asset.view', 'wo.view', 'request.create', 'reference.manage', 'document.view']],
        'it-admin' => ['IT Administrator', 'Konfigurasi teknis & akun; tidak otomatis berwenang menyetujui biaya', ['asset.view', 'request.create', 'user.manage', 'role.manage', 'reference.manage', 'settings.manage', 'backup.manage', 'audit.view', 'document.view', 'document.manage']],
        'auditor' => ['Auditor', 'Membaca data & jejak audit dalam lingkup yang ditetapkan', ['report.view', 'report.export', 'asset.view', 'asset.view_financial', 'wo.view', 'request.view_all', 'audit.view', 'document.view', 'document.view_restricted']],
    ];
}
