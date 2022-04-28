<?php

namespace App\Http\Requests;

use App\Model\Emails;
use App\Services\MailService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Class FeedbackRequest
 *
 * @property string $message
 * @property string $phone
 * @property string $name
 * @property string $email
 * @package App\Http\Requests
 */
class FeedbackRequest extends FormRequest
{
    /**
     * @var MailService
     */
    private $mail;

    public function __construct(MailService $mail)
    {
        parent::__construct();
        $this->mail = $mail;
    }
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'message' => 'required|max:512',
            'name' => 'required|max:30',
            'phone' => 'required|max:20',
            'email' => 'required|email|max:64',
        ];
    }

    public function attributes()
    {
        return [
            'message' => trans('app.message'),
            'name' => trans('app.name'),
            'phone' => trans('app.phone'),
            'email' => trans('app.account.email'),
        ];
    }

    public function sendMail()
    {
        $user = Auth::user();
        $subject = 'Обратная связь '.$user->account->name.' '.$user->name;
        $message = 'E-mail: '.$this->email
            .'<br>Имя: '.$this->name
            .'<br>Имя личного кабинета: '.$user->account->name
            .'<br>Телефон: ' .$this->phone
            .'<br>Сообщение: '.$this->message;

        try {
            Emails::send($this->mail, $subject, $message);
        } catch (\Exception $e) {
            logger($e->getMessage().', '.$e->getFile().', '.$e->getLine()."\n");
        }
    }
}
