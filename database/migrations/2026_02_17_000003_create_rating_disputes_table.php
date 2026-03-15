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
        Schema::create('rating_disputes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rating_id');
            $table->unsignedBigInteger('disputed_by');
            $table->string('dispute_reason', 500);
            $table->text('resolution_notes')->nullable();
            $table->enum('resolution_status', ['open', 'upheld', 'reversed'])->default('open');
            $table->unsignedBigInteger('resolved_by')->nullable();

            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            // Foreign keys
            $table->foreign('rating_id')->references('id')->on('user_ratings')->onDelete('cascade');
            $table->foreign('disputed_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');

            // Indices
            $table->index('rating_id');
            $table->index('disputed_by');
            $table->index('resolution_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_disputes');
    }
};
