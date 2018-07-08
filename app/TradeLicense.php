<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class TradeLicense extends Model {

    protected $table = 'tradelicense';
    protected $fillable = [
      'owner_name',
      'business_type',
      'trade_license_number',
      'trade_license_fee',
      'trade_license_issue_date',
      'trade_license_renewal_date',
      'signboard_tax',
      'signboard_size',
      'number_of_signboard',
      'pid',
      'created_at',
      'updated_at',
    ];


    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships

}
