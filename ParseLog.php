<?php

namespace App\Model;

use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * App\Model\ParseLog
 *
 * @property int $id
 * @property int $account_id
 * @property int $user_id
 * @property int $shop_url_id
 * @property string $parsed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Query\Builder|\App\Model\ParseLog whereAccountId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\ParseLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\ParseLog whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\ParseLog whereParsedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\ParseLog whereShopUrlId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\ParseLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Model\ParseLog whereUserId($value)
 *
 * @property Account $account
 * @property User $user
 * @property ShopUrl $shopUrl
 * @mixin \Eloquent
 */
class ParseLog extends Model
{
    public static $botName = 'cron';
    public static $unguarded = true;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public static function prepareDataTable()
    {
        return static::join('accounts', 'parse_logs.account_id', '=', 'accounts.id')
            ->join('shop_urls', 'parse_logs.shop_url_id', '=', 'shop_urls.id')
            ->leftJoin('users', 'parse_logs.user_id', '=', 'users.id')
            ->select([
                'parse_logs.id', 'parse_logs.shop_url_id', 'parse_logs.user_id',
                'users.name AS user_name',
                'accounts.name AS account_name',
                'parse_logs.created_at',
                'shop_urls.url', 'shop_urls.shop_id'
            ]);
    }

    /**
     * @param Request $request
     * @return ParseLog|\Illuminate\Database\Query\Builder
     */
    public static function exportDataTable(Request $request)
    {
        $query = static::join('accounts', 'parse_logs.account_id', '=', 'accounts.id')
            ->join('shop_urls', 'parse_logs.shop_url_id', '=', 'shop_urls.id')
            ->leftJoin('users', 'parse_logs.user_id', '=', 'users.id')
            ->select([
                'parse_logs.id', 'parse_logs.shop_url_id',
                'users.name AS user_name',
                'accounts.name AS account_name',
                'parse_logs.created_at',
                'shop_urls.url'
            ]);

        if ($request->account) {
            $query->where('accounts.name', 'like', '%'.stripslashes($request->account).'%');
        }

        if ($request->user) {
            $query->where('users.name', 'like', '%'.stripslashes($request->user).'%');
        }

        if ($request->url) {
            $query->where('shop_urls.url', 'like', '%'.$request->url.'%');
        }

        if ($periods = parse_period_date($request->period)) {
            $query->where('parse_logs.created_at', '>=', $periods['from']);
            $query->where('parse_logs.created_at', '<=', $periods['to']);
        }

        return $query;
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function shopUrl()
    {
        return $this->belongsTo(ShopUrl::class, 'shop_url_id', 'id');
    }

    /**
     * @param ShopUrl $shopUrl
     * @param int $userId
     * @return static|Model
     */
    public static function add($shopUrl, $userId = 0)
    {
        $model = new static([
            'account_id' => $shopUrl->account_id,
            'user_id' => $userId,
            'shop_url_id' => $shopUrl->id,
            'created_at' => Carbon::now(),
        ]);

        $model->save();

        return $model;
    }
}
