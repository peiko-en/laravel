<?php

declare(strict_types=1);

namespace Telegram\Bot\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Telegram\Bot\Actions\AboutAction;
use Telegram\Bot\Actions\CasesAction;
use Telegram\Bot\Actions\CategoriesAction;
use Telegram\Bot\Actions\ContactAction;
use Telegram\Bot\Actions\FastRequestAction;
use Telegram\Bot\Actions\RequestsAction;

class MenuService
{
    private Collection $menu;

    public function __construct()
    {
        $this->menu = collect([
            [
                'name' => trans('bot::app.my-requests'),
                'icon' => '🌐',
                'handler' => RequestsAction::class
            ],
            [
                'name' => trans('bot::app.fast-request'),
                'icon' => '⚡️',
                'handler' => FastRequestAction::class
            ],
            [
                'name' => trans('bot::app.about-us'),
                'icon' => 'ℹ️',
                'handler' => AboutAction::class
            ],
            [
                'name' => trans('bot::app.our-cases'),
                'icon' => '💼',
                'handler' => CasesAction::class
            ],
            [
                'name' => trans('bot::app.domain-templates'),
                'icon' => '🎨',
                'handler' => CategoriesAction::class
            ],
            [
                'name' => trans('bot::app.contact-us'),
                'icon' => '📞',
                'handler' => ContactAction::class
            ]
        ]);
    }

    public function getCollection(): Collection
    {
        return $this->menu;
    }

    public function findMenuItem(string $actionName): ?array
    {
        return Arr::first(
            $this->getCollection(),
            fn (array $item) => str_contains($actionName, $item['name'])
        );
    }
}