<?php

use Illuminate\Support\Facades\Schedule;

// Peringatan SLA (80% → PIC & CE, terlewati → GM). Deduplikasi mencegah notifikasi ganda.
Schedule::command('assetcare:sla-check')->everyMinute()->withoutOverlapping();

// Register dokumen: status masa berlaku + pengingat (deduplikasi per titik pengingat).
Schedule::command('assetcare:document-reminders')->dailyAt('07:00')->timezone(config('assetcare.display_timezone', 'Asia/Jakarta'))->withoutOverlapping();

// Preventive maintenance: WO otomatis untuk jadwal jatuh tempo (tanggal H-lead atau meter tercapai).
Schedule::command('assetcare:pm-generate')->dailyAt('06:00')->timezone(config('assetcare.display_timezone', 'Asia/Jakarta'))->withoutOverlapping();

// Label QR untuk aset yang masuk lewat impor SQL (token dibuat dengan APP_KEY server). Aman diulang.
Schedule::command('assetcare:labels-ensure')->everyTenMinutes()->withoutOverlapping();

// Backup database harian (.sql bertanggal dalam ZIP) yang bisa diunduh IT di menu Pengaturan › Backup database
Schedule::command('assetcare:backup-db')->dailyAt('01:30')->timezone(config('assetcare.display_timezone', 'Asia/Jakarta'))->withoutOverlapping();
