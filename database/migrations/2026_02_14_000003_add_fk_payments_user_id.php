<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transactions for this migration.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only add FK if column exists
        if (Schema::hasTable('payments') && Schema::hasTable('users')) {
            Schema::table('payments', function (Blueprint $table) {
                // Add foreign key; Laravel will handle if not present
                $table->foreign('user_id', 'fk_payments_user_id')
                    ->references('id')->on('users')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                // Drop FK if exists
                $table->dropForeign('fk_payments_user_id');
            });
        }
    }
};
