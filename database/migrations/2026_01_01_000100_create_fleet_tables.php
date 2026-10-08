<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver and Vehicle Management Database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->string('employee_no', 20)->unique();
            $table->string('full_name');
            $table->string('nic', 12)->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20);
            $table->string('address')->nullable();
            $table->string('license_no', 20)->unique();
            $table->string('license_class', 10)->default('D');
            $table->date('license_expiry');
            $table->date('joined_on')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedSmallInteger('max_weekly_hours')->default(60);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->string('registration_no', 20)->unique();
            $table->string('fleet_no', 20)->nullable();
            $table->string('make', 50);
            $table->string('model', 50);
            $table->unsignedSmallInteger('year_of_manufacture')->nullable();
            $table->unsignedSmallInteger('seating_capacity');
            $table->string('service_type', 20)->default('normal');
            $table->string('fuel_type', 20)->default('diesel');
            $table->unsignedInteger('current_mileage')->default(0);
            $table->unsignedInteger('service_interval_km')->default(10000);
            $table->unsignedInteger('last_service_mileage')->nullable();
            $table->date('last_service_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
        Schema::dropIfExists('drivers');
    }
};
