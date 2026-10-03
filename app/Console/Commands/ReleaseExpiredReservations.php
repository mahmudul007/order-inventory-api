<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:release-expired')]
#[Description('Command description')]
class ReleaseExpiredReservations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
