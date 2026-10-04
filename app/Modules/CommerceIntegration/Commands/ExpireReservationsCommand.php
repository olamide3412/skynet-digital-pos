<?php

namespace App\Modules\CommerceIntegration\Commands;

use App\Modules\CommerceIntegration\Services\InventoryReservationService;
use Illuminate\Console\Command;

class ExpireReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commerce:expire-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending commerce reservations that have exceeded their TTL';

    /**
     * Execute the console command.
     */
    public function handle(InventoryReservationService $reservationService): int
    {
        $this->info('Checking for expired commerce reservations...');

        $expired = $reservationService->expireOutdatedReservations();

        $this->info("Expired {$expired} outdated reservation(s).");

        return self::SUCCESS;
    }
}
