<?php

declare(strict_types=1);

namespace Telegram\Bot\Services;

use App\Helpers\LogHelper;
use Illuminate\Support\Arr;
use Telegram\Bot\Actions\AbstractAction;
use Telegram\Bot\Actions\StartAction;
use Telegram\Bot\Exceptions\TelegramException;
use TelegramBot\Api\Client;
use TelegramBot\Api\Types\CallbackQuery;
use TelegramBot\Api\Types\Message;
use \TelegramBot\Api\Types\Update;

class WebhookService
{
    public function __construct(
        private Client $botClient,
        private MenuService $menuService,
        private LogHelper $logger
    ) {
    }

    public function handle(): void
    {
        $this->logger->bot($this->botClient->getRawBody());

        /** @var Message $updateMessage */
        $updateMessage = null;

        try {
            $this->botClient->on(function (Update $update) use (&$updateMessage) {
                $this->logger->bot($update);

                $updateMessage = $update->getMessage() ?: $update->getCallbackQuery()->getMessage();

                if ($actionOptions = $this->parseUpdate($update)) {
                    $this->handleAction($updateMessage, $actionOptions);
                }
            }, fn() => true);

            $this->botClient->run();
        } catch (TelegramException $e) {
            if ($updateMessage) {
                $this->botClient->sendMessage($updateMessage->getChat()->getId(), $e->getMessage());
            }
        } catch (\Throwable $e) {
            $this->logger->bot($e->getMessage() . '|' . $e->getTraceAsString());
            $this->botClient->sendMessage($updateMessage->getChat()->getId(), trans('bot::app.smth-wrong'));
        }
    }

    private function parseUpdate(Update $update): array
    {
        if ($update->getMessage()) {
            return $this->parseActionFromMessage($update->getMessage());
        }

        if ($update->getCallbackQuery()) {
            if ($update->getCallbackQuery()->getData() == AbstractAction::NO_ACTION) {
                $this->botClient->answerCallbackQuery(['callback_query_id' => $update->getCallbackQuery()->getId()]);
            } else {
                return $this->parseActionFromQueryCallback($update->getCallbackQuery());
            }
        }

        return [];
    }

    private function parseActionFromMessage(Message $message): array
    {
        if ($message->getText() == '/start') {
            return ['handler' => StartAction::class];
        }

        if ($menuItem = $this->menuService->findMenuItem($message->getText())) {
            return $menuItem;
        }

        //Probably message inside previous action. Try call previous action
        $actionData = cache()->get('previous_action' . $message->getFrom()->getId());

        if (json_validate($actionData)) {
            return [
                'handler' => json_decode($actionData, true)['handler'],
                'args' => ['typing' => true]
            ];
        }

        return [];
    }

    private function parseActionFromQueryCallback(CallbackQuery $callbackQuery): array
    {
        $callbackData = [];
        if (json_validate($callbackQuery->getData())) {
            $callbackData = json_decode($callbackQuery->getData(), true);
        }

        $args = Arr::except($callbackData, 'action');

        return [
            'handler' => AbstractAction::createActionClass($callbackData['action']),
            'callbackQuery' => $callbackQuery,
            'args' => $args
        ];
    }

    private function handleAction(Message $message, ?array $actionOptions): void
    {
        if ($actionOptions) {
            if (isset($actionOptions['callbackQuery'])) {
                /** @var CallbackQuery $callbackQuery */
                $callbackQuery = $actionOptions['callbackQuery'];
                $telegramUserId = $callbackQuery->getFrom()->getId();
            } else {
                $telegramUserId = $message->getFrom()->getId();
            }

            $this->saveAction($telegramUserId, [
                'handler' => $actionOptions['handler']
            ]);

            /** @var AbstractAction $action */
            $action = app($actionOptions['handler'], [
                'message' => $message,
                'callbackQuery' => $actionOptions['callbackQuery'] ?? null
            ]);
            $action->handle($actionOptions['args'] ?? []);
        } else {
            $this->botClient->sendMessage(
                $message->getChat()->getId(),
                trans('bot::app.unknown-action')
            );
        }
    }

    private function saveAction(int $telegramUserId, array $data): void
    {
        cache()->put('previous_action' . $telegramUserId, json_encode($data));
    }
}