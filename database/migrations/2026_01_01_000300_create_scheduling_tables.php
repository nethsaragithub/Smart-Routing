<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schedule Management module: recurring timetables, the daily trips they
 * generate, and an audit log of every adjustment made to a trip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->string('recurrence', 10)->default('daily');
            $table->json('weekdays')->nullable();
            $table->json('month_days')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bus_id', 'status']);
            $table->index(['driver_id', 'status']);
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bus_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->date('trip_date')->index();
            $table->dateTime('scheduled_departure');
            $table->dateTime('scheduled_arrival');
            $table->dateTime('actual_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->unsignedSmallInteger('delay_minutes')->default(0);
            $table->unsignedInteger('odometer_start')->nullable();
            $table->unsignedInteger('odometer_end')->nullable();
            $table->unsignedSmallInteger('passenger_count')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['schedule_id', 'trip_date']);
        });

        Schema::create('trip_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('reason', 30)->nullable();
            $table->string('details');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_adjustments');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('schedules');
    }
};
