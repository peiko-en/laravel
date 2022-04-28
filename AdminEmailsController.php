<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Account;
use App\Model\Emails;
use App\Model\UserAccount;
use App\User;
use Html;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Yajra\Datatables\Facades\Datatables;

class AdminEmailsController extends Controller
{
    public function index()
    {
        $this->addBreadcrumb('Адреса электронной почты');
        return view('admin-emails.index');
    }

    public function ajaxList()
    {
        $emails = Emails::query();

        return Datatables::of($emails)
            ->addColumn('control', function (Emails $email) {
                $control = [
                    Html::link(route('admin.emails.store', ['id' => $email->id]), '<i class="fa fa-wrench"></i>', ['class' => 'btn btn-info btn-sm'], null, false),
                    Html::link('#', '<i class="fa fa-trash"></i>', ['class' => 'btn btn-danger btn-sm remove-account', 'data-id' => $email->id], null, false),
                ];

                return implode(' ', $control);
            })->make(true);
    }

    public function remove(Request $request)
    {
        return ['is_deleted' => Emails::findOrFail((int)$request->get('id'))->delete()];
    }

    public function store($id = 0)
    {
        /** @var Emails $model */
        $model = Emails::findOrNew($id);
        $this->addBreadcrumb('Адреса электронной почты', route('admin.emails'));
        $this->addBreadcrumb(($model->id) ? $model->email : 'Добавить');

        return view('admin-emails.store', [
            'email' => $model,
        ]);
    }

    public function save(Request $request, $id = 0)
    {
        $this->storeValidator($request, $id);

        /** @var Emails $email */
        $email = Emails::findOrNew($id);
        $email->fill($request->all());
        $email->user_id = Auth::user()->id;
        $email->save();

        return redirect()->route('admin.emails')
            ->with('successMessage', 'E-mail успешно добавлен');
    }

    protected function storeValidator(Request $request, $id = 0)
    {
        $rules = [
            'email' => ['required', 'email', Rule::unique('emails')->ignore($id)],
            'name' => 'required|max:64',
        ];

        $this->validate($request, $rules, [], ['name' => 'Имя']);
    }
}
