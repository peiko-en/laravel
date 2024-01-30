<?php

declare(strict_types=1);

namespace Telegram\Bot\Providers;

use App\Repositories\UserRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Events\FastRequestEvent;
use Telegram\Bot\Listeners\FastRequestListener;
use Telegram\Bot\Models\TelegramUser;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Client;

class TelegramBotServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Client::class, function() {
            return new Client(config('telegram.bot_token'));
        });

        $this->app->singleton(BotApi::class, function() {
            return new BotApi(config('telegram.bot_token'));
        });

        $this->app->singleton(TelegramUser::class, function (Application $app) {
            if (request()->exists('callback_query')) {
                $telegramUserId = (int) request()->input('callback_query.from.id');
                $telegramUsername = request()->input('callback_query.from.username');
            } else {
                $telegramUserId = (int) request()->input('message.from.id');
                $telegramUsername = request()->input('message.from.username');
            }

            $user = new TelegramUser();
            if ($telegramUserId && $telegramUsername) {
                $user = $app->make(UserRepository::class)->findByTelegramIdAndUsername($telegramUserId, $telegramUsername);
            }

            return $user;
        });

        $this->app->singleton(
            FastRequestListener::class,
            fn(Application $app) => new FastRequestListener(
                $app->make(BotApi::class),
                (int) config('telegram.bot_admin_chat_id')
            )
        );
    }

    public function boot()
    {
        Event::listen(FastRequestEvent::class, FastRequestListener::class);

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->mergeConfigFrom(__DIR__ . '/../config/telegram.php', 'telegram');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'bot');
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
    }
}