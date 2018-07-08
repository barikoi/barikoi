<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;
use Auth;
use App\User;
use App\Place;
use App\NewPlace;
use App\SavedPlace;
use App\Referral;
use App\analytics;
use App\Image;
use App\placestwo;
use App\imagetwo;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Carbon\Carbon;
use TeamTNT\TNTSearch\TNTSearch;
use TeamTNT\TNTSearch\Classifier\TNTClassifier;
use TeamTNT\TNTSearch\TNTGeoSearch;

class SearchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request){
       $terms=Input::get('query');
       $q = Input::get('query');
       //$srch=$request->query;
       //$q=$request->query;
       //NATURAL LANGUAGE MODE
       //BOOLEAN MODE
       if(Place::where('uCode','=',$terms)->exists()){
         $posts=Place::with('images')->where('uCode','=',$terms)->get();
       }
       else{
         //$area = DB::table('places')
           //->where('area', 'LIKE', '%'.$q.'%');
         $posts = Place::with('images')->where('flag','=',1)
         ->where('address', 'SOUNDS LIKE', '%'.$q.'%')
         ->where('address', 'REGEXP', '^'.$q.'$')
         ->limit(20)
         ->get(['id','longitude','latitude','Address','area','city','postCode','uCode','pType','subType']);
         /*$posts = Place::with('images')->where('flag','=',1)
         ->where("MATCH(Address,area) AGAINST ('.*$q*.' IN BOOLEAN MODE)")
         ->limit(20)
         ->get();*/
          /* $posts=DB::select("SELECT id,longitude,latitude,Address,area,city,postCode,uCode, pType, subType FROM
                     places
                     WHERE
                     MATCH (Address, area)
                     AGAINST ('.$request->search*' IN BOOLEAN MODE)
                     LIMIT 10");
         }else {
           $posts = 'Did not get anything like that ';
         }*/

       }



       DB::table('analytics')->increment('search_count',1);
       //https://hooks.slack.com/services/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
       //https://hooks.slack.com/services/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
       define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
  /*   if (isset($_SERVER['HTTP_CLIENT_IP']))
         $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
     else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
         $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
     else if(isset($_SERVER['HTTP_X_FORWARDED']))
         $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
     else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
         $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
     else if(isset($_SERVER['HTTP_FORWARDED']))
         $ipaddress = $_SERVER['HTTP_FORWARDED'];
     else if(isset($_SERVER['REMOTE_ADDR']))
         $ipaddress = $_SERVER['REMOTE_ADDR'];
     else
         $ipaddress = 'UNKNOWN';
     $clientDevice = gethostbyaddr($ipaddress);*/
    $clientDevice = 'x';
   // Make your message

   $message = array('payload' => json_encode(array('text' => "Someone searched for: '".$terms. "' , ip:".$clientDevice)));


   // Use curl to send your message
     $c = curl_init(SLACK_WEBHOOK);
     curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
     curl_setopt($c, CURLOPT_POST, true);
     curl_setopt($c, CURLOPT_POSTFIELDS, $message);
     curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
     $res = curl_exec($c);
     curl_close($c);

  /*   $file=Storage::disk('search')->get('search_log.json');
     $data = json_decode($file,true);
     unset($file);
     //you need to add new data as next index of data.
     $data[] =array(
         'dateTime'=> date('Y-m-d H:i:s'),
         'terms' => $terms,
         'url' => $request->url(),
         'from_IP' =>$clientDevice
         );
     $result1=json_encode($data,JSON_PRETTY_PRINT);
     //file_put_contents('search_log.json', $result);
     Storage::disk('search')->put('search_log.json', $result1);
     unset($result1);
     $log_save="ok";
*/
     return $posts;
   }



    public function findNearby(Request $request){
        $terms=Input::get('query');
        if($request->has('longitude'))
        {
          $lon=Input::get('longitude');
        }
        if($request->has('latitude')){
          $lat=Input::get('latitude');
        }

        $q = Input::get('query');
        //$srch=$request->query;
        //$q=$request->query;
        //NATURAL LANGUAGE MODE
        //BOOLEAN MODE
       // Place::where('uCode','like','%'.$terms.'%')->exists()
        if(Place::where('uCode','=',$terms)->exists())
        {
          $posts=Place::with('images')->where('uCode','=',$terms)->get();
        }
        else{
          $posts = Place::with(array('images' => function($query)
          {
            $query->select('pid','imageLink');}))->where('flag','=',1)
                  ->whereRaw("MATCH(Address,uCode,pType,subType) AGAINST(? IN BOOLEAN MODE)",array($q))
                  ->select(DB::raw('*, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
                  ->having('distance','<=',2)
                  ->orderBy('distance')
                  ->limit(2)
                  ->get();
          }


          if(count($posts)==0){
            $ar[]=array("text"=>"my apologies,Could not find '".$terms."'");
              return new JsonResponse([
                  'messages'=>$ar
              ]);
          }
          else{
            foreach ($posts as $post) {
              $ad=$post->Address;
              $sub=$post->area.','.$post->city;
              $code=$post->uCode;
              $weblink="https://barikoi.com/#/code/".$code;

              //echo count($post->images);

              if(count($post->images)==0){
                $img='';
                // $posts1[]=array('title'=>$ad,'image_url'=>NULL,'subtitle'=>$sub,'buttons'=>array([
                //     'type'=>'web_url','url'=>$weblink,'title'=>$code]));
              }else{
                foreach ($post->images as $p) {
                  $img=$p->imageLink;}
              }
              $posts1[]=array('title'=>$ad,'image_url'=>$img,'subtitle'=>$sub,'buttons'=>array([
                    'type'=>'web_url','url'=>$weblink,'title'=>$code]));
             }

              $messages[]=array('attachment'=>[
                        'type'=>'template','payload'=>
                                [
                                    'template_type'=>'generic',
                                    'elements' =>$posts1
                                ]
                            ]
                        );
        // $ar[]=array("text"=>"Searched for:".$terms." Lon:".$longitude." Lat:".$latitude);
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
      $message = array('payload' => json_encode(array('text' => "Someone searched nearby for: '".$terms. "' ,from BOT")));
    // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);
             return new JsonResponse([
                  'messages'=>$messages,
              ]);
        }
    }
    //this function is used by the bot to search all
      public function findAll(Request $request){
        $terms=Input::get('query');

        $q = Input::get('query');
        //$srch=$request->query;
        //$q=$request->query;
        //NATURAL LANGUAGE MODE
        //BOOLEAN MODE
       // Place::where('uCode','like','%'.$terms.'%')->exists()
        if(Place::where('uCode','=',$terms)->exists())
        {
          $posts=Place::with('images')->where('uCode','=',$terms)->get();
        }
        else{
          $posts = Place::with(array('images' => function($query)
          {
            $query->select('pid','imageLink');}))->where('flag',1)
                  ->whereRaw("MATCH(Address,area) AGAINST(? IN BOOLEAN MODE)",array($q))
                  ->limit(4)
                  ->get();
          }


          if(count($posts)==0){
            $ar[]=array("text"=>"my apologies,could not find anything related to' ".$terms." ' nearby");
              return new JsonResponse([
                  'messages'=>$ar
              ]);
          }
          else{
            foreach ($posts as $post) {
              $ad=$post->Address;
              $sub=$post->area.','.$post->city;
              $code=$post->uCode;
              $weblink="https://barikoi.com/#/code/".$code;

              //echo count($post->images);

              if(count($post->images)==0){
                $img='';
                // $posts1[]=array('title'=>$ad,'image_url'=>NULL,'subtitle'=>$sub,'buttons'=>array([
                //     'type'=>'web_url','url'=>$weblink,'title'=>$code]));
              }else{
                foreach ($post->images as $p) {
                  $img=$p->imageLink;}
              }
              $posts1[]=array('title'=>$ad,'image_url'=>$img,'subtitle'=>$sub,'buttons'=>array([
                    'type'=>'web_url','url'=>$weblink,'title'=>$code]));
             }

              $messages[]=array('attachment'=>[
                        'type'=>'template','payload'=>
                                [
                                    'template_type'=>'generic',
                                    'elements' =>$posts1
                                ]
                            ]
                        );
        // $ar[]=array("text"=>"Searched for:".$terms." Lon:".$longitude." Lat:".$latitude);
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
        $message = array('payload' => json_encode(array('text' => "Someone searched for: '".$terms. "' ,from BOT")));
        // Use curl to send your message
          $c = curl_init(SLACK_WEBHOOK);
          curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($c, CURLOPT_POST, true);
          curl_setopt($c, CURLOPT_POSTFIELDS, $message);
          curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
          $res = curl_exec($c);
          curl_close($c);
                 return new JsonResponse([
                      'messages'=>$messages,
                  ]);
        }
      }

    public function indexCode(Request $request){
        $q = Input::get('query');
        //$q=$request->query;
        //NATURAL LANGUAGE MODE
        //BOOLEAN MODE
        $posts = Place::whereRaw(
            "MATCH(Address,uCode) AGAINST(? IN NATURAL LANGUAGE MODE)",
            array($q)
        )->get();

        //return View::make('posts.index', compact('posts'));
        //$results = $query->get();
            //Save the log to a .json file

        $file = file_get_contents('search_log.json', true);
        $data = json_decode($file,true);
        unset($file);

        //you need to add new data as next index of data.
        $data[] =array(
            'dateTime'=> date('Y-m-d H:i:s'),
            'terms' => $terms,
            'url' => $request->url(),
            'from_IP' =>$clientDevice
            );
        $result=json_encode($data,JSON_PRETTY_PRINT);
        file_put_contents('search_log.json', $result);
        unset($result);
        $log_save="ok";

        return $posts;
    }


    //Food
    public function food(Request $request){
      $terms=Input::get('query');
      if($request->has('longitude'))
      {
        $lon=Input::get('longitude');
      }
      if($request->has('latitude')){
        $lat=Input::get('latitude');
      }

      if($request->has('within')){
        $distance=Input::get('within');
      }else{
        $distance=10;
      }


      $q = 'Food';
      //$srch=$request->query;
      //$q=$request->query;
      //NATURAL LANGUAGE MODE
      //BOOLEAN MODE
     // Place::where('uCode','like','%'.$terms.'%')->exists()
      if(Place::where('uCode','=',$terms)->exists())
      {
        $posts=Place::with('images')->where('uCode','=',$terms)->get();
      }
      else{
        $posts = Place::with(array('images' => function($query)
        {
          $query->select('pid','imageLink');}))->where('flag','=',1)->where('pType','=','Food')
                // ->whereRaw("MATCH(Address,uCode,pType,subType) AGAINST(? IN BOOLEAN MODE)",array($q))
                ->select(DB::raw('*, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
                ->having('distance','<=',$distance)
                ->orderBy('distance')
                ->limit(5)
                ->get();
        }
        //Reply to Bot
        if(count($posts)==0){
          $ar[]=array("text"=>"My apologies,could not find anything related to your search.");
            return new JsonResponse([
                'messages'=>$ar
            ]);
        }
        else{
          foreach ($posts as $post) {
            $ad=$post->Address;
            $sub=$post->area.','.$post->city;
            $code=$post->uCode;
            $weblink="https://barikoi.com/#/code/".$code;
            //echo count($post->images);
            if(count($post->images)==0){
              $img='';
              // $posts1[]=array('title'=>$ad,'image_url'=>NULL,'subtitle'=>$sub,'buttons'=>array([
              //     'type'=>'web_url','url'=>$weblink,'title'=>$code]));
            }else{
              foreach ($post->images as $p) {
                $img=$p->imageLink;}
            }
            $posts1[]=array('title'=>$ad,'image_url'=>$img,'subtitle'=>$sub,'buttons'=>array([
                  'type'=>'web_url','url'=>$weblink,'title'=>$code]));
           }

            $messages[]=array('attachment'=>[
                      'type'=>'template','payload'=>
                              [
                                  'template_type'=>'generic',
                                  'elements' =>$posts1
                              ]
                          ]
                      );
      // $ar[]=array("text"=>"Searched for:".$terms." Lon:".$longitude." Lat:".$latitude);
            define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
                  $message = array('payload' => json_encode(array('text' => "Someone searched nearby for: '".$q. "' ,from BOT")));
          // Use curl to send your message
            $c = curl_init(SLACK_WEBHOOK);
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_POSTFIELDS, $message);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
            $res = curl_exec($c);
            curl_close($c);
         return new JsonResponse([
              'messages'=>$messages,
          ]);
        }
    }
    //Travel
    public function travel(Request $request){
      $terms=Input::get('query');
      if($request->has('longitude'))
      {
        $lon=Input::get('longitude');
      }
      if($request->has('latitude')){
        $lat=Input::get('latitude');
      }

      if($request->has('within')){
        $distance=Input::get('within');
      }else{
        $distance=10;
      }


      $q = 'Tourist Spot';
      //$srch=$request->query;
      //$q=$request->query;
      //NATURAL LANGUAGE MODE
      //BOOLEAN MODE
     // Place::where('uCode','like','%'.$terms.'%')->exists()
      if(Place::where('uCode','=',$terms)->exists())
      {
        $posts=Place::with('images')->where('uCode','=',$terms)->get();
      }
      else{
        $posts = Place::with(array('images' => function($query)
        {
          $query->select('pid','imageLink');}))->where('flag','=',1)->where('pType','=','Tourism')
                // ->whereRaw("MATCH(Address,uCode,pType,subType) AGAINST(? IN BOOLEAN MODE)",array($q))
                ->select(DB::raw('*, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
                ->having('distance','<=',$distance)
                ->orderBy('distance')
                ->limit(5)
                ->get();
        }
        //Reply to Bot
        if(count($posts)==0){
          $ar[]=array("text"=>"My apologies,could not find anything related to your search.");
            return new JsonResponse([
                'messages'=>$ar
            ]);
        }
        else{
          foreach ($posts as $post) {
            $ad=$post->Address;
            $sub=$post->area.','.$post->city;
            $code=$post->uCode;
            $weblink="https://barikoi.com/#/code/".$code;
            //echo count($post->images);
            if(count($post->images)==0){
              $img='';
              // $posts1[]=array('title'=>$ad,'image_url'=>NULL,'subtitle'=>$sub,'buttons'=>array([
              //     'type'=>'web_url','url'=>$weblink,'title'=>$code]));
            }else{
              foreach ($post->images as $p) {
                $img=$p->imageLink;}
            }
            $posts1[]=array('title'=>$ad,'image_url'=>$img,'subtitle'=>$sub,'buttons'=>array([
                  'type'=>'web_url','url'=>$weblink,'title'=>$code]));
           }

            $messages[]=array('attachment'=>[
                      'type'=>'template','payload'=>
                              [
                                  'template_type'=>'generic',
                                  'elements' =>$posts1
                              ]
                          ]
                      );
      // $ar[]=array("text"=>"Searched for:".$terms." Lon:".$longitude." Lat:".$latitude);
           define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
                  $message = array('payload' => json_encode(array('text' => "Someone searched nearby for: '".$q. "' ,from BOT")));
          // Use curl to send your message
            $c = curl_init(SLACK_WEBHOOK);
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($c, CURLOPT_POST, true);
            curl_setopt($c, CURLOPT_POSTFIELDS, $message);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
            $res = curl_exec($c);
            curl_close($c);

          //  $var ="Someone searched nearby for: '".$q. "' ,from BOT";
            //$this->slack($var);
         return new JsonResponse([
              'messages'=>$messages,
          ]);
        }
    }


    public function searchLog(){
      //$file = file_get_contents('search_log.json', true);
      $file=Storage::disk('search')->get('search_log.json');
      $data = json_decode($file,true);
      //unset($file);
      //return $data;
      $d=array();
      $day=array();
      $word=array();
      foreach($data as $item) { //foreach element in $arr
        //$dateTime = new DateTime($item['dateTime']);
        $dateTime1=Carbon::parse($item['dateTime'])->format('d-m-Y');
        $dateTime=Carbon::parse($item['dateTime'])->format('F-Y');
        $searchTerms=$item['terms'];
        //$date = $item['dateTime']; //etc
        //print $date.'<br>';
        $d[]=$dateTime;
        $day[]=$dateTime1;
        $word[]=$searchTerms;
      }
      $search_terms = array_count_values($word);
      arsort($search_terms, SORT_NUMERIC);
      //return $d;
      //$p=array();
      $p[]=array_count_values($d);
      $q[]=array_count_values($day);
      //$words[]=array_count_values($word);
      //return $p;
      //$json_string = json_encode($p, JSON_PRETTY_PRINT);
      //$json_string1 = json_encode($q, JSON_PRETTY_PRINT);
      return new JsonResponse([
          "search_terms"=>$search_terms,
          "per_month_search"=>$p,
          "per_day_search" => $q
        ]);
      //you need to add new data as next index of data.
      // $data[] =array(
      //     'dateTime'=> date('Y-m-d H:i:s'),
      //     'terms' => $terms,
      //     'url' => $request->url(),
      //     'from_IP' =>$clientDevice
      //     );
      // $result=json_encode($data,JSON_PRETTY_PRINT);
      // file_put_contents('search_log.json', $result);
      // unset($result);
      // $log_save="ok";

      //return $date;
    }
    public function slack($var)
    {
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
            $message = array('payload' => json_encode(array('text' => $var)));
    // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);
    }


    // ----------- TNT search ----------------
    public function indextntsearch(Request $request){
      /*  $tnt = new TNTSearch;

        $tnt->loadConfig([
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'ethikana',
            'username'  => 'root',
            'password'  => 'root',
            'storage'   => '/var/www/html/ethikana/storage/custom/'
        ]);

        $indexer = $tnt->createIndex('name.index');
        $indexer->query('SELECT id, Address FROM places;');
        $indexer->run();
      */

    /*  $classifier = new TNTClassifier();
      $classifier->learn("nearby", "near");
      $classifier->learn("near", "near");


      $guess = $classifier->predict($request->q);

      $fuzzy_prefix_length  = 4;
      $fuzzy_max_expansions = 20;
    */$fuzzy_distance       = 4;
      $tnt = new TNTSearch;

     $tnt->loadConfig([
         'driver'    => 'mysql',
         'host'      => 'localhost',
         'database'  => 'ethikana',
         'username'  => 'root',
         'password'  => 'root',
         'storage'   => '/var/www/html/ethikana/storage/custom/'
     ]);

     $tnt->selectIndex("places.index");
     //$tnt->fuzziness = true;
     //$tnt->asYouType = true;
    $res = $tnt->search(str_replace(' ', '+',$request->search),20);
    $place = Place::with('images')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get();
  //  $place = $this->searchx($request->search);

    return response()->json(['places'=>$place,'result' =>$res,]);

  }
  public function getTntsearch(Request $request)
  {
     /*$fuzzy_prefix_length  = 4;
     $fuzzy_max_expansions = 50;
     $fuzzy_distance       = 2;*/
     $tnt = new TNTSearch;

    $tnt->loadConfig([
        'driver'    => 'mysql',
        'host'      => 'localhost',
        'database'  => 'ethikana',
        'username'  => 'root',
        'password'  => 'root',
        'storage'   => '/var/www/html/ethikana/storage/custom/'
    ]);

    $tnt->selectIndex("places.index");
    $tnt->fuzziness = true;
    //$tnt->asYouType = true;

    //$query = $this->expand($request->get('search'));
    //$res = $tnt->searchBoolean(str_replace(' ', '+',$request->search),20);
    $res = $tnt->search($request->search,20);
    $place = NewPlace::with('images')->where('new_address','LIKE','%'.$request->search.'%')->orWhere('alternate_address','LIKE','%'.$request->search.'%')->limit(5)->get();

    if (count($place)===0) {
      if (count($res['ids'])>0) {
        $place = NewPlace::with('images')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get();
      }

    }

    DB::table('analytics')->increment('search_count',1);
     //$startTimer = microtime(true);
    //$place = Place::with('images')->where('Address','LIKE','%'.$request->search.'%')->limit(10)->get();
    //$place = DB::raw("SELECT * FROM places WHERE id IN $res ORDER BY FIELD(id, ".implode(",",$res).");");
  //  $place = $this->searchx($request->search);
    //$stopTimer = microtime(true);


      return response()->json(['places'=>$place],200); //round($stopTimer - $startTimer, 7) *1000 ." ms" ]);


  }

  public static $query = [
       'house'          => 'house',
       'House No'     => 'House',
       'Block'        => 'Mirpur Bashudhara',
       'Road'        => 'Road',
       'Block Section' => 'Mirpur',
       'Sector'       => 'Uttara',

   ];
   public static function expand($query)
   {
       $query = trim($query);
       if (isset(self::$query[$query])) {
           return self::$query[$query];
       }
       return $query;
   }

  public function APIsearch(Request $request)
   {
    /*  $fuzzy_prefix_length  = 2;
      $fuzzy_max_expansions = 50;
      $fuzzy_distance       = 4;
      $tnt = new TNTSearch;

     $tnt->loadConfig([
         'driver'    => 'mysql',
         'host'      => 'localhost',
         'database'  => 'ethikana',
         'username'  => 'root',
         'password'  => 'root',
         'storage'   => '/var/www/html/ethikana/storage/custom/'
     ]);

     $tnt->selectIndex("places.index");
     $tnt->fuzziness = true;
     $tnt->asYouType = true;

     //$query = $this->expand($request->get('search'));


    //$res = $tnt->search($search,20);

    */
    $startTimer = microtime(true);
    $place = DB::connection('sqlite')->table('places_3')
    ->where('new_address','Like','%'.$request->search.'%')
    ->orWhere('alternate_address','Like','%'.$request->search.'%')
    ->limit(15)->get(['Address','area','city','postCode','uCode','route_description','longitude','latitude']);
    //$place = DB::connection('sqlite')->select("SELECT * FROM PLACES WHERE Address LIKE '%House 4, Road 1, Block C, Section 2%'")->limit(10);
    //$res = $tnt->search($request->search,20);
    $q = $request->search;
    $y = '';
    if (count($place)===0) {

      $q = preg_replace("/[-]/", " ", $q);
      $q = preg_replace('/\s+/', ' ',$q);
      $place = DB::connection('sqlite')->table('places_3')
      ->select('*')
      ->where('new_address','Like','%'.$q.'%')
      ->orWhere('alternate_address','Like','%'.$q.'%')
      ->limit(15)->get();
      if (count($place)===0){
        $str = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
        $x = explode(" ",$str);
        $y=''.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
        $place = DB::connection('sqlite')->table('places_3')
         ->select('*')
         ->where('new_address','Like','%'.$y.'%')
         ->orWhere('alternate_address','Like','%'.$y.'%')
         ->limit(15)->get();

         if (count($place)===0) {

            $str = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
              $x = explode(" ",$str);
              $y=''.$x[sizeof($x)-4].' '.$x[sizeof($x)-3].' '.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
              $place = DB::connection('sqlite')->table('places_3')
               ->select('*')
               ->where('new_address','Like','%'.$y.'%')
               ->orWhere('alternate_address','Like','%'.$y.'%')
               ->limit(20)->get();
            }


      }

    }

     DB::table('analytics')->increment('search_count',1);
      //$startTimer = microtime(true);
     //$place = Place::with('images')->where('Address','LIKE','%'.$request->search.'%')->limit(10)->get();
     //$place = DB::raw("SELECT * FROM places WHERE id IN $res ORDER BY FIELD(id, ".implode(",",$res).");");
   //  $place = $this->searchx($request->search);
     //$stopTimer = microtime(true);

      $stopTimer = microtime(true);
       return response()->json(['sub'=>round($stopTimer - $startTimer, 7) *1000,'places'=>$place],200); //round($stopTimer - $startTimer, 7) *1000 ." ms" ]);


   }


   public function APInearBy($search)
   {
     //$places = Place::with('images')->where('uCode','=',$ucode)->first();
     $place =explode(",", $search);
     $lat = $place[1];
     $lon = $place[0];
    $result = Place::with('images')
         ->select(DB::raw('*, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
       //  ->select(DB::raw('uCode, ( 6371 * acos(cos( radians(23) ) * cos( radians( '.$lat.' ) ) * cos( radians( '.$lon.' ) - radians(90) ) + sin( radians(23) ) * sin( radians( '.$lat.' ) ) ) ) AS distance'))
         ->where('flag','=',1)
         ->whereNotIn('pType', ['Residential','Vacant'])
         ->having('distance','<',0.5)
         ->orderBy('distance')
         ->limit(10)
         ->get();
     DB::table('analytics')->increment('search_count',1);
     return $result->toJson();
}


}