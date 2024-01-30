<?php

declare(strict_types=1);

namespace Telegram\Bot\Models;

use App\Models\User;

class TelegramUser extends User
{
    protected $table = 'users';
}