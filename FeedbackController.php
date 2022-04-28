<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\FeedbackRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FeedbackController extends Controller
{
    public function index()
    {
        return view('feedback.index');
    }

    public function send(FeedbackRequest $request)
    {
        $request->sendMail();
        return redirect()->route('feedback')->with('successMessage', 'Успешно отправлено');
    }
}
