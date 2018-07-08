<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use App\placestwo;
class imagetwo extends Model {

    protected $connection = 'sqlite';
    protected $table = 'images';
    protected $fillable = [
      'pid',
      'user_id',
      'imageGetHash',
      'imageTitle',
      'imageRemoveHash',
      'imageLink',
      'isShowable',
      'relatedTo'

    ];

    protected $dates = [];

    public static $rules = [
        // Validation rules
    ];

    // Relationships
    public function placestwo()
    {

        return $this->belongsTo('App\placestwo','id');
    }

}
