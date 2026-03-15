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
        Schema::create('user_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rater_id');
            $table->unsignedBigInteger('ratee_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('quote_id')->nullable();

            // Multi-dimensional ratings (1-5 stars)
            $table->unsignedTinyInteger('reliability_rating')->nullable();
            $table->unsignedTinyInteger('response_speed_rating')->nullable();
            $table->unsignedTinyInteger('professionalism_rating')->nullable();
            $table->unsignedTinyInteger('fairness_rating')->nullable();
            $table->unsignedTinyInteger('settlement_rating')->nullable();

            // Aggregate rating
            $table->decimal('overall_rating', 3, 2)->nullable();

            // Text feedback
            $table->text('review_text')->nullable();
            $table->json('tags')->nullable();

            // Status tracking
            $table->enum('rating_status', ['pending', 'published', 'disputed', 'removed'])->default('pending');
            $table->text('dispute_reason')->nullable();
            $table->unsignedBigInteger('dispute_resolved_by')->nullable();

            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('published_at')->nullable();

            // Indices
            $table->index('rater_id');
            $table->index('ratee_id');
            $table->index('transaction_id');
            $table->index('quote_id');
            $table->index(['rating_status', 'published_at']);

            // Unique constraint - one rating per transaction per rater
            $table->unique(['transaction_id', 'rater_id'], 'unique_transaction_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_ratings');
    }
};
