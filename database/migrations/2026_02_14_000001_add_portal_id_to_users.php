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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('portal_id')->nullable()->unique()->after('id')->comment('Legacy portaluserlogoninfo.Id mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'portal_id')) {
                $table->dropUnique(['portal_id']);
                $table->dropColumn('portal_id');
            }
        });
    }
};
