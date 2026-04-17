<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement(
            'DELETE t1 FROM telemetries t1
             INNER JOIN telemetries t2
                 ON t1.kebun = t2.kebun
                 AND t1.recorded_at = t2.recorded_at
                 AND t1.id > t2.id'
        );

        Schema::table('telemetries', function (Blueprint $table) {
            $table->unique(['kebun', 'recorded_at'], 'telemetries_kebun_recorded_at_unique');
        });
    }

    public function down(): void
    {
        Schema::table('telemetries', function (Blueprint $table) {
            $table->dropUnique('telemetries_kebun_recorded_at_unique');
        });
    }
};
