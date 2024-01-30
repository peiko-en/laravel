<?php

declare(strict_types=1);

namespace Telegram\Bot\Actions;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Support\Collection;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class CategoriesAction extends AbstractAction
{
    private CategoryRepository $categories;

    public function init(CategoryRepository $categories): void
    {
        $this->categories = $categories;
    }

    public function handle(array $args = []): void
    {
        $keyboardItems = [];

        /** @var Collection $menuRow */
        foreach ($this->categories->all()->chunk(2) as $categoryChunk) {
            $keyboardItems[] = array_values(array_map(function(Category $category) {
                return [
                    'text' => $category->name . ' ' . $category->telegram_emoji,
                    'callback_data' => json_encode(['action' => 'templates', 'category_id' => $category->id])
                ];
            }, $categoryChunk->all()));
        }

        if (isset($args['back_from_template'])) {
            $this->removePrevInlineKeyboard();
        }

        $this->botApi->sendMessage(
            $this->getChatId(),
            trans('bot::app.choose-domain'),
            replyMarkup: new InlineKeyboardMarkup($keyboardItems)
        );
    }
}