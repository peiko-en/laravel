<?php
/**
 * Created by PhpStorm.
 * User: Vitaliy
 * Date: 23.06.2017
 * Time: 13:26
 */

namespace App\Http\Controllers;

use App\Services\Paginator;
use App\Comments;
use App;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;

class CommentsController  extends Controller
{
    /**
     * Paginator Instance.
     *
     * @var Paginator
     */
    private $paginator;

    /**
     * Settings service instance.
     *
     * @var App\Services\Settings
     */
    private $settings;

    public $model;
    /**
     * Create new AlbumController instance.
     */
    public function __construct(Paginator $paginator, Comments $model)
    {
        $this->middleware('admin', ['only' => ['comments', 'switchVisible']]);

        $this->model = $model;
        $this->paginator     = $paginator;
        $this->settings      = App::make('Settings');
    }

    public function getComments($id, $mode)
    {
        $page = (int)Input::get('page');
        $limit = (int)Input::get('limit');

        $offset  = 0;
        if($page > 1) {
            $offset = ($page - 1) * $limit;
        }
        return Comments::where('artist_id', $id)
            ->where(['visible' => 1])
            ->take($limit)
            ->skip($offset)
            ->orderBy('id', 'DESC')->get();
    }

    public function comments()
    {
        $query = Comments::orderBy('id', 'DESC');
        return $this->paginator->paginate($query, Input::all(), 'comments');
    }

    public function switchVisible()
    {
        $comment = Comments::find((int)Input::get('id'));
        $message = trans('app.album-not-found');
        $status = 0;
        $value = 0;
        if($comment) {
            $comment->visible = ($comment->visible == 1) ? 0 : 1;
            if($comment->save()) {
                $status = 1;
                $message = ($comment->visible == 1) ? trans('app.comment-on') : trans('app.comment-off');
            } else {
                $message = trans('app.setting-unsaved');
            }

            $value = $comment->visible;
        }
        return response()->json(['status' => $status, 'message' => $message, 'value' => $value]);
    }

    public function save()
    {
        $comment = new Comments();
        $comment->author = Input::get('author');
        if(!$comment->author)
            $comment->author = trans('app.Anonymous');

        $comment->comment = Input::get('comment');
        $comment->artist_id = (int)Input::get('relativeId');

        $response = ['status' => 'success'];
        if($comment->comment)
        {
            $response['message'] = trans('app.commentSent');
            $comment->save();
        }
        else
        {
            $response = ['status' => 'error', 'message' => trans('app.commentRequired')];
        }

        return $response;

    }
}