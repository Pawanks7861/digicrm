<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('lop_days', 5, 2)->default(0)->change();
            $table->decimal('payable_days', 5, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('payable_days')->default(0)->change();
            $table->unsignedSmallInteger('lop_days')->default(0)->change();
        });
    }
};
