<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis ruang per lokasi: setiap lokasi (mis. Kamar 401) bisa ditautkan ke satu templat
 * (mis. "Kamar Deluxe"), sehingga aset bisa dibangkitkan massal sesuai jenis ruangnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('asset_template_id')->nullable()->after('parent_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_template_id');
        });
    }
};
