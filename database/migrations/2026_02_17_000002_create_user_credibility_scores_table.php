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
        Schema::create('user_credibility_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();

            // Score components
            $table->integer('total_ratings_count')->default(0);
            $table->decimal('average_overall_rating', 3, 2)->default(0);
            $table->decimal('rating_score', 5, 2)->default(0);
            $table->decimal('activity_score', 5, 2)->default(0);
            $table->integer('verification_score')->default(0); // 0, 30, or 100
            $table->decimal('settlement_score', 5, 2)->default(0);
            $table->decimal('response_time_score', 5, 2)->default(0);

            // Composite credibility score
            $table->decimal('credibility_index', 5, 2)->default(0);
            $table->string('credibility_badge', 20)->default('unrated'); // platinum, gold, silver, bronze, unrated

            // Rating distributions
            $table->integer('positive_rating_count')->default(0); // ratings >= 4
            $table->integer('neutral_rating_count')->default(0);  // ratings = 3
            $table->integer('negative_rating_count')->default(0); // ratings <= 2

            // Activity metrics
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_transaction_volume', 15, 2)->default(0); // in KES
            $table->timestamp('last_transaction_date')->nullable();

            // Disputes
            $table->integer('total_disputes')->default(0);
            $table->integer('resolved_disputes')->default(0);

            // Flags
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_new_user')->default(true);

            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indices
            $table->index('user_id');
            $table->index('credibility_badge');
            $table->index('is_trusted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_credibility_scores');
    }
};
