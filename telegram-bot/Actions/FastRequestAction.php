<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use App\Enums\Status;
use App\Models\FastRequest;
use Illuminate\Database\Eloquent\Model;
use Telegram\Bot\Events\FastRequestEvent;
use Telegram\Bot\Models\BotContent;
use Telegram\Bot\Models\TelegramUser;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class FastRequestAction extends AbstractAction
{
    private TelegramUser $user;

    public function init(TelegramUser $user): void
    {
        $this->user = $user;
    }

    public function handle(array $args = []): void
    {
        $isButton = isset($args['button']);
        $isTypingMessage = isset($args['typing']);

        if ($isButton || $isTypingMessage) {
            $this->handleFastRequest($isButton, $isTypingMessage);
        } else {
            $this->mainResponse();
        }
    }

    private function mainResponse(): void
    {
        $this->botApi->sendMessage(
            $this->getChatId(),
            $this->fetchContent(BotContent::CONTENT_FAST_REQUEST),
            replyMarkup: new InlineKeyboardMarkup([
                [
                    [
                        'text' => trans('bot::app.send-empty-request'),
                        'callback_data' => json_encode(['action' => 'fast-request', 'button' => 'empty-request'])
                    ]
                ]
            ])
        );
    }

    private function handleFastRequest(bool $withButtonRemoval = false, bool $isTypingMessage = false): void
    {
        $fastRequest = $this->saveFastRequest($isTypingMessage ? $this->message->getText() : null);

        FastRequestEvent::dispatch($fastRequest);

        if ($withButtonRemoval) {
            $this->removePrevInlineKeyboard();
        }

        $this->sendMessage($this->fetchContent(BotContent::CONTENT_EMPTY_REQUEST));
    }

    private function saveFastRequest(?string $comment = null): FastRequest|Model
    {
        return FastRequest::query()->create([
            'telegram_id' => $this->user->telegram_id,
            'username' => $this->user->username,
            'name' => $this->user->name,
            'comment' => e($comment),
            'status' => Status::PENDING->value
        ]);
    }
}