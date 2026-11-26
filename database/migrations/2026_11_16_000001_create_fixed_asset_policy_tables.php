<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kebijakan Penyusutan Aset Tetap GPCH (draf FC, November 2026):
 * buku komersial SAK EP (garis lurus, model biaya, pendekatan komponen, konvensi bulan penuh)
 * dan buku fiskal PMK 72/2023 untuk rekonsiliasi. Semua angka kebijakan disimpan sebagai data
 * agar dapat diubah FC tanpa rilis aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // satu baris per properti: batas kapitalisasi, pilihan umur fiskal bangunan, tarif pajak tangguhan
        Schema::create('fixed_asset_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->unique()->constrained()->restrictOnDelete();
            $t->decimal('capitalisation_threshold', 18, 2)->default(5000000)->comment('per unit; di bawahnya dibebankan');
            $t->decimal('group_purchase_threshold', 18, 2)->default(25000000)->comment('pembelian massal satu jenis untuk satu proyek');
            $t->decimal('renovation_threshold', 18, 2)->default(25000000)->comment('per proyek renovasi');
            $t->unsignedSmallInteger('building_fiscal_life_years')->default(40)->comment('20 (PMK) atau umur sesuai buku > 20');
            $t->decimal('deferred_tax_rate', 5, 2)->default(22)->comment('tarif PPh Badan untuk pajak tangguhan (%)');
            $t->date('effective_date')->nullable()->comment('tanggal berlaku kebijakan');
            $t->string('status', 12)->default('draft'); // draft, approved
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // kelompok harta fiskal UU PPh Pasal 11 / PMK 72/2023
        Schema::create('fiscal_groups', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique(); // K1, K2, K3, K4, BP, BTP, NONE
            $t->string('name');
            $t->unsignedSmallInteger('life_months')->nullable()->comment('null = tidak disusutkan');
            $t->string('note')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        // kelas aset kebijakan (LND, BLD, ... CIP) — dasar umur komersial, residu, dan kelompok fiskal
        Schema::create('depreciation_classes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('code', 10);
            $t->string('name');
            $t->string('examples')->nullable();
            $t->boolean('is_depreciable')->default(true);
            $t->boolean('is_intangible')->default(false)->comment('amortisasi (SAK EP Bab 18)');
            $t->unsignedSmallInteger('commercial_life_months')->nullable();
            $t->decimal('residual_pct', 5, 2)->default(0);
            $t->string('fiscal_group_code', 10)->nullable();
            $t->string('fiscal_note')->nullable();
            $t->string('policy_ref', 20)->nullable()->comment('bagian kebijakan, mis. 3 atau 4.1');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['property_id', 'code']);
        });

        Schema::table('asset_categories', function (Blueprint $t) {
            $t->foreignId('depreciation_class_id')->nullable()->after('tracking_mode')->constrained()->nullOnDelete();
            $t->string('fiscal_group_override', 10)->nullable()->after('depreciation_class_id')->comment('mis. AC split: K2 walau kelas ELC');
        });

        Schema::table('asset_financials', function (Blueprint $t) {
            // kelas & parameter (kosong = ikut kategori / kelas)
            $t->foreignId('depreciation_class_id')->nullable()->after('replacement_estimate')->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('commercial_life_months')->nullable()->after('depreciation_class_id');
            $t->decimal('residual_value', 18, 2)->nullable()->after('commercial_life_months');
            $t->date('depreciation_start_date')->nullable()->after('residual_value')->comment('tanggal siap dipakai (komersial)');
            $t->string('fiscal_group_code', 10)->nullable()->after('depreciation_start_date');
            $t->unsignedSmallInteger('fiscal_life_months')->nullable()->after('fiscal_group_code');
            $t->date('fiscal_start_date')->nullable()->after('fiscal_life_months')->comment('bulan pengeluaran (fiskal)');
            // kapitalisasi
            $t->string('capitalisation', 12)->default('capitalised')->after('fiscal_start_date'); // capitalised, group, expensed, pending
            $t->string('group_reference')->nullable()->after('capitalisation')->comment('referensi pembelian kelompok / proyek');
            // saldo awal migrasi (per tanggal potong)
            $t->date('opening_balance_date')->nullable()->after('group_reference');
            $t->decimal('opening_accum_commercial', 18, 2)->nullable()->after('opening_balance_date');
            $t->decimal('opening_accum_fiscal', 18, 2)->nullable()->after('opening_accum_commercial');
            // penurunan nilai & pelepasan
            $t->decimal('impairment_loss', 18, 2)->nullable()->after('opening_accum_fiscal');
            $t->date('disposal_date')->nullable()->after('impairment_loss');
            $t->decimal('disposal_proceeds', 18, 2)->nullable()->after('disposal_date');
        });
    }

    public function down(): void
    {
        Schema::table('asset_financials', function (Blueprint $t) {
            $t->dropConstrainedForeignId('depreciation_class_id');
            $t->dropColumn(['commercial_life_months', 'residual_value', 'depreciation_start_date', 'fiscal_group_code', 'fiscal_life_months', 'fiscal_start_date',
                'capitalisation', 'group_reference', 'opening_balance_date', 'opening_accum_commercial', 'opening_accum_fiscal', 'impairment_loss', 'disposal_date', 'disposal_proceeds']);
        });
        Schema::table('asset_categories', function (Blueprint $t) {
            $t->dropConstrainedForeignId('depreciation_class_id');
            $t->dropColumn('fiscal_group_override');
        });
        Schema::dropIfExists('depreciation_classes');
        Schema::dropIfExists('fiscal_groups');
        Schema::dropIfExists('fixed_asset_policies');
    }
};
