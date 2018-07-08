<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class NewPlace extends Model {

    protected $fillable = [];
    
    protected $table = 'places_3';
    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];


    // Relationships
    public function images()
    {
        return $this->hasMany('App\Image','pid');
    }
}
