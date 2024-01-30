<?php

declare(strict_types=1);

namespace Telegram\Bot\Events;

use App\Models\FastRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FastRequestEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public FastRequest $fastRequest)
    {
    }
}
