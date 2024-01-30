<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use App\Helpers\LogHelper;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Telegram\Bot\Repositories\BotContentRepository;
use Telegram\Bot\Services\MenuService;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Client;
use TelegramBot\Api\Types\CallbackQuery;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\Message;

abstract class AbstractAction
{
    public const string NO_ACTION = 'no_action';

    public function __construct(
        protected Client $botClient,
        protected BotApi $botApi,
        protected LogHelper $logger,
        protected MenuService $menuService,
        protected BotContentRepository $contentRepository,
        protected Message $message,
        protected ?CallbackQuery $callbackQuery = null
    ) {
        if (method_exists($this, 'init')) {
            App::call([$this, 'init']);
        }
    }

    public static function createActionClass(string $actionName): string
    {
        return __NAMESPACE__ . '\\' . ucfirst(Str::camel($actionName)) . 'Action';
    }

    abstract public function handle(array $args = []): void;

    protected function sendMessage($content): void
    {
        $this->botApi->sendMessage($this->getChatId(), $content);
    }

    protected function getChatId(): string|float|int
    {
        return $this->message->getChat()->getId();
    }

    protected function getMessageId(): float|int
    {
        return $this->message->getMessageId();
    }

    protected function fetchContent(string $alias): string
    {
        $content = $this->contentRepository->findByAlias($alias);

        return $content?->content ?: trans('bot::app.no-content');
    }

    protected function removePrevInlineKeyboard(): void
    {
        $this->botApi->editMessageReplyMarkup(
            $this->getChatId(),
            $this->getMessageId(),
            replyMarkup: new InlineKeyboardMarkup()
        );
    }
}