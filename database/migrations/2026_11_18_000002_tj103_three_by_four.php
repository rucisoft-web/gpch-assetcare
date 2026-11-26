<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Lembar Tom & Jerry No.103 ternyata 3 × 4 (12 label): buang kalibrasi lama yang masih 2 × 5 agar bawaan baru dipakai. */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('app_settings')->where('key', 'label_cal.tj-103')->first();
        $cal = $row ? json_decode((string) $row->value, true) : null;
        if (is_array($cal) && (int) ($cal['cols'] ?? 0) !== 3) {
            DB::table('app_settings')->where('key', 'label_cal.tj-103')->delete();
            if (class_exists(AppSetting::class) && method_exists(AppSetting::class, 'flush')) {
                AppSetting::flush();
            }
        }
    }

    public function down(): void {}
};
