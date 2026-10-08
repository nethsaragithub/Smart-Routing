<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuel and Maintenance Log module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bus_route_id')->nullable()->constrained()->nullOnDelete();
            $table->date('filled_on')->index();
            $table->unsignedInteger('odometer');
            $table->decimal('litres', 8, 2);
            $table->decimal('price_per_litre', 8, 2);
            $table->decimal('total_cost', 10, 2);
            $table->boolean('full_tank')->default(true);
            $table->string('station')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bus_id', 'odometer']);
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('category', 30);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->date('scheduled_for');
            $table->date('started_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('workshop')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('fuel_logs');
    }
};
