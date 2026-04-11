<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('portaluserotphistory')) {
            Schema::create('portaluserotphistory', function (Blueprint $table) {
                $table->increments('Id');
                $table->string('Otp');
                $table->unsignedBigInteger('User');
                $table->timestamp('OtpExpiry');
                $table->boolean('IsActive')->default(false);
                $table->timestamp('created_on')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portaluserotphistory');
    }
};
