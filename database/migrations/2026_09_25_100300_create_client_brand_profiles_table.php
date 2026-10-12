<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('brand_description')->nullable();
            $table->text('target_audience')->nullable();
            $table->text('tone_of_voice')->nullable();
            $table->json('preferred_colors')->nullable();
            $table->json('brand_fonts')->nullable();
            $table->text('dos')->nullable();
            $table->text('donts')->nullable();
            $table->text('competitors')->nullable();
            $table->json('hashtags')->nullable();
            $table->text('content_preferences')->nullable();
            $table->json('important_urls')->nullable();
            $table->text('reference_accounts')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_guidelines_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_brand_profiles');
    }
};
