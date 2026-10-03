<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odais', function (Blueprint $table) {
            $table->string('source_url', 500)->nullable()->after('image_path'); // 出典(取り込んだお題のみ)
        });
    }

    public function down(): void
    {
        Schema::table('odais', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });
    }
};
