<?php

declare(strict_types=1);

namespace Telegram\Bot\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $alias
 * @property string $name
 * @property string|null $content
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class BotContent extends Model
{
    public const string CONTENT_ABOUT = 'about';
    public const string CONTENT_START = 'start';
    public const string CONTENT_CONTACT = 'contact';
    public const string CONTENT_FAST_REQUEST = 'fast-request';
    public const string CONTENT_EMPTY_REQUEST = 'empty-request';
}
