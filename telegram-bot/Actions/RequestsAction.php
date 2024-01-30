<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use App\Models\WebappRequest;
use App\Repositories\WebappRequestRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Telegram\Bot\Models\TelegramUser;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class RequestsAction extends AbstractAction
{
    private TelegramUser $user;
    private WebappRequestRepository $webappRequests;

    public function init(TelegramUser $user, WebappRequestRepository $webappRequests): void
    {
        $this->user = $user;
        $this->webappRequests = $webappRequests;
    }

    public function handle(array $args = []): void
    {
        $page = $args['page'] ?? 1;
        $userRequests = $this->webappRequests->pagination($this->user->id, $page);

        $message = $this->createMessage($userRequests);

        if (!$message) {
            $this->botApi->sendMessage(
                $this->getChatId(),
                trans('bot::app.no-requests'),
                replyMarkup: $this->createKeyboard($userRequests)
            );
            return;
        }

        if (isset($args['page'])) {
            $this->botApi->editMessageText(
                $this->getChatId(),
                $this->getMessageId(), $message,
                replyMarkup: $this->createKeyboard($userRequests));
        } else {
            $this->botApi->sendMessage(
                $this->getChatId(),
                $message,
                replyMarkup: $this->createKeyboard($userRequests));
        }
    }

    /**
     * @param WebappRequest[] $userRequests
     */
    private function createMessage(LengthAwarePaginator $userRequests): ?string
    {
        /** @var ?WebappRequest $userRequest */
        $userRequest = $userRequests->first();

        return $userRequest?->getTelegramFormat();
    }

    private function createKeyboard(LengthAwarePaginator $userRequests): InlineKeyboardMarkup
    {
        $buttons = [];

        if ($userRequests->total() > 1) {
            $nextPage = $userRequests->currentPage() + 1;
            if ($nextPage > $userRequests->lastPage()) {
                $nextPage = 1;
            }

            $prevPage = $userRequests->currentPage() - 1;
            if ($prevPage < 1) {
                $prevPage = $userRequests->lastPage();
            }

            $buttons[] = [
                [
                    'text' => '<',
                    'callback_data' => json_encode(['action' => 'requests', 'page' => $prevPage])
                ],
                [
                    'text' => $userRequests->currentPage() . '/' . $userRequests->lastPage(),
                    'callback_data' => self::NO_ACTION
                ],
                [
                    'text' => '>',
                    'callback_data' => json_encode(['action' => 'requests', 'page' => $nextPage])
                ],
            ];
        }

        $buttons[] = [
            ['text' => trans('bot::app.new-request'), 'web_app' => ['url' => config('app.web_app_url')]],
        ];

        return new InlineKeyboardMarkup($buttons);
    }
}