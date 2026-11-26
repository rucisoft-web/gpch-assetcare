<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** M04 Laporan kerusakan & work order, SLA, biaya, blokir kamar; M07 mutasi aset (bagian 8, 9, 13, 18). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('number', 30)->unique();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('quantity_group_id')->nullable()->constrained('asset_quantity_groups')->nullOnDelete();
            $t->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('reporter_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->string('reporter_contact')->nullable();
            $t->string('category', 30); // listrik, air, ac, furniture, elektronik, bangunan, keselamatan, it, lainnya
            $t->text('symptom');
            $t->string('impact', 30)->nullable(); // keselamatan, kamar_tidak_dijual, acara_terganggu, layanan_alternatif, kosmetik
            $t->boolean('is_safety')->default(false);
            $t->string('priority', 4)->default('P3');
            $t->string('status', 30)->default('submitted'); // submitted, triaged, linked_to_work_order, resolved, closed, rejected, duplicate
            $t->timestamp('received_at');
            $t->timestamp('response_due_at')->nullable();
            $t->timestamp('response_at')->nullable();
            $t->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('triaged_at')->nullable();
            $t->foreignId('triaged_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status_reason')->nullable();
            $t->foreignId('duplicate_of_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $t->boolean('room_block_proposed')->default(false);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->uuid('client_event_uuid')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['reporter_id', 'client_event_uuid']);
            $t->index(['property_id', 'status']);
        });

        Schema::create('work_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('number', 30)->unique();
            $t->string('type', 20)->default('corrective'); // corrective, preventive, inspection, improvement, emergency
            $t->string('title');
            $t->text('description')->nullable();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $t->string('priority', 4)->default('P3');
            $t->boolean('is_safety')->default(false);
            $t->boolean('is_emergency')->default(false);
            $t->string('status', 20)->default('draft');
            // SLA
            $t->timestamp('received_at')->comment('waktu laporan pertama diterima server');
            $t->timestamp('response_due_at')->nullable();
            $t->timestamp('due_at')->nullable()->comment('target pemulihan');
            $t->string('sla_calendar', 10)->default('24h');
            $t->unsignedInteger('sla_cycle')->default(1)->comment('reopen memulai siklus baru');
            $t->unsignedInteger('hold_minutes')->default(0);
            $t->timestamp('hold_started_at')->nullable();
            $t->string('hold_reason')->nullable();
            // persetujuan
            $t->decimal('estimated_cost', 18, 2)->nullable();
            $t->boolean('requires_approval')->default(false);
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('approval_note')->nullable();
            // penugasan
            $t->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('team_ids')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->decimal('estimated_hours', 6, 2)->nullable();
            $t->boolean('needs_vendor')->default(false);
            $t->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $t->string('warranty_decision', 20)->nullable(); // claim, exception, not_applicable
            $t->json('permits')->nullable()->comment('ptw, loto, hot_work, height, room_access');
            $t->boolean('room_access_needed')->default(false);
            // pelaksanaan
            $t->timestamp('started_at')->nullable();
            $t->string('failure_mode')->nullable();
            $t->text('diagnosis')->nullable();
            $t->text('action_taken')->nullable();
            $t->string('function_test_result', 12)->nullable(); // pass, fail, partial
            $t->text('root_cause')->nullable();
            $t->text('follow_up')->nullable();
            $t->json('checklist')->nullable();
            $t->string('evidence_exception_reason')->nullable();
            $t->timestamp('restored_at')->nullable()->comment('layanan kembali aman (termasuk solusi sementara)');
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            // biaya
            $t->string('cost_status', 12)->default('provisional'); // provisional, final
            $t->string('no_cost_reason')->nullable();
            // verifikasi & penutupan
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->string('verification_note')->nullable();
            $t->string('asset_status_after', 20)->nullable();
            $t->boolean('fo_confirmed')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('reopen_count')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->index(['property_id', 'status']);
            $t->index(['asset_id', 'failure_mode']);
        });

        Schema::create('request_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['service_request_id', 'work_order_id']);
        });

        // Riwayat status laporan & work order (transisi, alasan, aktor, waktu)
        Schema::create('status_events', function (Blueprint $t) {
            $t->id();
            $t->morphs('subject');
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->string('action', 40);
            $t->text('reason')->nullable();
            $t->json('data')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('occurred_at');
        });

        Schema::create('wo_time_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('approved_minutes')->nullable();
            $t->decimal('rate_snapshot', 14, 2)->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('wo_cost_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20); // material, vendor, labor, other
            $t->string('description');
            $t->decimal('quantity', 14, 3)->default(1);
            $t->decimal('unit_cost', 18, 2)->default(0);
            $t->decimal('amount', 18, 2)->default(0);
            $t->string('cost_status', 12)->default('provisional'); // provisional, final
            $t->boolean('is_cash_out')->default(true)->comment('false = alokasi biaya internal');
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('invoice_ref')->nullable();
            $t->foreignId('reverses_id')->nullable()->constrained('wo_cost_lines')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('finalized_at')->nullable();
            $t->string('period', 7)->nullable(); // YYYY-MM
            $t->timestamps();
            $t->unique(['source_type', 'source_id']);
        });

        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->morphs('commentable');
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        // Usulan / blokir kamar: dikonfirmasi Front Office, terpisah dari status aset
        Schema::create('room_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained('locations')->restrictOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('proposed'); // proposed, confirmed, rejected, released
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->string('reason');
            $t->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('fo_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('fo_confirmed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('asset_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('number', 30)->unique();
            $t->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $t->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $t->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->string('reason');
            $t->string('status', 20)->default('draft'); // draft, submitted, approved, dispatched, received, closed, rejected, cancelled
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('dispatched_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('received_at')->nullable();
            $t->string('status_reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });

        Schema::create('asset_transfer_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transfer_id')->constrained('asset_transfers')->cascadeOnDelete();
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->char('condition_out', 1)->nullable();
            $t->char('condition_in', 1)->nullable();
            $t->boolean('received_scan')->default(false);
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['transfer_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        foreach (['asset_transfer_items', 'asset_transfers', 'room_blocks', 'comments', 'wo_cost_lines', 'wo_time_logs', 'status_events', 'request_links', 'work_orders', 'service_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
