<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('bk_db')->table('tableparams', function (Blueprint $table) {
            if (!Schema::connection('bk_db')->hasColumn('tableparams', 'NseCommission')) {
                $table->decimal('NseCommission', 10, 6)->default(0.000240)->after('DailyBasis')->comment('NSE Commission rate (e.g. 0.00024 for 0.024%)');
            }
            if (!Schema::connection('bk_db')->hasColumn('tableparams', 'NseMinCommission')) {
                $table->decimal('NseMinCommission', 15, 2)->default(1000.00)->after('NseCommission')->comment('Minimum NSE Commission in KES');
            }
            if (!Schema::connection('bk_db')->hasColumn('tableparams', 'CmaLevies')) {
                $table->decimal('CmaLevies', 10, 6)->default(0.000110)->after('NseMinCommission')->comment('CMA Levies rate (e.g. 0.00011 for 0.011%)');
            }
        });

        // Initialize existing record if it exists
        DB::connection('bk_db')->table('tableparams')->where('Id', 1)->update([
            'NseCommission' => 0.000240,
            'NseMinCommission' => 1000.00,
            'CmaLevies' => 0.000110,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('bk_db')->table('tableparams', function (Blueprint $table) {
            $table->dropColumn(['NseCommission', 'NseMinCommission', 'CmaLevies']);
        });
    }
};
