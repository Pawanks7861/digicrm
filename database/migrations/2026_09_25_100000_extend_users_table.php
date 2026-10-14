<?php

use App\Support\MysqlSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->foreignId('client_id')->nullable()->after('job_title');
            $table->boolean('is_active')->default(true)->after('client_id');
            $table->softDeletes();
        });

        MysqlSchema::ensureInnoDB('users');
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'client_id')) {
            MysqlSchema::dropForeignKeyOnColumn('users', 'client_id');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'job_title', 'client_id', 'is_active', 'deleted_at']);
        });
    }
};
