<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use App\Repositories\CasesRepository;
use Telegram\Bot\Exceptions\TelegramException;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia;
use TelegramBot\Api\Types\InputMedia\InputMediaPhoto;

class CaseAction extends AbstractAction
{
    private CasesRepository $casesRepository;

    public function init(CasesRepository $casesRepository): void
    {
        $this->casesRepository = $casesRepository;
    }

    public function handle(array $args = []): void
    {
        $case = $this->casesRepository->findById($args['case_id'] ?? 0);

        if (!$case) {
            throw new TelegramException(trans('bot::app.case-not-found'));
        }

        $this->removePrevInlineKeyboard();

        $keyboard = new InlineKeyboardMarkup([
            [
                [
                    'text' => trans('bot::app.back'),
                    'callback_data' => json_encode(['action' => 'cases', 'back_from_case' => true]),
                ],
                [
                    'text' => trans('bot::app.learn-more'),
                    'url' => route('front.case', ['case' => $case->alias])
                ]
            ],
        ]);

        $mediaInputs = new ArrayOfInputMedia();

        foreach ($case->media as $media) {
            $mediaInputs->addItem(
                new InputMediaPhoto($media->getUrl())
            );
        }

        if ($mediaInputs->count() > 0) {
            $this->botApi->sendMediaGroup($this->getChatId(), $mediaInputs);
        }

        $this->botApi->sendMessage($this->getChatId(), $case->getContentDetails(), replyMarkup: $keyboard);
    }
}