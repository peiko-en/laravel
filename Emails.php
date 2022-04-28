<?php

namespace App\Model;

use App\Services\MailService;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Model\Emails
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $name
 * @method static \Illuminate\Database\Query\Builder|\App\Model\Emails whereEmail($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\Emails whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\Emails whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\Emails whereName($value)
 * @mixin \Eloquent
 */
class Emails extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'name',
    ];

    public static function send(MailService $emailService, $subject, $message)
    {
        foreach (static::all() as $email) {
            $emailService->setTo($email->email, $email->name);
        }

        $emailService->setSubject($subject)
            ->setBody($message)
            ->send();
    }
}
