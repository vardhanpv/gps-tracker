<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();

            $table->foreignId('representative_id')
                ->constrained('representatives')
                ->cascadeOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('recorded_at')->index();

            $table->timestamps();

            $table->index(['representative_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_logs');
    }
};