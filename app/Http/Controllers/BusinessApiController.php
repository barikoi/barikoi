<?php

namespace App\Http\Controllers;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Place;
use App\User;
use App\Token;
use App\PlaceType;
use App\PlaceSubType;
use App\analytics;
use App\SavedPlace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Support\Facades\Mail;
use DB;
use TeamTNT\TNTSearch\TNTSearch;
use Auth;

use Illuminate\Support\Facades\Hash;


class BusinessApiController extends Controller
{
  public function RegisterBusinessUser(Request $request){

    $this->validate($request, [
      'name' => 'required',
      'email' => 'required|email|max:255',
      'password' => 'required|min:6',
      'userType'=>'required',
    ]);

    $user = new User;
    $user->name = $request->name;
    $user->email = $request->email;
    $user->password = app('hash')->make($request->password);
    // $hashed_random_password = Hash::make(str_random(8));
    //$user->password =$hashed_random_password;
    $user->userType=$request->userType; //1=admin,2=users,3=business
    $user->save();


    //  return response()->json('Welcome');
    return new JsonResponse([
      'message' => 'Welcome'
    ]);
  }


  public function generateApiKey(Request $request) {


    $userId = $request->user()->id;
    $bEmail=$request->user()->email;
    $isUser = User::where('email','=',$bEmail)->first();
    if(is_null($isUser)){
      return new JsonResponse([
        'message' => 'Could not find any user with this email'
      ]);
    }
    else{

      $bUid=$isUser->id; //Get The User ID

      //if there is any previous active for this userId , revoke them
      $prvsKeys=Token::where('user_id','=',$bUid)->where('isActive',1)->update(['isActive' => 0]);

      //start generating Key:
      $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
      $charactersLength = strlen($characters);
      $apiKey = '';
      for ($i = 0; $i < 10; $i++) {
        $apiKey .= $characters[rand(0, $charactersLength - 1)];
      }
      $toEncode= $bUid.':'.$apiKey;
      $newApiKey=new Token;
      $newApiKey->user_id=$bUid;
      $newApiKey->key=base64_encode($toEncode); //in future we won't keep any key in our DB
      $newApiKey->randomSecret=$apiKey;
      $newApiKey->isActive=1;

      $newApiKey->save();// Save The New KEY for this User ID
      Mail::send('Email.bkeygenerated', ['key' => base64_encode($toEncode)], function($message) use($bEmail)
      {
        $message->to($bEmail)->subject('Password Reset!');
      });
      //DB::table('tokens')->where('user_id','=',$userId)->increment('post_count',1);

      $message = "New API KEY GENERATED,id:".$userId." , Email:".$bEmail." ";
      $channel = 'newregistration';
      $data = array(
        'channel'     => $channel,
        'username'    => 'tayef',
        'text'        => $message

      );
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

      return new JsonResponse([
        'message' => 'Key Generated!',
        'key'=>base64_encode($toEncode),
      ]);
    }
  }
  //PBDYI2LC4O
  public function addPlaceByBusinessUser(Request $request,$apikey)
  {
    /* $credentials = base64_decode(
    Str::substr($request->header('Authorization'),6)
  );*/
  //return $credentials;
  $key = base64_decode($apikey);
  $bIdAndKey = explode(':', $key);
  $bUser=$bIdAndKey[0];
  $bKey=$bIdAndKey[1];
  if(Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()){
    //return "Valid";

    //Random Code_character part
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomStringChar = '';
    for ($i = 0; $i < 4; $i++) {
      $randomStringChar  .= $characters[rand(0, $charactersLength - 1)];
    }
    //Random Code_digit part
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomStringDig = '';
    for ($i = 0; $i < 4; $i++) {
      $randomStringDig .= $characters[rand(0, $charactersLength - 1)];
    }
    $ucode =  ''.$randomStringChar.''.$randomStringDig.'';

    //Start Storing/Adding Process
    $input = new Place;

    $input->longitude = $request->longitude;
    $input->latitude = $request->latitude;
    $input->Address = $request->Address;
    $input->city = $request->city;
    $input->area = $request->area;
    $input->postCode = $request->postCode;
    $input->pType = $request->pType;
    $input->subType = $request->subType;
    $input->user_id =$bUser;
    //longitude,latitude,Address,city,area,postCode,pType,subType,flag,device_ID,user_id,email
    /*      if($request->has('flag'))
    {
    $input->flag = $request->flag;
    if ($request->flag==1) {
    DB::table('analytics')->increment('public_count');
  }else{
  DB::table('analytics')->increment('private_count');
}
}*/
$request->flag=$request->flag;

if ($request->has('device_ID')) {
  $input->device_ID = $request->device_ID;
}

if ($request->has('email')) {
  $input->email = $request->email;
}

$input->uCode = $ucode;
$input->save();

DB::table('analytics')->increment('code_count');
DB::table('analytics')->increment('business_code_count');
///DB::table('tokens')->where('user_id','=',$userId)->increment('post_count',1);
return response()->json($ucode);
}
else{
  return new JsonResponse([
    'message' => 'Invalid or No Regsitered Key',
  ]);
}
//  return $key;
/*  return new JsonResponse([
'User_ID' => $bUser,
'KEY' =>$bKey,
]);*/
}



public function PlacesAddedByBusinessUser($apikey){
  $key = base64_decode($apikey);
  $bIdAndKey = explode(':', $key);
  $bUser=$bIdAndKey[0];
  $bKey=$bIdAndKey[1];

  if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
    # code...
    $place = Place::where('user_id','=',$bUser)->get();
    //DB::table('token')->where('user_id','=',$userId)->increment('get_count',1);
    //DB::table('analytics')->increment('business_search_count',1);
    return $place->toJson();
  }
  else{
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }
}

public function UpdatePlaceByBusinessUser($apikey,$id){
  $key = base64_decode($apikey);
  $bIdAndKey = explode(':', $key);
  $bUser=$bIdAndKey[0];
  $bKey=$bIdAndKey[1];

  if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
    $places = Place::where('id','=',$id)->first();
    if ($request->has('longitude')) {
      $places->longitude = $request->longitude;
    }
    if ($request->has('latitude')) {
      $places->latitude = $request->latitude;
    }
    $places->Address = $request->Address;
    $places->city = $request->city;
    $places->area = $request->area;
    $places->user_id = $bUser;
    $places->postCode = $request->postCode;
    $places->flag = 1;
    $places->save();
    //  $splaces = SavedPlace::where('pid','=',$id)->update(['Address'=> $request->Address]);
    // DB::table('tokens')->where('user_id','=',$userId)->increment('get_count',1);

    return response()->json('updated');
  }
  else{
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }
}
//Number of Keys byuser, and Current Active Key
public function getCurrentActiveKey(){

  $user = JWTAuth::parseToken()->authenticate();
  $userId = $user->id;
  if (Token::where('user_id','=',$userId)->exists()) {
    $keysByUser= Token::where('user_id','=',$userId)->get();
    $numberOfKeysByUser=$keysByUser->count();
    $activeSecret= Token::where('user_id','=',$userId)->where('isActive',1)->select('randomSecret')->first();

    // $theSecret=$activeSecret['key'];
    $toBeEncoded= $userId.':'.$activeSecret['randomSecret'];
    // DB::table('tokens')->where('user_id','=',$userId)->where('isActive',1)->increment('get_count',1);
    //$numberOfKeysByUser=$keysByUser->count();
    return new JsonResponse([
      'message' => 'Total Keys:'.$numberOfKeysByUser,
      'current_active_key'=>base64_encode($toBeEncoded)
    ]);
  }
  else{
    return new JsonResponse([
      'message' => 'Unable to find the User or Invalid or No Regsitered Key',
    ]);
  }
}


public function AddBusinessDescription(Request $request,$pid){
  $user = JWTAuth::parseToken()->authenticate();
  $userId = $user->id;
  $requestedUserId=User::where('id','=',$userId)->select('userType')->first();
  $rUid=$requestedUserId->userType;

  //return $rUid;
  if($rUid==3){
    //return $rUid;
    $b_detils = new BusinessDetails;
    $b_detils->business_pid=$pid;
    $b_detils->business_description=$request->description;
    $b_detils->save();
    return new JsonResponse([
      'message'=>'Description Added',
    ]);
  }else{
    return new JsonResponse([
      'message'=>'Could not find the User',
    ]);
  }
}

public function ShowBusinessDescription($pid){

  $getDescription=BusinessDetails::where('business_pid','=',$pid)->get();
  return $getDescription->toJson();
}
//@@Developer Token analysis
public function TokenAnalysis(Request $request)
{
  $details = Token::where('user_id',$request->user()->id)
  ->where('isActive',1)->get();

  return $details->toJson();
}


public function DeveloperAutoCompleteX($apikey,Request $request)
{
  $key = base64_decode($apikey);
  $bIdAndKey = explode(':', $key);
  $bUser=$bIdAndKey[0];
  $bKey=$bIdAndKey[1];

  if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
    $cap=DB::table('tokens')->select('autocomplete_count','autocomplete_cap')->where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->first();
    $autocomplete_cap = $cap->autocomplete_cap;
    $count = $cap->autocomplete_count;
    if ((int)$count>=$autocomplete_cap) {
      return response()->json(['Message'=> 'You have reached your monthly limit, Please contact Sales']);

    }else{

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


      $q = $request->q;

      if (empty($q)) {
        return response()->json(['places' => 'Empty Query']);
      }
      $y = '';
      DB::table('Searchlytics')->insert(['query' => $q]);
      if(Place::where('uCode','=',$q)->exists()){
        $place=Place::with('images')->where('uCode','=',$q)->get(['id','Address','uCode']);
      }
      else{
        $place = DB::connection('sqlite')->table('places_3')

        ->where('new_address','Like','%'.$q.'%')
        ->orWhere('alternate_address','Like','%'.$q.'%')
        ->limit(20)->get(['id','Address','uCode']);
        if (count($place)===0) {
          $q = preg_replace("/[-]/", " ", $q);
          $q = preg_replace('/\s+/', ' ',$q);
          $str = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
          $x = explode(" ",$str);
          $size = sizeof($x);
          $place = DB::connection('sqlite')->table('places_3')
          ->where('new_address','Like','%'.$str.'%')
          ->orWhere('alternate_address','Like','%'.$str.'%')
          ->limit(20)->get(['id','Address','uCode']);
          if (count($place)===0) {
            // if string size is less then equal to 5 words
            if ($size <=5) {
              $y=''.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
              $place = DB::connection('sqlite')->table('places_3')
              ->where('new_address','Like','%'.$y.'%')
              ->orWhere('alternate_address','Like','%'.$y.'%')
              ->limit(20)->get(['id','Address','uCode']);
              if (count($place)===0) {
                $y=''.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
                $place = DB::connection('sqlite')->table('places_3')
                ->where('flag',1)
                ->where('new_address','Like','%'.$y.'%')
                ->orWhere('alternate_address','Like','%'.$y.'%')
                ->limit(20)->get(['id','Address','uCode']);
                if(count($place)>=0 || count($place)>=20) {
                  $res = $tnt->searchBoolean($q,20);
                  $place = DB::table('places_3')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','uCode']);

                }
              }
            }

            if ($size>=6)
            {
              $y=''.$x[sizeof($x)-5].' '.$x[sizeof($x)-4].' '.$x[sizeof($x)-3].' '.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
              $place = DB::connection('sqlite')->table('places_3')
              ->where('new_address','Like','%'.$y.'%')
              ->orWhere('alternate_address','Like','%'.$y.'%')
              ->limit(20)->get(['id','Address','uCode']);
              if(count($place)===0) {
                $y=''.$x[sizeof($x)-5].' '.$x[sizeof($x)-4].' '.$x[sizeof($x)-3].' '.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
                $place = DB::connection('sqlite')->table('places_3')
                ->where('new_address','Like','%'.$y.'%')
                ->orWhere('alternate_address','Like','%'.$y.'%')
                ->limit(20)->get(['id','Address','uCode']);
                if(count($place)>=0 || count($place)>=20) {
                  $res = $tnt->searchBoolean($q,20);
                  $place = DB::table('places_3')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','uCode']);

                }
              }

            }

          }
        }
      }
      DB::table('analytics')->increment('search_count',1);
      DB::table('analytics')->increment('business_search_count',1);
      DB::table('tokens')->where('user_id','=',$bUser)->increment('autocomplete_count',1);
      return response()->json(['places'=>$place ]);


    }
  }
  else{
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }

}

public function DeveloperAutoComplete($apikey,Request $request)
{

  if ($request->has('q') || $request->q==='is_null'){

    $key = base64_decode($apikey);
    $key =str_replace('%20', '', $key);
    if (strpos($key, ':') !== false || Token::where('key','=',$apikey)->where('isActive',1)->exists()) {
      $bIdAndKey = explode(':', $key);
    }else {
      return new JsonResponse([
        'message' => 'Invalid or No Regsitered Key',
        'status' => '200',
      ]);
    }

    $bUser=$bIdAndKey[0];
    $bKey=$bIdAndKey[1];

    if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
      $cap=DB::table('tokens')->select('autocomplete_count','autocomplete_cap')->where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->first();
      $autocomplete_cap = $cap->autocomplete_cap;
      $count = $cap->autocomplete_count;
      if ((int)$count>=$autocomplete_cap) {
        return response()->json(['Message'=> 'You have reached your monthly limit, Please contact Sales']);

      }
      else{

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


        $q = $request->q;
        $place = DB::connection('sqlite')->table('places_3')
        ->where('new_address','Like','%'.$q.'%')
        ->orWhere('alternate_address','Like','%'.$q.'%')
        //->orWhere('uCode','=',$q)
        ->limit(10)->get(['id','Address','uCode','Area']);
        if (count($place)>0) {
          DB::table('analytics')->increment('search_count',1);
          DB::table('analytics')->increment('business_search_count',1);
          DB::table('tokens')->where('user_id','=',$bUser)->increment('autocomplete_count',1);
          return response()->json(['places'=>$place ]);
        }else {
          $q = preg_replace("/[-]/", " ", $q);
          $q = preg_replace('/\s+/', ' ',$q);
          $str = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
          $x = explode(" ",$str);
          $size = sizeof($x);
          $place = DB::connection('sqlite')->table('places_3')
          ->where('new_address','Like','%'.$str.'%')
          ->orWhere('alternate_address','Like','%'.$str.'%')
          // ->orderBy('id','desc')
          ->limit(10)->get(['id','Address','uCode','Area']);
          if (count($place)>0) {
            DB::table('analytics')->increment('search_count',1);
            DB::table('analytics')->increment('business_search_count',1);
            DB::table('tokens')->where('user_id','=',$bUser)->increment('autocomplete_count',1);
            return response()->json(['places'=>$place ]);
          }else {
            $res = $tnt->searchBoolean($request->q,10);
            if (count($res['ids'])>0) {
              $place = DB::table('places_3')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','uCode','Area']);
              DB::table('analytics')->increment('search_count',1);
              DB::table('analytics')->increment('business_search_count',1);
              DB::table('tokens')->where('user_id','=',$bUser)->increment('autocomplete_count',1);
              return response()->json(['places' => $place]);
            }else{
              return response()->json(
                [
                  'message' => 'not found',
                  'status' => '200',
                ]
              );
            }
          }
        }

      }

    }
    else{
      return new JsonResponse([
        'message' => 'Invalid or No Regsitered Key',
        'status' => '200',
      ]);
    }
  }else {
    return new JsonResponse([
      'message' => 'Empty Request! Please Give Valid Data for searching',
      'status' => '200',
    ]);
  }

}


/*
@@Geocode
*/
public function geocode($apikey,$id)
{
  $key = base64_decode($apikey);
  $key =str_replace('%20', '', $key);
  if (strpos($key, ':') !== false || Token::where('key','=',$apikey)->where('isActive',1)->exists()) {
    $bIdAndKey = explode(':', $key);
  }else {
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }
  $bUser=$bIdAndKey[0];
  $bKey=$bIdAndKey[1];

  if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
    $cap=DB::table('tokens')->select('geo_code_count','geo_code_cap')->where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->first();
    $geo_cap = $cap->geo_code_cap;
    $count = $cap->geo_code_count;
    if ((int)$count>=$geo_cap) {
      return response()->json(['Message'=> 'You have reached your monthly limit, Please contact Sales']);

    }
    else {

      //  if (is_int($id)) {

      $place = Place::with('images')->where('id',$id)->orWhere('uCode',$id)->get(['Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
      DB::table('tokens')->where('user_id','=',$bUser)->increment('geo_code_count',10);
      // decrease count in autocomplete count
      if (count($place)>0) {
        return $place->toJson();
      }else {
        return response()->json([
          'message' => 'not found',
          'status'  => '200',
        ]);
      }


      /* }else {
      return new JsonResponse([
      'message' => 'ID must be Integer',
    ]);
  }*/
}
}
else{
  return new JsonResponse([
    'message' => 'Invalid or No Regsitered Key',
  ]);
}
}
/*
@@reverse geocode
*/
public function reverseGeocodeNew($apikey,Request $request)
{
  $key = base64_decode($apikey);
  $key =str_replace('%20', '', $key);
  if (strpos($key, ':') !== false || Token::where('key','=',$apikey)->where('isActive',1)->exists()) {
    $bIdAndKey = explode(':', $key);
  }else {
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }
  $bUser=$bIdAndKey[0];
  $bKey=$bIdAndKey[1];

  if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
    $cap=DB::table('tokens')->select('reverse_geo_code_count','reverse_geo_code_cap')->where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->first();
    $reverse_cap = $cap->reverse_geo_code_cap;
    $count = $cap->reverse_geo_code_count;
    //  $cap = (int)$cap;
    if ((int)$count>=$reverse_cap) {
      return response()->json(['Message'=> 'You have reached your monthly limit, Please contact Sales']);
    }
    else {
      if ($request->has('longitude') && $request->has('latitude')) {


        $lat = $request->latitude;
        $lon = $request->longitude;

        if(preg_match("/[a-z]/i", $lat ) || preg_match("/[a-z]/i", $lon )){
          return response()->json([
            'message' => 'wrong parameters',
            'status' => '200',
          ]);
        }

        $distance = 0.1;
        //$result = DB::select("SELECT id, slc($lat, $lon, y(location), x(location))*10000 AS distance_in_meters, Address,area,longitude,latitude,pType,subType, astext(location) FROM places_2 WHERE MBRContains(envelope(linestring(point(($lat+(0.2/111)), ($lon+(0.2/111))), point(($lat-(0.2/111)),( $lon-(0.2/111))))), location) order by distance_in_meters LIMIT 1");
        $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_within_meters,Address,area,city
        FROM places
        WHERE ST_Contains( ST_MakeEnvelope(
          Point(($lon+($distance/111)), ($lat+($distance/111))),
          Point(($lon-($distance/111)), ($lat-($distance/111)))
        ), location )
        ORDER BY distance_within_meters LIMIT 1");

        DB::table('tokens')->where('user_id','=',$bUser)->increment('reverse_geo_code_count',1);
        // decrease count in autocomplete count
        if (count($result)>0) {
          return response()->json(['Place' => $result]);
        }else {
          return response()->json([
            'message' => 'not found',
            'status' => '200',
          ]);
        }

      }
      else {
        return new JsonResponse([
          'message' => 'parameters missing',
        ]);
      }
    }

  }
  else{
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }
}

public function reverseNearBy(Request $request, $apikey,$distance=0.5, $limit=15)
{
  $key = base64_decode($apikey);
  $key =str_replace('%20', '', $key);
  if (strpos($key, ':') !== false || Token::where('key','=',$apikey)->where('isActive',1)->exists()) {
    $bIdAndKey = explode(':', $key);
  }else {
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }
  $bUser=$bIdAndKey[0];
  $bKey=$bIdAndKey[1];
  if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
    if ($request->has('longitude') && $request->has('latitude')) {

      $lat = $request->latitude;
      $lon = $request->longitude;
      if(preg_match("/[a-z]/i", $lat ) || preg_match("/[a-z]/i", $lon )){
        return response()->json([
          'message' => 'wrong parameters',
          'status' => '200',
        ]);
      }
      //$distance = 0.5;
      $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters,longitude,latitude,pType,Address,area,city,postCode,subType,uCode, ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lon+($distance/111)), ($lat+($distance/111))),
        Point(($lon-($distance/111)), ($lat-($distance/111)))
      ), location )
      ORDER BY distance_in_meters LIMIT $limit");

      if (count($result)>0) {
        return response()->json(['Place' => $result]);
      }else {
        return response()->json([
          'message' => 'not found',
          'status' => '200',
        ]);
      }
    }
    else {
      return new JsonResponse([
        'message' => 'parameters missing',
      ]);
    }
  }

  else{
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }
}

public function nearbyCatagorized(Request $request, $apikey,$distance=0.5, $limit=15)
{
  $key = base64_decode($apikey);
  $bIdAndKey = explode(':', $key);
  $bUser=$bIdAndKey[0];
  $bKey=$bIdAndKey[1];
  if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
    if ($request->has('longitude') && $request->has('latitude')) {
      $lat = $request->latitude;
      $lon = $request->longitude;
      $distance = 1.5;
      if ($request->has('ptype')) {
        $ptype= $request->ptype;
        $result = $this->rectangularSearch($lon,$lat,$ptype,$limit,$distance);/*DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, uCode,contact_person_phone,ST_AsText(location)
        FROM places
        WHERE ST_Contains( ST_MakeEnvelope(
          Point(($lon+($distance/111)), ($lat+($distance/111))),
          Point(($lon-($distance/111)), ($lat-($distance/111)))
        ), location ) AND ( pType LIKE '%$request->ptype%')
        ORDER BY distance_in_meters
        LIMIT $limit");*/
      }else {
        $result = $this->rectangularSearch($lon,$lat,$ptype,$limit,$distance);/*DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, uCode,contact_person_phone,ST_AsText(location)
        FROM places
        WHERE ST_Contains( ST_MakeEnvelope(
          Point(($lon+($distance/111)), ($lat+($distance/111))),
          Point(($lon-($distance/111)), ($lat-($distance/111)))
        ), location ) AND (subType LIKE '%$request->ptype%')
        ORDER BY distance_in_meters
        LIMIT $limit");*/
      }
      if (count($result)>0) {
        return response()->json(['Place' => $result]);
      }else {
        return response()->json([
          'message' => 'not found',
          'status' => '200',
        ]);
      }
    }
    else {
      return new JsonResponse([
        'message' => 'parameters missing',
      ]);
    }

  }
  else{
    return new JsonResponse([
      'message' => 'Invalid or No Regsitered Key',
    ]);
  }
}
public function getAreaDataPolygonWise(Request $request)
{
  if ($request->has('pType')) {
    $subtype = $request->pType;
  }else {
    $subtype = 'Shop';
  }
  if ($request->has('area')) {
    $area = $request->area;
  }
  else {
    $area = 'Baridhara DOHS';
  }
  if ($subtype=='all') {
    $places = DB::select("SELECT id, Address, area, subType, pType, longitude,latitude, uCode,user_id,created_at,updated_at,astext(location) FROM places WHERE st_within(location,(select area from Area where name='$area') ) LIMIT 10");
  }
  else {
    $places = DB::select("SELECT id, Address, area, subType, pType, longitude,latitude, uCode,user_id,created_at,updated_at,astext(location) FROM places WHERE st_within(location,(select area from Area where name='$area') ) and pType LIKE '%$subtype%' LIMIT 10");


  }
      return response()->json([
          'Total' => count($places),
          'places'=> $places
        ]);

}
public function rectangularSearch($lon,$lat,$data, $limit=20,$distance = 1.5)
{

  $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, uCode,contact_person_phone,ST_AsText(location)
  FROM places
  WHERE ST_Contains( ST_MakeEnvelope(
    Point(($lon+($distance/111)), ($lat+($distance/111))),
    Point(($lon-($distance/111)), ($lat-($distance/111)))
  ), location ) AND (pType LIKE '%$data%')
  ORDER BY distance_in_meters
  LIMIT $limit");
  return $result;
}


  //Show API ANALYTICS ----------------------********----------------------

  public function totalApiUser()
  {
    //$api = Token::where('isActive',1)->count();
    $api_usage = Token::sum('reverse_geo_code_count');

    return response()->json(['Api_usage' => $api_usage]);
  }



  //END API ANALYTICS


}
