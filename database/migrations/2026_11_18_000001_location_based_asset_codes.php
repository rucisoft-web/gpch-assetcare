<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kode aset mencerminkan lokasi & jenis: MB-08-819-TV-01. Kode lama disimpan di former_codes agar tetap bisa dicari. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $t) {
            $t->string('asset_code', 50)->comment('lokasi-jenis-urut, mis. MB-08-819-TV-01')->change();
            $t->text('former_codes')->nullable()->after('asset_code');
        });
        Schema::table('sequences', fn (Blueprint $t) => $t->string('prefix', 60)->change());
        Schema::table('label_print_items', fn (Blueprint $t) => $t->string('code_snapshot', 50)->change());
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $t) => $t->dropColumn('former_codes'));
    }
};
