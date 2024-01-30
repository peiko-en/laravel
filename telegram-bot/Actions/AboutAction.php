<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use Telegram\Bot\Models\BotContent;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class AboutAction extends AbstractAction
{
    public function handle(array $args = []): void
    {
        $this->botApi->sendMessage(
            $this->getChatId(),
            $this->fetchContent(BotContent::CONTENT_ABOUT),
            replyMarkup: new InlineKeyboardMarkup([
                [
                    ['text' => trans('bot::app.homepage'), 'url' => config('app.landing_url')]
                ]
            ])
        );
    }
}