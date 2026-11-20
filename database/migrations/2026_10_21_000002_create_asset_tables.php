<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** M02 Register aset: kategori, aset individual, keuangan terbatas, komponen, histori status, kelompok kuantitas, lampiran. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->string('group', 30)->nullable(); // kamar, mice, kitchen, mep, publik, keselamatan, it
            $t->string('default_criticality', 10)->default('medium');
            $t->string('tracking_mode', 12)->default('individual'); // individual | quantity
            $t->unsignedSmallInteger('default_pm_interval_days')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['property_id', 'code']);
        });

        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('asset_code', 30)->comment('permanen, dibuat server, mis. GPCH-AST-000001');
            $t->string('name');
            $t->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $t->string('brand')->nullable();
            $t->string('model')->nullable();
            $t->string('serial_number')->nullable();
            $t->string('photo_path')->nullable();
            // penempatan
            $t->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('custodian_unit')->nullable()->comment('unit penanggung jawab bila bukan perorangan');
            $t->foreignId('parent_id')->nullable()->constrained('assets')->restrictOnDelete()->comment('cache komponen aktif');
            // kondisi & risiko (dimensi berdiri sendiri)
            $t->char('condition_grade', 1)->nullable(); // A..E
            $t->string('criticality', 10)->nullable(); // low, medium, high, critical
            $t->string('operational_status', 20)->default('serviceable'); // serviceable, degraded, under_repair, out_of_service
            $t->string('lifecycle_status', 12)->default('draft'); // draft, active, retired, disposed, lost
            $t->boolean('in_transit')->default(false);
            $t->timestamp('last_inspected_at')->nullable();
            // perolehan (non-finansial)
            $t->string('ownership', 20)->nullable(); // milik, sewa, konsinyasi, vendor
            $t->date('purchase_date')->nullable();
            $t->date('in_service_date')->nullable();
            $t->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $t->string('invoice_ref')->nullable();
            $t->string('funding_source')->nullable();
            // perawatan
            $t->unsignedSmallInteger('pm_interval_days')->nullable();
            $t->text('specification')->nullable();
            $t->date('warranty_until')->nullable();
            $t->string('contract_ref')->nullable();
            // kualitas data
            $t->string('verification_status', 20)->default('unverified'); // unverified, verified, discrepancy
            $t->string('legacy_code')->nullable();
            $t->string('migration_source')->nullable();
            $t->foreignId('generation_batch_id')->nullable()->index();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->string('installation_status', 20)->default('pending'); // turunan peristiwa pemasangan terakhir
            $t->unsignedInteger('version')->default(1);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['property_id', 'asset_code']);
            $t->index(['property_id', 'lifecycle_status']);
            $t->index(['property_id', 'location_id']);
            $t->index('serial_number');
            $t->index('legacy_code');
        });

        Schema::create('asset_financials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->unique()->constrained()->cascadeOnDelete();
            $t->date('acquisition_date')->nullable();
            $t->decimal('acquisition_cost', 18, 2)->nullable();
            $t->decimal('replacement_estimate', 18, 2)->nullable();
            $t->string('cost_center', 40)->nullable();
            $t->string('accounting_ref')->nullable();
            $t->string('verification_status', 20)->default('unverified');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('asset_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_asset_id')->constrained('assets')->cascadeOnDelete();
            $t->foreignId('component_asset_id')->constrained('assets')->cascadeOnDelete();
            $t->timestamp('installed_at');
            $t->timestamp('removed_at')->nullable();
            $t->unsignedBigInteger('work_order_id')->nullable();
            $t->string('reason')->nullable();
            $t->foreignId('replaced_component_id')->nullable()->constrained('assets')->nullOnDelete();
            // satu induk aktif per komponen: kolom bernilai component_asset_id saat aktif, NULL saat dilepas
            $t->unsignedBigInteger('active_component_key')->nullable()->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('asset_status_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->string('dimension', 30); // condition_grade, operational_status, lifecycle_status, location_id, verification_status, criticality, department_id
            $t->string('old_value')->nullable();
            $t->string('new_value')->nullable();
            $t->string('old_label')->nullable();
            $t->string('new_label')->nullable();
            $t->string('reason')->nullable();
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('occurred_at');
            $t->index(['asset_id', 'dimension']);
        });

        Schema::create('asset_quantity_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('group_code', 30);
            $t->foreignId('location_id')->constrained()->restrictOnDelete();
            $t->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $t->string('name');
            $t->string('unit', 20)->default('pcs');
            $t->decimal('on_hand', 14, 2)->nullable()->comment('null = belum dihitung');
            $t->decimal('expected_qty', 14, 2)->nullable();
            $t->string('status', 12)->default('draft'); // draft, active, retired
            $t->foreignId('generation_batch_id')->nullable()->index();
            $t->string('installation_status', 20)->default('pending');
            $t->timestamps();
            $t->unique(['property_id', 'group_code']);
        });

        Schema::create('quantity_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quantity_group_id')->constrained('asset_quantity_groups')->cascadeOnDelete();
            $t->string('type', 20); // count, add, remove, adjust
            $t->decimal('qty_before', 14, 2)->nullable();
            $t->decimal('qty_after', 14, 2);
            $t->string('reason')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('occurred_at');
        });

        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->morphs('attachable');
            $t->string('kind', 30)->default('evidence'); // photo, nameplate, manual, warranty, certificate, before, after, evidence
            $t->string('disk', 20)->default('local');
            $t->string('path');
            $t->string('original_name')->nullable();
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->string('checksum', 64)->nullable();
            $t->string('visibility', 20)->default('internal'); // internal, financial
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['attachments', 'quantity_movements', 'asset_quantity_groups', 'asset_status_events', 'asset_components', 'asset_financials', 'assets', 'asset_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
