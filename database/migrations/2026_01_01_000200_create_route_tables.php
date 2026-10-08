<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Route Planning module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bus_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->string('route_no', 10);
            $table->string('name');
            $table->string('origin');
            $table->string('destination');
            $table->decimal('distance_km', 7, 2);
            $table->unsignedSmallInteger('estimated_duration_minutes');
            $table->string('service_type', 20)->default('normal');
            $table->unsignedSmallInteger('min_capacity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['depot_id', 'route_no']);
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_route_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('distance_from_start_km', 7, 2)->nullable();
            $table->unsignedSmallInteger('minutes_from_start')->nullable();
            $table->timestamps();

            $table->index(['bus_route_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('bus_routes');
    }
};
