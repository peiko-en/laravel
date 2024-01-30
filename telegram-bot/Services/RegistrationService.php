<?php

declare(strict_types=1);

namespace Telegram\Bot\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use TelegramBot\Api\Types\User as TelegramUser;

class RegistrationService
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function register(TelegramUser $telegramUser): User|Model
    {
        $user = $this->userRepository->findByTelegramId($telegramUser->getId());

        if ($user) {
            return $user;
        }

        $user = new User();
        $user->telegram_id = $telegramUser->getId();
        $user->name = $telegramUser->getFirstName() . ' ' . $telegramUser->getLastName();
        $user->email = $telegramUser->getUsername() . '_' . $telegramUser->getId() . '@telegram.com';
        $user->email_verified_at = now();
        $user->username = $telegramUser->getUsername();
        $user->setPassword(Str::random());
        $user->save();

        return $user;
    }
}