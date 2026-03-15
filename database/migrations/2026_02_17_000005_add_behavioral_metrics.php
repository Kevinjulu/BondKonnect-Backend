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
        Schema::table('user_credibility_scores', function (Blueprint $table) {
            // Recency-weighted calculation columns
            $table->decimal('recent_50_average', 5, 2)->default(0)->comment('Average rating of last 50 transactions');
            $table->decimal('mid_50_average', 5, 2)->default(0)->comment('Average rating of transactions 51-100');
            $table->decimal('older_average', 5, 2)->default(0)->comment('Average rating of transactions 101+');
            $table->decimal('recency_weighted_score', 5, 2)->default(0)->comment('Weighted credibility score (70% recent, 20% mid, 10% older)');

            // Improvement trend tracking columns
            $table->string('improvement_trend', 10)->default('stable')->comment('Trend direction: ↑ (improving), ↗ (slight improvement), → (stable), ↘ (declining), ↓ (worsening)');
            $table->decimal('last_6_months_change', 5, 2)->default(0)->comment('Change in score over last 6 months');
            $table->string('trend_direction', 20)->default('stable')->comment('Direction: improving, stable, or declining');

            // Observation status (for transparency, not restriction)
            $table->string('observation_status', 20)->default('normal')->comment('Status: normal, observation, or watch (transparency tracking)');
            $table->text('observation_notes')->nullable()->comment('Notes about current performance trajectory');

            // Tracking timestamps
            $table->timestamp('recency_weighted_updated_at')->nullable()->comment('Last time recency weighted score was calculated');
            $table->timestamp('trend_calculated_at')->nullable()->comment('Last time trend was calculated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_credibility_scores', function (Blueprint $table) {
            $table->dropColumn([
                'recent_50_average',
                'mid_50_average',
                'older_average',
                'recency_weighted_score',
                'improvement_trend',
                'last_6_months_change',
                'trend_direction',
                'observation_status',
                'observation_notes',
                'recency_weighted_updated_at',
                'trend_calculated_at',
            ]);
        });
    }
};
