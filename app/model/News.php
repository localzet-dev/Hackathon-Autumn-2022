<?php

namespace app\model;

use support\Model;

class News extends Model 
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'News';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = ['user_id', 'image', 'body'];


    public function user() {
        return $this->belongsTo(User::class);
    }
}
