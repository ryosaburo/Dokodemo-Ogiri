<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('odai_id')->constrained();
            $table->unsignedSmallInteger('round_number');
            $table->string('status', 20)->default('collecting'); // collecting / revealing / voting / finished
            $table->unsignedSmallInteger('time_limit_sec')->default(60);
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
            $table->unique(['room_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
