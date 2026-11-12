<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Modul bagian umum: ruang rapat, kendaraan dinas, ATK & persediaan, dokumen & perizinan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->default(10);
            $table->text('facilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('room_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40)->nullable();
            $table->foreignId('meeting_room_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('attendees')->default(1);
            $table->text('needs')->nullable();
            $table->string('state', 20)->default('requested');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'starts_at']);
            $table->index(['meeting_room_id', 'starts_at']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('plate_number', 20);
            $table->string('name');
            $table->string('type', 20)->default('car');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->unsignedSmallInteger('seats')->nullable();
            $table->unsignedInteger('odometer')->default(0);
            $table->string('status', 20)->default('available');
            $table->string('default_driver')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->date('tax_due_date')->nullable();
            $table->date('stnk_due_date')->nullable();
            $table->date('kir_due_date')->nullable();
            $table->date('insurance_due_date')->nullable();
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_km')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'plate_number']);
        });

        Schema::create('vehicle_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40)->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose');
            $table->string('destination');
            $table->unsignedSmallInteger('passengers')->default(1);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('driver_name')->nullable();
            $table->string('state', 20)->default('requested');
            $table->unsignedInteger('odometer_start')->nullable();
            $table->unsignedInteger('odometer_end')->nullable();
            $table->unsignedInteger('distance_km')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'starts_at']);
            $table->index(['vehicle_id', 'starts_at']);
        });

        Schema::create('vehicle_fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('filled_at');
            $table->unsignedInteger('odometer')->nullable();
            $table->decimal('liters', 8, 2);
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('station')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'filled_at']);
        });

        Schema::create('vehicle_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->unsignedInteger('odometer')->nullable();
            $table->string('service_type', 20)->default('routine');
            $table->string('workshop')->nullable();
            $table->text('description')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_km')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'service_date']);
        });

        Schema::create('supply_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('category', 20)->default('stationery');
            $table->string('unit', 20)->default('pcs');
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->string('storage_location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40)->nullable();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('requested_at');
            $table->date('needed_by')->nullable();
            $table->text('purpose')->nullable();
            $table->string('state', 20)->default('submitted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'state']);
        });

        Schema::create('supply_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('issued_quantity', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 12, 2);
            $table->decimal('stock_after', 12, 2)->default(0);
            $table->date('moved_at');
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('supply_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'moved_at']);
        });

        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document_type', 20)->default('license');
            $table->string('number')->nullable();
            $table->string('issuer')->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedSmallInteger('reminder_days')->default(60);
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        foreach (['legal_documents', 'stock_movements', 'supply_request_lines', 'supply_requests', 'supply_items', 'vehicle_services', 'vehicle_fuel_logs', 'vehicle_bookings', 'vehicles', 'room_bookings', 'meeting_rooms'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
