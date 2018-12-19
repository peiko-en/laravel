<?php namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Comments
 *
 * @property integer $artist_id
 * @property string $author
 * @property string $comment
 * @property string $created_at datetime
 * @property string $updated_at datetime
 * @property boolean $visible
 */
class Comments extends Model
{
    //public $timestamps = false;
    protected $table = 'comments';

    public function getCreatedAtAttribute($value)
    {
        return strtotime($value) * 1000;
    }
}
