<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('power_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_name', 50)->index();
            $table->string('state', 20)->index();
            $table->string('mode', 20)->default('AUTO');
            $table->decimal('current_ma', 8, 2);
            $table->timestamp('timestamp')->index();
            $table->boolean('is_estimated')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('power_logs');
    }
};
