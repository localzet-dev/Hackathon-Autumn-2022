<?php

namespace app\model;

use support\Model;

class Token extends Model 
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'Tokens';

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

    protected $fillable = ['user_id', 'time', 'access_token', 'refresh_token'];


    public function user() {
        $this->belongsTo(User::class);
    }
}
