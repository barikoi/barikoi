<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class GPX extends Model {

    protected $fillable = ['lon','lat','user_id'];
    protected $table='gpx';
    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo('App\User');
    }

}
