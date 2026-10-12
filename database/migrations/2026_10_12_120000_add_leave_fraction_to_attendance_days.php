<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->decimal('leave_fraction', 3, 2)->default(1)->after('leave_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropColumn('leave_fraction');
        });
    }
};
