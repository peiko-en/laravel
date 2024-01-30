<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use App\Repositories\TemplateRepository;
use Telegram\Bot\Exceptions\TelegramException;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class TemplatesAction extends AbstractAction
{
    private TemplateRepository $templates;

    public function init(TemplateRepository $templates): void
    {
        $this->templates = $templates;
    }

    public function handle(array $args = []): void
    {
        $categoryId = $args['category_id'] ?? 0;
        $template = $this->templates->findByCategoryId($categoryId);

        if (!$template) {
            throw new TelegramException(trans('bot::app.template-not-found'));
        }

        $this->removePrevInlineKeyboard();

        $keyboard = new InlineKeyboardMarkup([
            [
                [
                    'text' => trans('bot::app.test-template'),
                    'web_app' => [
                        'url' => config('app.web_app_url') . '?' . http_build_query([
                                'categoryId' => $template->category_id, 'templateId' => $template->id
                            ])
                    ],
                ],
            ],
            [
                [
                    'text' => trans('bot::app.back'),
                    'callback_data' => json_encode(['action' => 'categories', 'back_from_template' => true]),
                ],
                [
                    'text' => trans('bot::app.learn-more'),
                    'url' => route('front.template', ['template' => $template->alias])
                ]
            ],
        ]);

        if ($template->getFirstMedia()) {
            $this->botApi->sendPhoto(
                $this->getChatId(),
                $template->getFirstMedia()->getUrl(),
                $template->getContent(),
                replyMarkup: $keyboard
            );
        } else {
            $this->botApi->sendMessage(
                $this->getChatId(),
                $template->getContent(),
                replyMarkup: $keyboard
            );
        }
    }
}