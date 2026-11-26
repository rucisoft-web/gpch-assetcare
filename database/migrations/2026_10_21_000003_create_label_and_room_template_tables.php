<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** M03 QR & label, template aset per tipe kamar, pembuatan massal, cetak & pemasangan (bagian 6, 24–26). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_labels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            // tepat satu target: aset individual ATAU kelompok kuantitas
            $t->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('quantity_group_id')->nullable()->constrained('asset_quantity_groups')->restrictOnDelete();
            $t->char('token_hash', 64)->unique();
            $t->text('token_ciphertext')->comment('terenkripsi APP_KEY, untuk cetak ulang');
            $t->string('status', 12)->default('active'); // active, revoked
            $t->timestamp('activated_at');
            $t->timestamp('revoked_at')->nullable();
            $t->string('revoke_reason')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            // satu token aktif per target
            $t->string('active_target_key', 40)->nullable()->unique();
            $t->timestamps();
        });

        Schema::create('room_asset_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->unsignedSmallInteger('version')->default(1);
            $t->string('name');
            $t->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $t->string('status', 12)->default('draft'); // draft, published, archived
            $t->text('notes')->nullable();
            $t->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['property_id', 'code', 'version']);
        });

        Schema::create('room_template_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('template_id')->constrained('room_asset_templates')->cascadeOnDelete();
            $t->string('item_key', 40)->comment('stabil lintas versi, mis. tv-utama');
            $t->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $t->string('standard_name');
            $t->unsignedSmallInteger('quantity')->default(1);
            $t->string('mode', 12)->default('individual'); // individual | quantity
            $t->string('unit', 20)->default('unit');
            $t->string('positions')->nullable()->comment('label posisi per unit, pisahkan koma: Kiri, Kanan');
            $t->foreignId('default_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->string('default_criticality', 10)->nullable();
            $t->text('specification')->nullable();
            $t->unsignedSmallInteger('pm_interval_days')->nullable()->comment('usulan; berlaku setelah aktivasi');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
            $t->unique(['template_id', 'item_key']);
        });

        // Slot = posisi standar dalam kamar. Kunci (property, room, item_key, unit_position) tidak berubah lintas versi.
        Schema::create('room_asset_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('room_id')->constrained('locations')->restrictOnDelete();
            $t->string('item_key', 40);
            $t->unsignedSmallInteger('unit_position')->default(1);
            $t->string('position_label')->nullable();
            $t->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $t->string('standard_name');
            $t->string('mode', 12)->default('individual');
            $t->decimal('expected_qty', 14, 2)->nullable()->comment('hanya slot kuantitas');
            $t->foreignId('template_id')->nullable()->constrained('room_asset_templates')->nullOnDelete();
            $t->string('status', 20)->default('empty'); // empty, filled, not_available, archived
            $t->timestamps();
            $t->unique(['property_id', 'room_id', 'item_key', 'unit_position'], 'room_slot_key_unique');
        });

        Schema::create('slot_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('slot_id')->constrained('room_asset_slots')->cascadeOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('quantity_group_id')->nullable()->constrained('asset_quantity_groups')->cascadeOnDelete();
            $t->timestamp('assigned_at');
            $t->timestamp('released_at')->nullable();
            $t->string('assign_reason', 30)->default('generated'); // generated, matched, replacement, transfer
            $t->string('release_reason')->nullable();
            $t->unsignedBigInteger('generation_batch_id')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // satu pemetaan aktif per slot & satu slot aktif per aset (NULL saat dilepas)
            $t->unsignedBigInteger('active_slot_key')->nullable()->unique();
            $t->unsignedBigInteger('active_asset_key')->nullable()->unique();
            $t->timestamps();
        });

        Schema::create('room_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained('locations')->cascadeOnDelete();
            $t->string('item_key', 40);
            $t->string('type', 20); // quantity, added, not_available, substitute
            $t->unsignedSmallInteger('quantity')->nullable()->comment('jumlah aktual untuk tipe quantity/added');
            $t->foreignId('substitute_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $t->string('substitute_name')->nullable();
            $t->string('reason');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['room_id', 'item_key']);
        });

        Schema::create('generation_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('number', 30)->unique();
            $t->foreignId('template_id')->constrained('room_asset_templates')->restrictOnDelete();
            $t->unsignedSmallInteger('template_version');
            $t->string('idempotency_key', 64)->unique();
            $t->json('room_ids');
            $t->json('preview_snapshot')->nullable();
            $t->json('match_decisions')->nullable();
            $t->string('status', 20)->default('previewed'); // previewed, committing, completed, partial, failed, cancelled
            $t->unsignedInteger('created_assets')->default(0);
            $t->unsignedInteger('matched_assets')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('committed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('generation_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('generation_batches')->cascadeOnDelete();
            $t->foreignId('room_id')->constrained('locations')->restrictOnDelete();
            $t->string('status', 12); // ok, failed, conflict, skipped
            $t->unsignedInteger('created_count')->default(0);
            $t->unsignedInteger('matched_count')->default(0);
            $t->unsignedInteger('skipped_count')->default(0);
            $t->text('message')->nullable();
            $t->json('detail')->nullable();
            $t->timestamps();
            $t->unique(['batch_id', 'room_id']);
        });

        Schema::create('label_print_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('number', 30)->unique();
            $t->string('template_version', 20);
            $t->string('media_profile', 30);
            $t->unsignedSmallInteger('start_cell')->default(1);
            $t->json('filters')->nullable();
            $t->string('purpose', 12)->default('new'); // new, reprint
            $t->string('reprint_reason')->nullable();
            $t->unsignedInteger('label_count')->default(0);
            $t->unsignedInteger('sheet_count')->default(0);
            $t->string('status', 20)->default('ready'); // ready, downloaded, printed, cancelled
            $t->string('file_path')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->timestamp('downloaded_at')->nullable();
            $t->timestamp('printed_confirmed_at')->nullable();
            $t->foreignId('printed_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('label_print_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('label_print_batches')->cascadeOnDelete();
            $t->foreignId('asset_label_id')->constrained()->restrictOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('quantity_group_id')->nullable()->constrained('asset_quantity_groups')->nullOnDelete();
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $t->string('location_snapshot')->nullable();
            $t->string('name_snapshot');
            $t->string('code_snapshot', 40);
            $t->string('template_version', 20);
            $t->unsignedSmallInteger('sheet_no')->default(1);
            $t->unsignedSmallInteger('cell_no')->default(1);
            $t->unsignedSmallInteger('copy_no')->default(1);
            $t->string('reprint_reason')->nullable();
            $t->unsignedInteger('sequence')->default(0);
            $t->timestamps();
        });

        // Pemasangan: status terkini diturunkan dari peristiwa terakhir
        Schema::create('installation_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_label_id')->constrained()->restrictOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('quantity_group_id')->nullable()->constrained('asset_quantity_groups')->nullOnDelete();
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('print_item_id')->nullable()->constrained('label_print_items')->nullOnDelete();
            $t->string('status', 20); // pending, installed, damaged, missing, needs_relabel
            $t->string('note')->nullable();
            $t->string('photo_path')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['asset_label_id', 'occurred_at']);
        });

        Schema::create('scan_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('asset_label_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->string('result', 20); // ok, revoked, unknown, forbidden, group
            $t->string('channel', 20)->default('url'); // url, scanner, manual
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('scanned_at');
        });
    }

    public function down(): void
    {
        foreach (['scan_logs', 'installation_events', 'label_print_items', 'label_print_batches', 'generation_results', 'generation_batches', 'room_exceptions', 'slot_assignments', 'room_asset_slots', 'room_template_items', 'room_asset_templates', 'asset_labels'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
