<?php

namespace App\Jobs;

use App\Services\RatingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPendingRatings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RatingService $ratingService): void
    {
        Log::info('Background Job: Publishing pending ratings (48h+ old)...');
        
        $count = $ratingService->publishPendingRatings();
        
        if ($count > 0) {
            Log::info("Successfully published {$count} ratings in background.");
        }
    }
}
