<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use App\image;
class placestwo extends Model {


    protected $table = 'places_3';
    protected $fillable = [
      'longitude',
      'latitude',
      'Address',
      'flag',
      'device_ID',
      'uCode',
      'location',
      'zone',
      'ward',
      'cc_code',
      'road_details',

    ];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships
    public function image()
    {
        return $this->hasMany('App\Image','pid');
    }

}
