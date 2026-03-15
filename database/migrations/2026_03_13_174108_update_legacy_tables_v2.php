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
        // Table: statstable
        if (!Schema::hasTable('statstable')) {
            Schema::create('statstable', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->string('Otr', 100)->nullable();
                $table->integer('Filter1')->nullable();
                $table->integer('Filter2')->nullable();
                $table->integer('Id_')->nullable();
                $table->string('BondIssueNo', 100)->nullable();
                $table->timestamp('IssueDate')->nullable();
                $table->timestamp('MaturityDate')->nullable();
                $table->timestamp('ValueDate')->nullable();
                $table->string('QuotedYield', 100)->nullable();
                $table->decimal('SpotYield', 28, 8)->nullable();
                $table->decimal('DirtyPrice', 28, 8)->nullable();
                $table->decimal('Coupon', 28, 8)->nullable();
                $table->integer('NextCpnDays')->nullable();
                $table->decimal('DtmYrs', 28, 8)->nullable();
                $table->decimal('Dtc', 28, 8)->nullable();
                $table->decimal('Duration', 28, 8)->nullable();
                $table->decimal('MDuration', 28, 8)->nullable();
                $table->decimal('Convexity', 28, 8)->nullable();
                $table->decimal('ExpectedReturn', 28, 8)->nullable();
                $table->decimal('ExpectedShortfall', 28, 8)->nullable();
                $table->decimal('Dv01', 28, 8)->nullable();
                $table->integer('Last91Days')->nullable();
                $table->integer('Last364Days')->nullable();
                $table->string('LqdRank', 100)->nullable();
                $table->decimal('Spread', 28, 8)->nullable();
                $table->decimal('CreditRiskPremium', 28, 8)->nullable();
                $table->integer('MdRank')->nullable();
                $table->integer('ErRank')->nullable();
                $table->integer('Basis')->nullable();
                $table->integer('DayCount')->nullable();
                $table->timestamp('FirstCallDate')->nullable();
                $table->timestamp('SecondCallDate')->nullable();
                $table->decimal('ParCall1', 28, 8)->nullable();
                $table->decimal('ParCall2', 28, 8)->nullable();
                $table->decimal('BtwCalls', 28, 8)->nullable();
            });
        }

        // Table: primarymarkettable
        if (!Schema::hasTable('primarymarkettable')) {
            Schema::create('primarymarkettable', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->string('BondIssueNo', 100)->nullable();
                $table->timestamp('IssueDate')->nullable();
                $table->timestamp('MaturityDate')->nullable();
                $table->timestamp('FirstCallDate')->nullable();
                $table->timestamp('SecondCallDate')->nullable();
                $table->string('ParCall1Percent', 100)->nullable();
                $table->string('ParCall2Percent', 100)->nullable();
                $table->string('PricingMethod', 100)->nullable();
                $table->decimal('DtmOrWal', 28, 8)->nullable();
                $table->integer('DayCount')->nullable();
                $table->timestamp('FirstCouponDate')->nullable();
                $table->timestamp('SecondCouponDate')->nullable();
                $table->string('SpotRate', 100)->nullable();
                $table->string('ParYield', 100)->nullable();
            });
        }

        // Table: portfolio
        if (!Schema::hasTable('portfolio')) {
            Schema::create('portfolio', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->string('Name', 100)->nullable();
                $table->text('Description')->nullable();
                $table->timestamp('ValueDate')->nullable();
                $table->integer('UserId')->nullable();
            });
        }

        // Table: portfoliodata
        if (!Schema::hasTable('portfoliodata')) {
            Schema::create('portfoliodata', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->integer('PortfolioId')->nullable();
                $table->integer('BondId')->nullable();
                $table->integer('User')->nullable();
                $table->string('Type', 100)->nullable();
                $table->timestamp('BuyingDate')->nullable();
                $table->timestamp('SellingDate')->nullable();
                $table->decimal('BuyingPrice', 28, 8)->nullable();
                $table->decimal('SellingPrice', 28, 8)->nullable();
                $table->decimal('BuyingWAP', 28, 8)->nullable();
                $table->decimal('SellingWAP', 28, 8)->nullable();
                $table->integer('FaceValueBuys')->nullable();
                $table->integer('FaceValueSales')->nullable();
                $table->integer('FaceValueBAL')->nullable();
                $table->decimal('ClosingPrice', 28, 8)->nullable();
                $table->decimal('CouponNET', 28, 8)->nullable();
                $table->integer('NextCpnDays')->nullable();
                $table->decimal('RealizedPNL', 28, 8)->nullable();
                $table->decimal('UnrealizedPNL', 28, 8)->nullable();
                $table->decimal('OneYrTotalReturn', 28, 8)->nullable();
                $table->decimal('DirtyPrice', 28, 8)->nullable();
                $table->integer('PortfolioValue')->nullable();
                $table->decimal('SpotYTM', 28, 8)->nullable();
                $table->decimal('Coupon', 28, 8)->nullable();
                $table->decimal('Duration', 28, 8)->nullable();
                $table->decimal('MDuration', 28, 8)->nullable();
                $table->decimal('Dv01', 28, 8)->nullable();
                $table->decimal('ExpectedShortfall', 28, 8)->nullable();
                $table->boolean('IsActive')->default(true);
            });
        }

        // Update quotebook table if it exists but is missing columns
        if (Schema::hasTable('quotebook')) {
            Schema::table('quotebook', function (Blueprint $table) {
                if (!Schema::hasColumn('quotebook', 'PlacementNo')) {
                    $table->integer('created_by')->nullable();
                    $table->integer('altered_by')->nullable();
                    $table->timestamp('dola')->nullable();
                    $table->string('PlacementNo', 100)->nullable();
                    $table->integer('AssignedBy')->nullable();
                    $table->integer('ViewingParty')->nullable();
                    $table->integer('BondIssueNo')->nullable();
                    $table->timestamp('SettlementDate')->nullable();
                    $table->decimal('BidPrice', 28, 8)->nullable();
                    $table->decimal('BidYield', 28, 8)->nullable();
                    $table->decimal('BidAmount', 28, 8)->nullable();
                    $table->decimal('IndicativeLower', 28, 8)->nullable();
                    $table->decimal('IndicativeHigher', 28, 8)->nullable();
                    $table->decimal('OfferYield', 28, 8)->nullable();
                    $table->decimal('OfferAmount', 28, 8)->nullable();
                    $table->decimal('OfferPrice', 28, 8)->nullable();
                    $table->decimal('Consideration', 28, 8)->nullable();
                    $table->decimal('CommissionNSE', 28, 8)->nullable();
                    $table->decimal('OtherLevies', 28, 8)->nullable();
                    $table->decimal('TotalPayable', 28, 8)->nullable();
                    $table->decimal('TotalReceivable', 28, 8)->nullable();
                    $table->boolean('IsAccepted')->nullable();
                    $table->timestamp('ExitDate')->nullable();
                    $table->boolean('IsBid')->nullable();
                    $table->boolean('IsOffer')->nullable();
                    $table->boolean('IsActive')->nullable();
                }
            });
        }

        // Table: obitable
        if (!Schema::hasTable('obitable')) {
            Schema::create('obitable', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->timestamp('Date')->nullable();
                $table->string('QuotedYield', 100)->nullable();
                $table->string('SpotYield', 100)->nullable();
                $table->string('DirtyPrice', 100)->nullable();
                $table->string('ObiKIndex', 100)->nullable();
                $table->string('Coupon', 100)->nullable();
                $table->string('Duration', 100)->nullable();
                $table->string('ExpectedReturn', 100)->nullable();
                $table->string('Dv01', 100)->nullable();
                $table->string('ExpectedShortfall', 100)->nullable();
                $table->string('ObiTr', 100)->nullable();
            });
        }

        // Table: graphtable
        if (!Schema::hasTable('graphtable')) {
            Schema::create('graphtable', function (Blueprint $table) {
                $table->increments('Id');
                $table->integer('created_by')->nullable();
                $table->timestamp('created_on')->nullable();
                $table->integer('altered_by')->nullable();
                $table->timestamp('dola')->nullable();
                $table->timestamp('Date')->nullable();
                $table->string('Year', 100)->nullable();
                $table->string('SpotRate', 100)->nullable();
                $table->string('NseRate', 100)->nullable();
                $table->string('UpperBand', 100)->nullable();
                $table->string('LowerBand', 100)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graphtable');
        Schema::dropIfExists('obitable');
        Schema::dropIfExists('portfoliodata');
        Schema::dropIfExists('portfolio');
        Schema::dropIfExists('primarymarkettable');
        Schema::dropIfExists('statstable');
    }
};
