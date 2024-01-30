<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use Illuminate\Support\Collection;
use Telegram\Bot\Models\BotContent;
use Telegram\Bot\Services\RegistrationService;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;

class StartAction extends AbstractAction
{
    private RegistrationService $registrationService;

    public function init(RegistrationService $registrationService): void
    {
        $this->registrationService = $registrationService;
    }

    public function handle(array $args = []): void
    {
        if (!$this->message->getFrom()) {
            $this->botApi->sendMessage($this->getChatId(), trans('bot::app.registration-error'));
            return;
        }

        $this->registrationService->register($this->message->getFrom());

        $this->botApi->sendMessage(
            $this->getChatId(),
            $this->fetchContent(BotContent::CONTENT_START),
            null,
            false,
            null,
            new ReplyKeyboardMarkup($this->createKeyboardItems(), resizeKeyboard: true)
        );
    }

    private function createKeyboardItems(): array
    {
        $keyboardItems = [];

        /** @var Collection $menuRow */
        foreach ($this->menuService->getCollection()->chunk(2) as $menuRow) {
            $keyboardItems[] = array_values(array_map(function(array $item) {
                $options = ['text' => $item['name'] . ' ' . $item['icon']];

                if (isset($item['web_app'])) {
                    $options['web_app'] = $item['web_app'];
                }

                return $options;
            }, $menuRow->all()));
        }

        return $keyboardItems;
    }
}