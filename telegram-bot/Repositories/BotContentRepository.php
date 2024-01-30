<?php

declare(strict_types=1);

namespace Telegram\Bot\Repositories;

use Telegram\Bot\Models\BotContent;

class BotContentRepository
{
    public function findByAlias(string $alias): ?BotContent
    {
        return BotContent::query()->firstWhere('alias', $alias);
    }
}