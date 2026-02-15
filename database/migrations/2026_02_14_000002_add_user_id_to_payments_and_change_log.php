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
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('plan_id')->index()->comment('FK to users.id');
        });

        // Change log for backfill operations
        Schema::create('payment_user_backfill_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->index();
            $table->unsignedBigInteger('old_user_id')->nullable();
            $table->unsignedBigInteger('new_user_id')->nullable();
            $table->string('run_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });

        Schema::dropIfExists('payment_user_backfill_log');
    }
};
