<?php

namespace App\Modules\CommerceIntegration\Events;

use App\Modules\CommerceIntegration\Models\CommerceReservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommerceStockConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CommerceReservation $reservation)
    {
    }
}
