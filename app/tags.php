<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class tags extends Model {

    protected $fillable = ['tags'];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships

}
