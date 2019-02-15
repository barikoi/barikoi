<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    //assign the table
    protected $table = "tokens";
    protected $fillable =[
      'user_id',
      'api_key',
      'get_count',
      //caps for apis , caps = rate limit factor
      'autocomplete_cap',
      'geo_code_cap',
      'reverse_geo_code_cap',
      //count for apis
      'autocomplete_count',
      'geo_code_count',
      'reverse_geo_code_count',
      'nearby_count',
      'distance_count'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
