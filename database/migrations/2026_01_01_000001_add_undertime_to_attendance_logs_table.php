<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->boolean('is_undertime')->default(false)->after('is_late');
            $table->integer('undertime_minutes')->nullable()->after('is_undertime');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['is_undertime', 'undertime_minutes']);
        });
    }
};
