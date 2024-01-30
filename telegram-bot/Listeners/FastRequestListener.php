<?php

declare(strict_types=1);

namespace Telegram\Bot\Listeners;

use Telegram\Bot\Events\FastRequestEvent;
use TelegramBot\Api\BotApi;

class FastRequestListener
{
    public function __construct(private BotApi $botApi, private int $chatId)
    {
    }

    public function handle(FastRequestEvent $event): void
    {
        $this->botApi->sendMessage(
            $this->chatId,
            $event->fastRequest->getTelegramFormat()
        );
    }
}
