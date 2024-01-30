<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use Telegram\Bot\Models\BotContent;

class ContactAction extends AbstractAction
{
    public function handle(array $args = []): void
    {
        $this->sendMessage($this->fetchContent(BotContent::CONTENT_CONTACT));
    }
}