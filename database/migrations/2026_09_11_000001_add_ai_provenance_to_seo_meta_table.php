<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_meta', fn (Blueprint $table) => $table->json('ai_provenance')->nullable());
    }

    public function down(): void
    {
        Schema::table('seo_meta', fn (Blueprint $table) => $table->dropColumn('ai_provenance'));
    }
};
