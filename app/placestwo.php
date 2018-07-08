<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use App\imagetwo;
class placestwo extends Model {

    protected $connection = 'sqlite';
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
    public function imagetwo()
    {
        return $this->hasMany('App\imagetwo','pid');
    }

}
