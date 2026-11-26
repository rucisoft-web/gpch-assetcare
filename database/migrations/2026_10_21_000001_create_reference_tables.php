<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** M01 Referensi & akses: properti, departemen, lokasi, tipe kamar, peran, izin, audit, penomoran. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('timezone', 40)->default('Asia/Jakarta');
            $t->string('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('code', 20);
            $t->string('name');
            $t->string('cost_center', 40)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['property_id', 'code']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('property_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
            $t->string('job_title')->nullable()->after('name');
            $t->string('phone', 40)->nullable()->after('email');
            $t->boolean('is_active')->default(true);
            $t->boolean('must_change_password')->default(false);
            $t->timestamp('password_changed_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->text('app_authentication_secret')->nullable();
            $t->text('app_authentication_recovery_codes')->nullable();
            $t->decimal('labor_rate', 14, 2)->nullable()->comment('tarif manajerial per jam (internal)');
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('name');
            $t->string('description')->nullable();
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('name');
            $t->string('group', 60)->nullable();
            $t->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });

        // Peran per properti (property_id null = semua properti yang diizinkan)
        Schema::create('role_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unique(['role_id', 'user_id', 'property_id']);
        });

        Schema::create('room_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('code', 20);
            $t->string('name');
            $t->string('bed_config', 40)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['property_id', 'code']);
        });

        // properti → gedung/zona → lantai → ruang/kamar → titik pemasangan
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $t->string('code', 40);
            $t->string('name');
            $t->string('location_type', 20); // building, zone, floor, room, venue, area, point
            $t->foreignId('room_type_id')->nullable()->constrained()->nullOnDelete();
            $t->string('room_ref', 20)->nullable()->comment('nomor kamar di PMS');
            $t->string('floor_label', 20)->nullable();
            $t->string('sort_key', 60)->nullable()->comment('urutan natural: gedung/lantai/nomor');
            $t->string('pms_room_status', 30)->nullable()->comment('informasi terpisah dari status aset');
            $t->string('full_path')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['property_id', 'code']);
            $t->index(['property_id', 'location_type']);
            $t->index('sort_key');
        });

        Schema::create('vendors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->string('specialization')->nullable();
            $t->string('contact_person')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('service_hours')->nullable();
            $t->string('sla_note')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['property_id', 'code']);
        });

        Schema::create('sla_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('priority', 4); // P1..P4
            $t->string('name');
            $t->string('example')->nullable();
            $t->unsignedInteger('response_minutes');
            $t->unsignedInteger('recovery_minutes')->nullable()->comment('null = target per kasus');
            $t->string('calendar', 10)->default('24h'); // 24h | business
            $t->timestamps();
            $t->unique(['property_id', 'priority']);
        });

        // Batas persetujuan berjenjang dengan masa berlaku & pengganti sementara
        Schema::create('approval_limits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('object_type', 30)->default('work_order'); // work_order, transfer, disposal
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->decimal('max_amount', 18, 2)->nullable()->comment('null = tanpa batas nominal');
            $t->foreignId('delegate_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('prefix', 20);
            $t->unsignedSmallInteger('year')->default(0);
            $t->unsignedBigInteger('last_number')->default(0);
            $t->timestamps();
            $t->unique(['property_id', 'prefix', 'year']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event', 30);
            $t->string('auditable_type');
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->string('auditable_label')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('reason')->nullable();
            $t->string('request_id', 40)->nullable();
            $t->string('url', 500)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        // Kunci deduplikasi notifikasi (mis. eskalasi SLA hanya sekali per ambang)
        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->string('dedupe_key', 191)->unique();
            $t->string('channel', 20)->default('database');
            $t->string('status', 20)->default('sent');
            $t->unsignedInteger('recipients')->default(0);
            $t->string('error')->nullable();
            $t->timestamps();
        });

        // Idempotensi kiriman dari perangkat (client_event_uuid)
        Schema::create('client_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid');
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('action', 60);
            $t->string('payload_hash', 64);
            $t->json('result')->nullable();
            $t->timestamp('device_time')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'action', 'uuid']);
        });
    }

    public function down(): void
    {
        foreach (['client_events', 'notification_logs', 'notifications', 'audit_logs', 'sequences', 'approval_limits', 'sla_policies', 'vendors', 'locations', 'room_types', 'role_user', 'permission_role', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('department_id');
            $t->dropConstrainedForeignId('property_id');
            $t->dropColumn(['job_title', 'phone', 'is_active', 'must_change_password', 'password_changed_at', 'last_login_at', 'app_authentication_secret', 'app_authentication_recovery_codes', 'labor_rate']);
        });
        Schema::dropIfExists('departments');
        Schema::dropIfExists('properties');
    }
};
