<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateDailySalesSummary implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $amountCents,
        public ?Carbon $placedAt = null,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $date = ($this->placedAt ?? now())->toDateString();
        $cacheKey = "sales:summary:{$date}";

        Cache::increment($cacheKey, $this->amountCents);

        Log::info("Updated daily sales summary for {$date} by +{$this->amountCents} cents.");
    }
}
