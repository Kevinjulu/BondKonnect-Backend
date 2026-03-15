<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table: portaluserlogintoken
        if (!Schema::hasTable('portaluserlogintoken')) {
            Schema::create('portaluserlogintoken', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->string('IpAddress', 100)->nullable();
                $table->text('Token')->nullable();
                $table->text('UserAgent')->nullable();
                $table->timestamp('LastLogOn')->nullable();
                $table->timestamp('ExpiresAt')->nullable();
                $table->boolean('IsActive')->default(true);
                $table->integer('User')->nullable();
                $table->integer('ActiveRole')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portaluserlogintoken');
    }
};
