<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use App\Models\Cases;
use App\Repositories\CasesRepository;
use Illuminate\Support\Collection;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class CasesAction extends AbstractAction
{
    private CasesRepository $casesRepository;

    public function init(CasesRepository $casesRepository): void
    {
        $this->casesRepository = $casesRepository;
    }

    public function handle(array $args = []): void
    {
        $keyboardItems = [];

        /** @var Collection $caseChunk */
        foreach ($this->casesRepository->all()->chunk(2) as $caseChunk) {
            $keyboardItems[] = array_values(array_map(function(Cases $case) {
                return [
                    'text' => $case->name . ' ' . $case->telegram_emoji,
                    'callback_data' => json_encode(['action' => 'case', 'case_id' => $case->id])
                ];
            }, $caseChunk->all()));
        }

        if (isset($args['back_from_case'])) {
            $this->removePrevInlineKeyboard();
        }

        $this->botApi->sendMessage(
            $this->getChatId(),
            trans('bot::app.choose-case'),
            replyMarkup: new InlineKeyboardMarkup($keyboardItems)
        );
    }
}