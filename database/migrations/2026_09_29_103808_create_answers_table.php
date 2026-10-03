<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('submitted_at')->useCurrent();
            $table->unsignedSmallInteger('reveal_order')->nullable();
            $table->timestamp('revealed_at')->nullable();
            // offline_laugh mode
            $table->double('raw_volume_sum')->nullable();
            $table->unsignedSmallInteger('normalized_score')->nullable();
            $table->timestamps();
            $table->unique(['round_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
