<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('calibration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('kebun')->unique(); // kebun-a, kebun-b
            $table->double('tds_multiplier')->default(1.0);
            $table->double('suhu_correction')->default(0.0);
            $table->timestamps();
        });

        // Insert default values for each kebun
        DB::table('calibration_settings')->insert([
            ['kebun' => 'kebun-a', 'tds_multiplier' => 1.0, 'suhu_correction' => 0.0, 'created_at' => now(), 'updated_at' => now()],
            ['kebun' => 'kebun-b', 'tds_multiplier' => 1.0, 'suhu_correction' => 0.0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('calibration_settings');
    }
};
