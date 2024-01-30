<?php

declare(strict_types=1);

namespace Telegram\Bot\Controllers;

use App\Http\Controllers\Controller;
use Telegram\Bot\Services\WebhookService;

class WebhookController extends Controller
{
    public function index(WebhookService $webhookService): void
    {
        $webhookService->handle();
    }
}