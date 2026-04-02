<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('telemetries', function (Blueprint $table) {
            $table->id();
            $table->string('kebun')->nullable();
            $table->double('ph')->nullable();
            $table->integer('tds')->nullable();
            $table->double('suhu')->nullable();
            $table->double('cal_ph_netral')->nullable();
            $table->double('cal_ph_asam')->nullable();
            $table->double('cal_tds_k')->nullable();
            $table->integer('tds_mentah')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('telemetries');
    }
};
