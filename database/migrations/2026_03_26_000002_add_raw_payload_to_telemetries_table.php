<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('telemetries', function (Blueprint $table) {
            if (!Schema::hasColumn('telemetries', 'raw_payload')) {
                $table->longText('raw_payload')->nullable()->after('tds_mentah');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telemetries', function (Blueprint $table) {
            if (Schema::hasColumn('telemetries', 'raw_payload')) {
                $table->dropColumn('raw_payload');
            }
        });
    }
};
