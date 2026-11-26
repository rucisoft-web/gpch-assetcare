<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Register Dokumen Aset (draf November 2026): setiap aset boleh punya nol, satu, atau banyak
 * dokumen digital; satu dokumen (mis. polis asuransi) boleh menaungi banyak aset.
 * Berkas disimpan sebagai lampiran privat (hash SHA-256, versi lama tidak dihapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique(); // LGL-TNH-01, K3-01, AST-04, ...
            $t->string('name');
            $t->string('archive_category', 10); // LGL, LGL-IZN, LGL-KTR, AST, PML (Lampiran A SRS ARSIP)
            $t->string('section', 40); // kelompok tampilan: Tanah & bangunan, Izin, K3, ...
            $t->string('validity_hint')->nullable()->comment('pola masa berlaku; tanggal di dokumen yang dipakai');
            $t->boolean('has_expiry')->default(true);
            $t->unsignedSmallInteger('first_reminder_days')->default(90);
            $t->boolean('is_k3')->default(false)->comment('kedaluwarsa → aset "Tidak boleh dioperasikan"');
            $t->boolean('is_insurance')->default(false);
            $t->boolean('is_contract')->default(false);
            $t->string('default_classification', 10)->default('internal'); // umum, internal, terbatas, rahasia
            $t->string('default_pic_role')->nullable();
            $t->string('retention')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // dokumen wajib per kelas aset kebijakan — hanya untuk laporan kekurangan, tidak memblokir
        Schema::create('document_requirements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('depreciation_class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $t->string('condition')->nullable()->comment('mis. "bila ada", "bila angkutan"');
            $t->boolean('is_mandatory')->default(true)->comment('false = dianjurkan (bila ada)');
            $t->timestamps();
            $t->unique(['depreciation_class_id', 'document_type_id'], 'doc_req_class_type_unique');
        });

        Schema::create('asset_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('register_no', 30)->comment('mis. LGL-IZN/2026/0042');
            $t->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('document_number')->nullable();
            $t->string('issuer')->nullable()->comment('instansi / pihak penerbit');
            $t->date('issued_at')->nullable();
            $t->date('valid_until')->nullable()->comment('null + no_expiry = tanpa batas');
            $t->boolean('no_expiry')->default(false);
            $t->unsignedSmallInteger('notice_days')->nullable()->comment('kontrak: batas pemberitahuan sebelum berakhir');
            $t->string('status', 16)->default('valid')->index(); // valid, expiring, expired, renewing, replaced, revoked
            // perpanjangan
            $t->date('renewal_submitted_at')->nullable();
            $t->string('renewal_ref')->nullable();
            $t->decimal('renewal_cost', 18, 2)->nullable();
            $t->foreignId('replaced_by_id')->nullable()->constrained('asset_documents')->nullOnDelete();
            $t->timestamp('revoked_at')->nullable();
            $t->string('revoke_reason')->nullable();
            // tanggung jawab & asli
            $t->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('backup_pic_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('original_location', 16)->nullable(); // brankas, notaris, bank, salinan, unit, lainnya
            $t->string('original_location_note')->nullable()->comment('nama bank & nomor perjanjian, nama notaris, dsb.');
            // tautan lain
            $t->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            // nilai kontrak / polis
            $t->decimal('contract_value', 18, 2)->nullable();
            $t->decimal('premium', 18, 2)->nullable();
            $t->decimal('sum_insured', 18, 2)->nullable();
            $t->string('classification', 10)->default('internal');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['property_id', 'register_no']);
            $t->index(['property_id', 'valid_until']);
        });

        Schema::create('asset_document_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_document_id')->constrained()->cascadeOnDelete();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['asset_document_id', 'asset_id']);
            $t->index('asset_id');
        });

        Schema::table('attachments', function (Blueprint $t) {
            $t->unsignedSmallInteger('file_version')->nullable()->after('checksum')->comment('versi berkas dokumen register');
            $t->string('note')->nullable()->after('file_version');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', fn (Blueprint $t) => $t->dropColumn(['file_version', 'note']));
        Schema::dropIfExists('asset_document_links');
        Schema::dropIfExists('asset_documents');
        Schema::dropIfExists('document_requirements');
        Schema::dropIfExists('document_types');
    }
};
