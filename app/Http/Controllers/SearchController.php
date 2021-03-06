<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;
use Auth;
use App\User;
use App\Place;
use App\placestwo;
use App\NewPlace;
use App\SavedPlace;
use App\Referral;
use App\analytics;
use App\Image;
use App\Searchlytics;
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
use Illuminate\Support\Facades\Redis;

// SEARCH MAIN
class SearchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


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

        if(Place::where('uCode','=',$terms)->exists())
        {
          $posts=Place::with('images')->where('uCode','=',$terms)->get(['Address','uCode','area','city','postCode']);
        }
        else{
          $fuzzy_prefix_length  = 2;
          $fuzzy_max_expansions = 50;
          $fuzzy_distance       = 2;
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




         $res = $tnt->searchBoolean($q,20);
         if (count($res['ids'])>0) {
           $posts = Place::with('images')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['Address','uCode','area','city','postCode']);
           //return response()->json(['places' => $place]);
         }
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
              $weblink="https://barikoi.com/#/map/".$code;
              // http://barikoi.com/#/map/MXVZ6205
              //echo count($post->images);

              if(count($post->images)==0){
                $img='';
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
     $fuzzy_prefix_length  = 4;
     $fuzzy_max_expansions = 50;
     $fuzzy_distance       = 2;
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
    //$res = $tnt->searchBoolean(str_replace(' ', '+',$request->search),20);
    $res = $tnt->search($request->search,20);
    $place = Place::with('images')->where('Address','LIKE','%'.$request->search.'%')->limit(5)->get(['id','Address','longitude','latitude','pType','subType','ward','zone','uCode', 'area','city']);

    if (count($place)===0) {
      if (count($res['ids'])>0) {
        $place = Place::with('images')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','longitude','latitude','pType','subType','ward','zone','uCode', 'area','city']);
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

      $fuzzy_prefix_length  = 2;
      $fuzzy_max_expansions = 50;
      $fuzzy_distance       = 2;
      $tnt = new TNTSearch;

     $tnt->loadConfig([
         'driver'    => 'mysql',
         'host'      => 'localhost',
         'database'  => 'ethikana',
         'username'  => 'root',
         'password'  => 'root',
         'storage'   => '/var/www/html/ethikana/storage/custom/'
     ]);


     //$query = $this->expand($request->get('search'));


    $startTimer = microtime(true);
    $tnt->selectIndex("places.index");
    $tnt->fuzziness = true;
    $tnt->asYouType = true;


    $q = $request->search;

    DB::table('Searchlytics')->insert(['query' => $q]);
    if(Place::where('uCode','=',$q)->exists())
    {
       $place=Place::with('images')->where('uCode','=',$q)->get();
     }else{
     $q = preg_replace("/[-]/", " ", $q);
     $q = preg_replace('/\s+/', ' ',$q);
     $y = '';
     $str = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
     $x = explode(" ",$str);
     $size = sizeof($x);
     $place = DB::connection('sqlite')->table('places_3')
     ->where('new_address','Like','%'.$q.'%')
     ->orWhere('alternate_address','Like','%'.$q.'%')
     ->limit(20)->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);

     if (count($place)==0) {

     if ($size>=6)
     {
       $y=''.$x[sizeof($x)-4].' '.$x[sizeof($x)-3].' '.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
       $place = DB::connection('sqlite')->table('places_3')
        ->select('*')
        ->where('new_address','Like','%'.$y.'%')
        ->orWhere('alternate_address','Like','%'.$y.'%')
        ->limit(20)->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
      }
      if ($size <=5) {
        $y=''.$x[sizeof($x)-1].'';
        $place = DB::connection('sqlite')->table('places_3')
         ->select('*')
         ->where('new_address','Like','%'.$y.'%')
         ->orWhere('alternate_address','Like','%'.$y.'%')
         ->limit(20)->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
        /* if (count($place)==0) {
           $y=''.$x[sizeof($x)-2].''.$x[sizeof($x)-1].'';
           $place = DB::connection('sqlite')->table('places_3')
            ->select('*')
            ->where('new_address','Like','%'.$y.'%')
            ->orWhere('alternate_address','Like','%'.$y.'%')
            ->limit(20)->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
        */   if(count($place)>=0 || count($place)>=20) {
             $res = $tnt->searchBoolean($q,20);
             $place = Place::with('images')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get();

           }
         //}
       }
     }
   }
     DB::table('analytics')->increment('search_count',1);
     return response()->json(['places'=>$place]);


      $stopTimer = microtime(true);
      return response()->json(['sub'=>round($stopTimer - $startTimer, 7) *1000,'places'=>$place],200); //round($stopTimer - $startTimer, 7) *1000 ." ms" ]);


   }

   public function testSearchtwo(Request $request)
   {
     $fuzzy_prefix_length  = 2;
     $fuzzy_max_expansions = 50;
     $fuzzy_distance       = 2;
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
    $tnt->asYouType = false;


    $q = $request->search;
    /*if (strpos($q, ' rd') !== false || strpos($q, ' rd ') !== false || strpos($q, 'rd ') !== false || strpos($q, 'h ') !== false || strpos($q, ' h ') !== false || strpos($q, ' h') !== false) {
      $q = str_replace(' rd', ' road',$q);
      $q = str_replace(' rd ', ' road',$q);
      $q = str_replace('rd ', ' road ',$q);
      $q = str_replace('h ', 'house ',$q);
      $q = str_replace(' h ', 'house ',$q);
     // $q = str_replace(' h', 'house ',$q);
    }
    $q= ltrim($q,' ');
    */

    $res = $tnt->searchBoolean($q,20);
    $keys = collect($res['ids'])->values()->all();
    if (count($res['ids'])>0) {
      $place = DB::table('places_3')->whereIn('id', $keys)/*orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))*/->get(['id','Address','new_address','area','city','postCode','uCode','longitude','latitude','pType','subType']);
      return response()->json(['String' => $q,'places' => $place]);
    }
    else {
        $q = preg_replace("/[\/,]/", " ", $q);
        $res = $tnt->search($q,20);
        $keys = collect($res['ids'])->values()->all();
        if (count($res['ids'])>0) {
          $place = DB::table('places_3')->whereIn('id', $keys)->/*orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->*/get(['id','Address','new_address','area','city','postCode','uCode','longitude','latitude','pType','subType']);
          return response()->json(['String' => $q,'places' => $place]);
        }
        else {
          return response()->json(['String two' => $q,'places'=>'Not found']);
        }

    }
   }

   public function BugFixSearch(Request $request)
   {
     $q = $request->search;
     $fuzzy_prefix_length  = 2;
     $fuzzy_max_expansions = 50;
     $fuzzy_distance       = 3;
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

      $res = $tnt->searchBoolean($q,10);
      if (count($res['ids'])>0) {
        $place = DB::table('places_3')->whereIn('id', $res['ids'])->where('flag','1')->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
        return response()->json(['places'=>$place ]);
      }else {

        return response()->json([
          'message' => 'not found',
          'status' => '200',
        ]);
   }
 }

public function testSearchthree(Request $request)
   {
     $fuzzy_prefix_length  = 2;
     $fuzzy_max_expansions = 50;
     $fuzzy_distance       = 3;
     $tnt = new TNTSearch;

    $tnt->loadConfig([
        'driver'    => 'mysql',
        'host'      => 'ls-f92239927d8abce6431333a5c33375fd67bc4025.ceyzgzybzzhb.ap-southeast-1.rds.amazonaws.com',
        'database'  => 'ethikana',
        'username'  => 'dbmasteruser',
        'password'  => 'Amitayef5.7barikoi1216',
        'storage'   => '/var/www/html/ethikana/storage/custom/'
    ]);

    $tnt->selectIndex("places.index");
    $tnt->fuzziness = true;
    $tnt->asYouType = true;
    $message = 'someone searched "'.$request->search.'" from web ';
    $this->SlackSearch($message);
    if ($request->has('search')) {
      $q = strtolower($request->search);
      if ($place = Redis::get($q)) {
        $place = json_decode($place);
        return response()->Json([
          'q'=> $q,
          'places' => $place,
          'source' => 'Cache'
        ]);
      }
      $res = DB::connection('sqlite')->table('places_3')->where('Address','=',$q)->get(['id','Address','new_address','alternate_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
      if (isset($res[0]) && $this->isExactMatch($request, $res[0])) {
          Redis::setex($q, 60*24,$res[0]);
          return response()->json( [
              'places'       => $res[0],
              'Souce' => 'exact match'
          ]);
      }


        // $place = $this->elasticSearch($q);
        // if (count($place)>0) {
        //    //Redis::setex($q, 60,$place);
        //    return response()->json(['source' => 'Elastic','places'=>$place]);
        // }else{
        $place = $this->linearsearch($q);
       if (count($place)>0) {
          Redis::setex($q, 60*24,$place);
         return response()->json(['Source'=> 'sqlite','q'=> $q,'places'=>$place ]);
       }else {
         $q = preg_replace("/[(^)]/", " ",$q);
         $q = preg_replace('/\s+/', ' ',$q);
         $q = str_replace(",","",$q);
         $place = $this->linearsearchlong($q);

        if (count($place)>0) {
            Redis::setex($q, 60*1,$place);
           return response()->json(['q'=>$q,'Source'=> 'sqlite long','places'=>$place ]);
         }else {
           $place = $this->uCodeSearch($q);
           if (count($place)>0) {
             Redis::set($q, $place);
             return response()->json(['places'=>$place ]);
           }
           else {

             $q = str_replace("  "," ",$q);
             $q = str_replace("/"," ",$q);
             $q = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
             $res = $tnt->searchBoolean($q,10);
             if (count($res['ids'])>0) {

               $place = DB::table('places_3')->whereIn('id', $res['ids'])->where('flag','1')->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
              // Redis::setex($q, 60*1,$place);
               return response()->json(['Q tnt search'=>$q ,'places' => $place]);
             }else{
               $q = preg_replace("/[\/,-]/", " ", $q);
               $x = explode(" ",$q);
               $size = sizeof($x);
               if (count($size)>1) {
                 $y=''.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
                 $res = $tnt->searchBoolean($y,10);
               }else {
                 $res = $tnt->searchBoolean($q,10);
               }
               if (count($res['ids'])>0) {
                  $place = DB::table('places_3')->whereIn('id', $res['ids'])->where('flag','1')->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
                //  Redis::setex($q, 60*1,$place);
                  return response()->json(['y'=> $y,'places' => $place]);
                }else {

                  $q = str_replace("  "," ",$q);

                //  $q = preg_replace('/\b\w\b(\s|.\s)?/', '', $q); //get rid of single letters
                  $res = $tnt->search($q,10);
                  if (count($res['ids'])>0) {
                    $place = DB::table('places_3')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
                  //  Redis::setex($q, 60*1,$place);
                    return response()->json(['String' => $q,'places' => $place, 'Source'=>'TNT']);
                  }
                  else {

                    $place = $this->elasticSearch($q);
                    if (count($place)>0) {
                       return response()->json(['source' => 'Elastic','places'=>$place]);
                    }else{
                    DB::table('Searchlytics')->insert(['query' => $q]);
                    return response()->json([
                      'message' => 'not found',
                      'status' => '200',
                      'Q Not Found'=> $q

                    ]);
                  }

                }

             }
           }
         }
         }
       }
     }else {
       return new JsonResponse([
         'message' => 'empty request',
       ]);
     }

  }
public function batchGeo(Request $request)
{
  $fuzzy_prefix_length  = 2;
  $fuzzy_max_expansions = 50;
  $fuzzy_distance       = 3;
  $tnt = new TNTSearch;
  $q = $request->search;
  $x = explode(",",$q);


 $tnt->loadConfig([
     'driver'    => 'mysql',
     'host'      => 'ls-f92239927d8abce6431333a5c33375fd67bc4025.ceyzgzybzzhb.ap-southeast-1.rds.amazonaws.com',
     'database'  => 'ethikana',
     'username'  => 'dbmasteruser',
     'password'  => 'Amitayef5.7barikoi1216',
     'storage'   => '/var/www/html/ethikana/storage/custom/'
 ]);
  $res = DB::connection('sqlite')->table('places_3')->where('Address','=',$x[0])->get(['id','Address','new_address','alternate_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
  if (isset($res[0]) && $this->isExactMatch($request, $res[0])) {
      return response()->json( [
          'places'       => $res[0],
          'Souce' => 'exact match'
      ]);
  }
  $places = $this->linearsearch($x[0]);
  if (isset($places[0])) {
    return response()->json([
        'places'       => $places[0],
        'Souce' => 'sqlite'
    ]);
  }
    $places = $this->linearsearchlong($x[0]);
  if (isset($places[0])) {
    return response()->json([
        'places'       => $places[0],
        'Souce' => 'sqlite all match'
    ]);
  }
  $tnt->selectIndex("places.index");
  $tnt->fuzziness = true;
  $tnt->asYouType = true;
//  $q = preg_replace("/[(^)]/", " ",$q);
  $res = $tnt->searchBoolean($x[0],10);
  if (count($res['ids'])>0) {
    $place = DB::table('places_3')->whereIn('id', $res['ids'])->where('flag','1')->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
    return response()->json(['Q tnt search'=>$request->search ,'places' => $place[0]]);
  }
  $places=$this->linearsearchLocate($request->search);
  if (count($places)>0) {
    return response()->json([
        'places'       => $places,
        'Souce' => 'locate'
    ]);
  }
  else {
    return response()->json([
        'Message'       => 'Not Found',

    ]);
  }

}

/// autocomplete
public function SearchAutocomplete(Request $request)
   {
     $user = $request->user()->name;
     $fuzzy_prefix_length  = 2;
     $fuzzy_max_expansions = 50;
     $fuzzy_distance       = 3;
     $tnt = new TNTSearch;

     $tnt->loadConfig([
         'driver'    => 'mysql',
         'host'      => 'ls-f92239927d8abce6431333a5c33375fd67bc4025.ceyzgzybzzhb.ap-southeast-1.rds.amazonaws.com',
         'database'  => 'ethikana',
         'username'  => 'dbmasteruser',
         'password'  => 'Amitayef5.7barikoi1216',
         'storage'   => '/var/www/html/ethikana/storage/custom/'
     ]);

    $tnt->selectIndex("places.index");
    $tnt->fuzziness = true;
    //$tnt->asYouType = true;
    $message = ''.$user.' searched '.$request->search.' from app ';
    $this->SlackSearch($message);

    if ($request->has('search')) {
      $q = strtolower($request->search);

      if ($place = Redis::get($q)) {
        $place = json_decode($place);
        return response()->Json([
          'q'=> $q,
          'places' => $place,
          'source' => 'Cache'
        ]);
      }
      $place = $this->elasticSearch($q);
      if (count($place)>0) {

         return response()->json(['source' => 'Elastic','places'=>$place]);
      }else{
        $place = DB::connection('sqlite')->table('places_3')
        ->where('flag',1)
        ->where('new_address','Like','%'.$q.'%')
        ->orWhere('alternate_address','Like','%'.$q.'%')
        ->limit(10)->get(['id','Address','new_address','area','city','uCode']);
        if (count($place)>0) {

         return response()->json(['Source'=> 'sqlite','q'=> $q,'places'=>$place ]);
       }else {
         $q = preg_replace("/[(^)]/", " ",$q);
         $q = preg_replace('/\s+/', ' ',$q);
         $q = str_replace(",","",$q);
         $place = DB::connection('sqlite')->table('places_3')
         ->where('flag',1)
         ->where('new_address','Like','%'.$q.'%')
         ->orWhere('alternate_address','Like','%'.$q.'%')
         ->limit(10)->get(['id','Address','new_address','area','city','uCode']);
        if (count($place)>0) {

           return response()->json(['q'=>$q,'Source'=> 'sqlite','places'=>$place ]);
         }else {
           $place = $this->uCodeSearch($q);
           if (count($place)>0) {
             Redis::set($q, $place);
             return response()->json(['places'=>$place ]);
           }
           else {

             $q = str_replace("  "," ",$q);
             $q = str_replace("/"," ",$q);
             $q = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
             $res = $tnt->searchBoolean($q,10);
             if (count($res['ids'])>0) {

               $place = DB::table('places_3')->whereIn('id', $res['ids'])->where('flag','1')->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','uCode']);

               return response()->json(['Q tnt search'=>$q ,'places' => $place]);
             }else{
               $q = preg_replace("/[\/,-]/", " ", $q);
               $x = explode(" ",$q);
               $size = sizeof($x);
               if (count($size)>1) {
                 $y=''.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
                 $res = $tnt->searchBoolean($y,10);
               }else {
                 $res = $tnt->searchBoolean($q,10);
               }
               if (count($res['ids'])>0) {
                  $place = DB::table('places_3')->whereIn('id', $res['ids'])->where('flag','1')->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','uCode']);

                  return response()->json(['y'=> $y,'places' => $place]);
                }else {

                  $q = str_replace("  "," ",$q);

                //  $q = preg_replace('/\b\w\b(\s|.\s)?/', '', $q); //get rid of single letters
                  $res = $tnt->search($q,10);
                  if (count($res['ids'])>0) {
                    $place = DB::table('places_3')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','uCode']);

                    return response()->json(['String' => $q,'places' => $place, 'Source'=>'TNT']);
                  }
                  else {

                    DB::table('Searchlytics')->insert(['query' => $q]);
                    return response()->json([
                      'message' => 'not found',
                      'status' => '200',
                      'Q Not Found'=> $q

                    ]);
                  }

                }

             }
           }
         }
         }
       }
     }else {
       return new JsonResponse([
         'message' => 'empty request',
       ]);
     }

  }

  public function SearchAutocompleteWeb(Request $request)
     {
       //$user = $request->user()->name;
       $fuzzy_prefix_length  = 2;
       $fuzzy_max_expansions = 50;
       $fuzzy_distance       = 3;
       $tnt = new TNTSearch;

       $tnt->loadConfig([
           'driver'    => 'mysql',
           'host'      => 'ls-f92239927d8abce6431333a5c33375fd67bc4025.ceyzgzybzzhb.ap-southeast-1.rds.amazonaws.com',
           'database'  => 'ethikana',
           'username'  => 'dbmasteruser',
           'password'  => 'Amitayef5.7barikoi1216',
           'storage'   => '/var/www/html/ethikana/storage/custom/'
       ]);

      $tnt->selectIndex("places.index");
      $tnt->fuzziness = true;
      //$tnt->asYouType = true;
      $message = 'Someone  '.$request->search.' from website';
      $this->SlackSearch($message);

      if ($request->has('search')) {
        $q = strtolower($request->search);

        if ($place = Redis::get($q)) {
          $place = json_decode($place);
          return response()->Json([
            'q'=> $q,
            'places' => $place,
            'source' => 'Cache'
          ]);

        }
        // $place = $this->elasticSearch($q);
        // if (count($place)>0) {
        //
        //    return response()->json(['source' => 'Elastic','places'=>$place]);
        // }else{

          $place = DB::connection('sqlite')->table('places_3')
          ->where('flag',1)
          ->where('new_address','Like','%'.$q.'%')
          ->orWhere('alternate_address','Like','%'.$q.'%')
          ->limit(10)->get(['id','Address','new_address','area','city','uCode']);
          if (count($place)>0) {
            Redis::setex($q, (60*24*60),$place);
           return response()->json(['Source'=> 'sqlite','q'=> $q,'places'=>$place ]);
         }else {
           $q = preg_replace("/[(^)]/", " ",$q);
           $q = preg_replace('/\s+/', ' ',$q);
           $q = str_replace(",","",$q);
           $place = DB::connection('sqlite')->table('places_3')
           ->where('flag',1)
           ->where('new_address','Like','%'.$q.'%')
           ->orWhere('alternate_address','Like','%'.$q.'%')
           ->limit(10)->get(['id','Address','new_address','area','city','uCode']);
          if (count($place)>0) {

             return response()->json(['q'=>$q,'Source'=> 'sqlite','places'=>$place ]);
           }else {
             $place = $this->uCodeSearch($q);
             if (count($place)>0) {
               Redis::set($q, $place);
               return response()->json(['places'=>$place ]);
             }
             else {

               $q = str_replace("  "," ",$q);
               $q = str_replace("/"," ",$q);
               $q = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
               $res = $tnt->searchBoolean($q,10);
               if (count($res['ids'])>0) {

                 $place = DB::table('places_3')->whereIn('id', $res['ids'])->where('flag','1')->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','uCode']);

                 return response()->json(['Q tnt search'=>$q ,'places' => $place]);
               }else{
                 $q = preg_replace("/[\/,-]/", " ", $q);
                 $x = explode(" ",$q);
                 $size = sizeof($x);
                 if (count($size)>1) {
                   $y=''.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
                   $res = $tnt->searchBoolean($y,10);
                 }else {
                   $res = $tnt->searchBoolean($q,10);
                 }
                 if (count($res['ids'])>0) {
                    $place = DB::table('places_3')->whereIn('id', $res['ids'])->where('flag','1')->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','uCode']);

                    return response()->json(['y'=> $y,'places' => $place]);
                  }else {

                    $q = str_replace("  "," ",$q);

                  //  $q = preg_replace('/\b\w\b(\s|.\s)?/', '', $q); //get rid of single letters
                    $res = $tnt->search($q,10);
                    if (count($res['ids'])>0) {
                      $place = DB::table('places_3')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','new_address','area','city','uCode']);

                      return response()->json(['String' => $q,'places' => $place, 'Source'=>'TNT']);
                    }
                    else {

                      DB::table('Searchlytics')->insert(['query' => $q]);
                      return response()->json([
                        'message' => 'not found',
                        'status' => '200',
                        'Q Not Found'=> $q

                      ]);
                    }

                  }

//               }
             }
           }
           }
         }
       }else {
         return new JsonResponse([
           'message' => 'empty request',
         ]);
       }

    }




  public function searchAdmin(Request $request)
      {
        $fuzzy_prefix_length  = 2;
        $fuzzy_max_expansions = 50;
        $fuzzy_distance       = 3;
        $tnt = new TNTSearch;

        $tnt->loadConfig([
            'driver'    => 'mysql',
            'host'      => 'ls-f92239927d8abce6431333a5c33375fd67bc4025.ceyzgzybzzhb.ap-southeast-1.rds.amazonaws.com',
            'database'  => 'ethikana',
            'username'  => 'dbmasteruser',
            'password'  => 'Amitayef5.7barikoi1216',
            'storage'   => '/var/www/html/ethikana/storage/custom/'
        ]);
       $tnt->selectIndex("places.index");
       $tnt->fuzziness = true;
       $tnt->asYouType = true;

       if ($request->has('search')) {
         $q = $request->search;
         if ($place = Redis::get($q)) {
           $place = json_decode($place);
           return response()->Json([
             'q'=> $q,
             'places' => $place,
             'source' => 'Cache'
           ]);
         }
         //$q= str_replace('#', ' ',$q);
         //$q= str_replace('  ', ' ',$q);
         // $place = $this->elasticSearch($q);
         // if (count($place)>0) {
         //     return response()->json(['source' => 'Elastic','places'=>$place]);
         // }else{
         $place = DB::table('places')->where('Address','Like','%'.$q.'%')->limit(10)
          ->get(['id','Address','area','city','postCode','uCode','longitude','latitude','pType','subType','updated_at','contact_person_phone','user_id']);
          if (count($place)>0) {
            // Redis::setex($q, 60*60*1,$place);
             return response()->json(['q'=>$q,'Source '=> 'Database','places'=>$place ]);
           }else {
             $q = preg_replace("/[(^)]/", " ", $q);
             $q = preg_replace('/\s+/', ' ',$q);
             $q = preg_replace("/[^A-Za-z0-9\s]/", "",$q);

             $place = DB::table('places')->where('Address','Like','%'.$q.'%')
             ->limit(10)->get(['id','Address','area','city','postCode','uCode','longitude','latitude','pType','subType','updated_at','contact_person_phone','user_id']);

             if (count($place)>0) {
               return response()->json(['q'=>$q,'Source '=> 'Database','places'=>$place  ]);
            }else {
               $place = DB::table('places')->where('uCode','=',$q)->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone','user_id']);

              if (count($place)>0) {
                return response()->json(['places'=>$place ]);
              }
              else {
                $q = preg_replace("/[\/,-]/", " ", $q);
                $res = $tnt->searchBoolean($q,10);
                if (count($res['ids'])>0) {
                  $place = DB::table('places')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone','user_id']);
                  return response()->json(['places' => $place]);
                }else{
                  $x = explode(" ",$q);
                  $size = sizeof($x);
                  if (count($size)>1) {
                    $y=''.$x[0].' '.$x[sizeof($x)-1].'';
                    $res = $tnt->searchBoolean($y,10);
                  }else {
                    $res = $tnt->searchBoolean($q,20);
                  }
                  if (count($res['ids'])>0) {
                     $place = DB::table('places')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone','user_id']);
                     return response()->json(['y'=> $y,'places' => $place]);
                   }else {
                     $client = new \GuzzleHttp\Client();
                     $res = $client->request('GET', 'photon.komoot.de/api/?q='.$q.'');
                     //preg_match_all('!\d+!', $str->Address, $matches);
                     $res = $res->getBody();
                     $data = json_decode( $res, true );
                     $name =$data['features'];
                     $longitude = $name[0]['geometry']['coordinates'][0];
                     $latitude = $name[0]['geometry']['coordinates'][1];
                     $Address = $name[0]['properties']['name'];


                     return new JsonResponse([
                         'places' => array([
                          'Address' => $Address,
                          'postCode' => $name[0]['properties']['postcode'],
                          'city' => $name[0]['properties']['city'],
                          'longitude' => $longitude,
                          'latitude' => $latitude,
                          'uCode' => 'Not Available',
                          'subType' => 'Not Available',
                          'pType' => 'Not Available',
                          'Data Source' => 'OpenstreetMap',
                        ])
                     ]);
                    /* DB::table('Searchlytics')->insert(['query' => $q]);
                     return response()->json([
                       'message' => 'not found',
                       'status' => '200',
                       'q' => $q,
                     ]);*/
                   }

                }
              }

            }
         }

      }else {
          return new JsonResponse([
            'message' => 'empty request',
          ]);
        }

  }
  public function elasticSearch($q)
  {
    //$q=$request->search;
    $q = $q;
    $query = 'elastic.barikoi.com/api/search?q='.$q.'';
    $client = new \GuzzleHttp\Client();
    $res = $client->request('POST', $query);
    $res = $res->getBody();
    $data = json_decode( $res, true );
    //
    // $st_code = $data->status_code;
    // if ($st_code = 500) {
    //   return null;
    // }else{
    return $data;


  }

   public function linearsearch($q)
   {
     $place = DB::connection('sqlite')->table('places_3')
     ->where('flag',1)
     ->where('new_address','Like',$q.'%')
     ->orWhere('alternate_address','Like',$q.'%')
     ->limit(10)->get(['id','Address','new_address','alternate_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
     return $place;
   }
   public function linearsearchLocate($q)
   {
     $place = DB::select("SELECT Address,new_address,alternate_address, uCode, longitude,latitude,id,pType,subType,area from places_3 WHERE Locate(Address, '$q')");
     return $place;
   }

   public function linearsearchlong($q)
   {
     $place = DB::connection('sqlite')->table('places_3')
     ->where('flag',1)
     ->where('new_address','Like','%'.$q.'%')
     ->orWhere('alternate_address','Like','%'.$q.'%')
     ->limit(10)->get(['id','Address','new_address','alternate_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
     return $place;
   }
   public function uCodeSearch($q)
   {
     $place = DB::table('places_3')->where('uCode','=',$q)->get(['id','Address','new_address','alternate_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at','contact_person_phone']);
     return $place;
   }
   public function TntId($res)
   {
     return $res;
   }



   public function testSearch(Request $request)
   {
     $fuzzy_prefix_length  = 2;
     $fuzzy_max_expansions = 50;
     $fuzzy_distance       = 2;
     $tnt = new TNTSearch;

     $tnt->loadConfig([
         'driver'    => 'mysql',
         'host'      => 'ls-f92239927d8abce6431333a5c33375fd67bc4025.ceyzgzybzzhb.ap-southeast-1.rds.amazonaws.com',
         'database'  => 'ethikana',
         'username'  => 'dbmasteruser',
         'password'  => 'Amitayef5.7barikoi1216',
         'storage'   => '/var/www/html/ethikana/storage/custom/'
     ]);

    $tnt->selectIndex("places.index");
    $tnt->fuzziness = true;
    $tnt->asYouType = true;


    $q = $request->search;
    if (empty($q)) {
      return response()->json(['Message' => 'No input']);
    }
    $y = '';
    DB::table('Searchlytics')->insert(['query' => $q]);
    if(DB::table('places_3')->where('uCode','=',$q)->exists()){
       $place=Place::with('images')->select('id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at')->where('uCode','=',$q)->get();
     }
     else{
       $place = DB::connection('sqlite')->table('places_3')
       ->where('new_address','Like','%'.$q.'%')
       ->orWhere('alternate_address','Like','%'.$q.'%')
       ->orderBy('id','desc')
       ->limit(10)->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);

     if (count($place)===0) {
       $q = preg_replace("/[-]/", " ", $q);
       $q = preg_replace('/\s+/', ' ',$q);
       $str = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
       $x = explode(" ",$str);
       $size = sizeof($x);
       $place = DB::connection('sqlite')->table('places_3')
       ->where('new_address','Like','%'.$str.'%')
       ->orWhere('alternate_address','Like','%'.$str.'%')
       ->orderBy('id','desc')
       ->limit(10)->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
    if (count($place)===0) {
      // if string size is less then equal to 5 words
      if ($size <=5) {
        $y=''.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
        $place = DB::connection('sqlite')->table('places_3')
         ->where('new_address','Like','%'.$y.'%')
         ->orWhere('alternate_address','Like','%'.$y.'%')
         ->orderBy('id','desc')
         ->limit(20)->get(['id','Address','area','new_address','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
         if (count($place)===0) {
           $y=''.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
           $place = DB::connection('sqlite')->table('places_3')
            ->where('flag',1)
            ->where('new_address','Like','%'.$y.'%')
            ->orWhere('alternate_address','Like','%'.$y.'%')
            ->orderBy('id','desc')
            ->limit(20)->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
           if(count($place)>=0 || count($place)>=20) {
             $q = preg_replace("/[\/,-]/", " ", $q);
             $res = $tnt->searchBoolean($q,20);

             if (count($res['ids'])>0) {

               $place = DB::table('places_3')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get();
               return response()->json(['places' => $place]);
             }else{
               return response()->json(['Message'=>'Not found']);
             }

           }
         }
       }

       if ($size>=6)
       {
         $y=''.$x[sizeof($x)-5].' '.$x[sizeof($x)-4].' '.$x[sizeof($x)-3].' '.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
           $place = DB::connection('sqlite')->table('places_3')
            ->where('new_address','Like','%'.$y.'%')
            ->orWhere('alternate_address','Like','%'.$y.'%')
            ->orderBy('id','desc')
            ->limit(20)->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
            if (count($place)===0) {
              $y=''.$x[sizeof($x)-5].' '.$x[sizeof($x)-4].' '.$x[sizeof($x)-3].' '.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
                $place = DB::connection('sqlite')->table('places_3')
                 ->where('new_address','Like','%'.$y.'%')
                 ->orWhere('alternate_address','Like','%'.$y.'%')
                 ->orderBy('id','desc')
                 ->limit(20)->get(['id','Address','new_address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
              if(count($place)>=0 || count($place)>=20) {
                $q = preg_replace("/[\/,-]/", " ", $q);
                $res = $tnt->searchBoolean($q,20);
                if (count($res['ids'])>0) {

                  $place = DB::table('places_3')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get();
                  return response()->json(['places' => $place]);
                }else {
                  return response()->json(['Message'=>'Not found']);
                }

          }
            }

       }

     }
    }
   }
     DB::table('analytics')->increment('search_count',1);
     return response()->json(['string' => $y,'places'=>$place ]);

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

  public function GeoCode($id)
  {
    $place = DB::table('places')->where('id',$id)->orWhere('uCode',$id)->get(['Address','area','city','postCode','uCode','longitude','latitude','pType','subType','contact_person_phone','updated_at']);

    if (count($place)>0) {
      $address = $place[0]->Address;
      $message = ''.$address.' was selected from last search query';
      $this->SlackSearch($message);
      return response()->json($place);
    }else {
      return response()->json([
        'message' => 'not found',
        'status'  => '200',
      ]);
    }
  }

  public function TestFuzzySearch(Request $request)
  {

        // input misspelled word
        $input = $request->data;

        // array of words to check against
        $words  = array('Monipur','Mirpur','Gulshan','Basundhara');
        // no shortest distance found, yet
        $shortest = -1;

        // loop through words to find the closest
        foreach ($words as $word) {

            // calculate the distance between the input word,
            // and the current word
            $lev = levenshtein($input, $word);

            // check for an exact match
            if ($lev == 0) {

                // closest word is this one (exact match)
                $closest = $word;
                $shortest = 0;

                // break out of the loop; we've found an exact match
                break;
            }

            // if this distance is less than the next found shortest
            // distance, OR if a next shortest word has not yet been found
            if ($lev <= $shortest || $shortest < 0) {
                // set the closest match, and shortest distance
                $closest  = $word;
                $shortest = $lev;
            }
        }

        echo "Input word: $input\n";
        if ($shortest == 0) {
            return response()->json(["Result"=> $closest]);
        } else {

            return response()->json(["Result"=> "Did you mean: $closest"]);
        }



            /*  $input= $request->data;
              $a=explode(" ",$input);
              $words  = array('Mirpur','Block','House','Dhaka','Jafrabad');


              $b=array();
              //for ($i=0;$i<=count($a);$i++)
              foreach ($a as $input)
              {
              $shortest = -1;

                foreach ($words as $key ) {
                $lev = levenshtein($input, $key);

                if ($lev == 0) {
                    $closest = $key;
                    $shortest = 0;

                 }


                if ($lev <= $shortest OR $shortest < 0) {

                    $closest  = $key;
                    $shortest = $lev;
                      //echo $closest;
                      //$b[]=$closest;

                 }



              }
              $b[]=$closest;
              }

              return implode(" ",$b);

              //print_r($b);
*/

  }// end of function


   public function isExactMatch($request, $result)
   {
       return strtolower($request->search) == strtolower($result->Address);
   }

   public function SlackSearch($message)
   {
     $channel = 'searchqueries';
     $data = array(
       'channel'     => $channel,
       'username'    => 'tayef',
       'text'        => $message

     );
    // sleep(2);
     //Slack Webhook : notify
     define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
     // Make your message
     $message_string = array('payload' => json_encode($data));
     //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
     // Use curl to send your message
     $c = curl_init(SLACK_WEBHOOK);
     curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
     curl_setopt($c, CURLOPT_POST, true);
     curl_setopt($c, CURLOPT_POSTFIELDS, $message_string);
     curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
     $res = curl_exec($c);
     curl_close($c);
   }


}
