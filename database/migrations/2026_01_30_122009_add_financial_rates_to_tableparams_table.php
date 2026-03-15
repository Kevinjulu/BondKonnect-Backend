<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        // Ensure table exists (especially for tests)
        if (!Schema::connection('bk_db')->hasTable('tableparams')) {
            Schema::connection('bk_db')->create('tableparams', function (Blueprint $table) {
                $table->increments('Id');
                $table->decimal('Inflation', 28, 8)->nullable();
                $table->decimal('YtmTr', 28, 8)->nullable();
                $table->integer('DailyBasis')->nullable();
                $table->decimal('CbrRate', 28, 8)->nullable();
                $table->decimal('Level', 28, 8)->nullable();
                $table->decimal('Slope', 28, 8)->nullable();
                $table->decimal('Curvature', 28, 8)->nullable();
                $table->decimal('NseCommission', 10, 6)->default(0.000240)->comment('NSE Commission rate');
                $table->decimal('NseMinCommission', 15, 2)->default(1000.00)->comment('Minimum NSE Commission in KES');
                $table->decimal('CmaLevies', 10, 6)->default(0.000110)->comment('CMA Levies rate');
            });
            
            DB::connection('bk_db')->table('tableparams')->insert([
                'Id' => 1, 
                'Inflation' => 0.05,
                'NseCommission' => 0.000240,
                'NseMinCommission' => 1000.00,
                'CmaLevies' => 0.000110,
            ]);
        } else {
             // If table exists, just update it
            Schema::connection('bk_db')->table('tableparams', function (Blueprint $table) {
                if (!Schema::connection('bk_db')->hasColumn('tableparams', 'NseCommission')) {
                    $table->decimal('NseCommission', 10, 6)->default(0.000240)->comment('NSE Commission rate');
                }
                if (!Schema::connection('bk_db')->hasColumn('tableparams', 'NseMinCommission')) {
                    $table->decimal('NseMinCommission', 15, 2)->default(1000.00)->comment('Minimum NSE Commission in KES');
                }
                if (!Schema::connection('bk_db')->hasColumn('tableparams', 'CmaLevies')) {
                    $table->decimal('CmaLevies', 10, 6)->default(0.000110)->comment('CMA Levies rate');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('bk_db')->hasTable('tableparams')) {
            Schema::connection('bk_db')->table('tableparams', function (Blueprint $table) {
                $table->dropColumn(['NseCommission', 'NseMinCommission', 'CmaLevies']);
            });
        }
    }
};
