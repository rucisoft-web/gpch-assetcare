<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemeliharaan terencana: rencana PM berbasis waktu / meter (→ WO preventive otomatis) dan
 * pemantauan kondisi untuk predictive maintenance (→ WO predictive bila melewati batas).
 */
return new class extends Migration
{
    public function up(): void
    {
        // parameter kondisi / meter: jam operasi genset, suhu chiller, getaran pompa, arus motor, tekanan boiler, ...
        Schema::create('condition_parameters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->string('unit', 20)->nullable();
            $t->boolean('is_meter')->default(false)->comment('nilai kumulatif (jam operasi, km) untuk PM berbasis meter');
            $t->foreignId('category_id')->nullable()->constrained('asset_categories')->nullOnDelete()->comment('kosong = semua kategori');
            $t->string('direction', 8)->default('above'); // above: bahaya bila naik; below: bahaya bila turun
            $t->decimal('warning_value', 14, 3)->nullable();
            $t->decimal('critical_value', 14, 3)->nullable();
            $t->boolean('wo_on_warning')->default(false)->comment('buat WO sejak batas waspada');
            $t->string('critical_priority', 4)->default('P2');
            $t->unsignedSmallInteger('reading_interval_days')->nullable()->comment('jadwal pembacaan rutin');
            $t->text('guidance')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['property_id', 'code']);
        });

        Schema::create('condition_readings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('condition_parameter_id')->constrained()->cascadeOnDelete();
            $t->decimal('value', 14, 3);
            $t->string('status', 10)->default('normal'); // normal, warning, critical
            $t->timestamp('read_at');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('note')->nullable();
            $t->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['asset_id', 'condition_parameter_id', 'read_at'], 'cond_read_asset_param_at');
        });

        Schema::create('pm_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->string('scope', 10)->default('asset'); // asset, category
            $t->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $t->string('wo_type', 20)->default('preventive'); // preventive, inspection
            $t->unsignedSmallInteger('interval_days')->nullable();
            $t->foreignId('meter_parameter_id')->nullable()->constrained('condition_parameters')->nullOnDelete();
            $t->decimal('interval_meter', 14, 3)->nullable();
            $t->date('starts_on')->nullable()->comment('jatuh tempo pertama');
            $t->unsignedSmallInteger('lead_days')->default(7)->comment('WO dibuat H-n sebelum jatuh tempo');
            $t->string('priority', 4)->default('P4');
            $t->json('checklist')->nullable();
            $t->decimal('estimated_hours', 6, 2)->nullable();
            $t->boolean('needs_vendor')->default(false);
            $t->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('instructions')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['property_id', 'code']);
        });

        Schema::create('pm_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pm_plan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->date('last_done_at')->nullable();
            $t->decimal('last_meter', 14, 3)->nullable();
            $t->date('next_due_at')->nullable();
            $t->decimal('next_due_meter', 14, 3)->nullable();
            $t->foreignId('open_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $t->unsignedInteger('done_count')->default(0);
            $t->timestamps();
            $t->unique(['pm_plan_id', 'asset_id']);
        });

        Schema::table('work_orders', function (Blueprint $t) {
            $t->string('source', 12)->default('manual')->after('type'); // manual, request, pm, predictive
            $t->foreignId('pm_schedule_id')->nullable()->after('source')->constrained()->nullOnDelete();
            $t->foreignId('condition_reading_id')->nullable()->after('pm_schedule_id')->constrained()->nullOnDelete();
            $t->json('pm_checklist')->nullable()->after('checklist')->comment('[{item, done}] dari rencana PM');
            $t->date('planned_due_at')->nullable()->after('scheduled_at')->comment('jatuh tempo PM');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pm_schedule_id');
            $t->dropConstrainedForeignId('condition_reading_id');
            $t->dropColumn(['source', 'pm_checklist', 'planned_due_at']);
        });
        Schema::dropIfExists('pm_schedules');
        Schema::dropIfExists('pm_plans');
        Schema::dropIfExists('condition_readings');
        Schema::dropIfExists('condition_parameters');
    }
};
