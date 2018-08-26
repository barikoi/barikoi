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
        $isUser = User::where('email','=',$bEmail)->where('userType',3)->first();
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

  public function searchPlaceByBusinessUser($apikey, $code){
     $key = base64_decode($apikey);
     $bIdAndKey = explode(':', $key);
     $bUser=$bIdAndKey[0];
     $bKey=$bIdAndKey[1];

     if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
       # code...
      $place = $this->DeveloperSearch($code);
      DB::table('analytics')->increment('business_search_count',1);

      DB::table('tokens')->where('user_id','=',$bUser)->increment('get_count',1);
      // increase autocomplete count
      DB::table('tokens')->where('user_id','=',$bUser)->increment('autocomplete_count',1);
      // decrease count in autocomplete count
      DB::table('tokens')->where('user_id','=',$bUser)->decrement('autocomplete_cap',1);
      return response()->json(['places'=>$place]);
     }
     else{
            return new JsonResponse([
              'message' => 'Invalid or No Regsitered Key',
          ]);
     }
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
    ->where('isActive',1)->get(['reverse_geo_code_count','geo_code_count','autocomplete_count']);

    return $details->toJson();
  }

  public function DeveloperSearch(Request $request)
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
   $tnt->asYouType = true;


    $q = $reuqest->q;

   DB::table('Searchlytics')->insert(['query' => $q]);
   if(Place::where('uCode','=',$q)->exists()){
      $place=Place::with('images')->where('uCode','=',$q)->get();
    }
    else{
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

    if ($size >=6)
    {
      $y=''.$x[sizeof($x)-4].' '.$x[sizeof($x)-3].' '.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
        $place = DB::connection('sqlite')->table('places_3')
         ->select('*')
         ->where('new_address','Like','%'.$y.'%')
         ->orWhere('alternate_address','Like','%'.$y.'%')
         ->limit(20)->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
         if(count($place)>=20) {
           $res = $tnt->searchBoolean($q,20);
           $place = Place::with('images')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get();

     }
    }
     if ($size <= 4) {
       $y=''.$x[sizeof($x)-1].'';
       $place = DB::connection('sqlite')->table('places_3')
          ->select('*')
          ->where('new_address','Like','%'.$y.'%')
          ->orWhere('alternate_address','Like','%'.$y.'%')
          ->limit(20)->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
         if(count($place)>=0 || count($place)>=20) {
          $res = $tnt->searchBoolean($q,20);
          $place = Place::with('images')->whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get();

        }
      }
    }
  }
    DB::table('analytics')->increment('search_count',1);
    return $place;

  }

  public function DeveloperAutoComplete($apikey,Request $request)
  {
    $key = base64_decode($apikey);
    $bIdAndKey = explode(':', $key);
    $bUser=$bIdAndKey[0];
    $bKey=$bIdAndKey[1];

    if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {

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

     DB::table('Searchlytics')->insert(['query' => $q]);
     // increase autocomplete count
     DB::table('tokens')->where('user_id','=',$bUser)->increment('autocomplete_count',1);
     if(Place::where('uCode','=',$q)->exists()){
        $place=Place::with('images')->where('uCode','=',$q)->get();
      }
      else{
        $q = preg_replace("/[-]/", " ", $q);
        $q = preg_replace('/\s+/', ' ',$q);
        $y = '';
        $str = preg_replace("/[^A-Za-z0-9\s]/", "",$q);
        $x = explode(" ",$str);
        $size = sizeof($x);
        $place = DB::connection('sqlite')->table('places_3')
        ->where('new_address','Like','%'.$str.'%')
        ->orWhere('alternate_address','Like','%'.$str.'%')
        ->limit(20)->get(['id','Address','uCode','pType','updated_at']);

      if (count($place)==0) {

        if ($size >=6)
        {
          $y=''.$x[sizeof($x)-4].' '.$x[sizeof($x)-3].' '.$x[sizeof($x)-2].' '.$x[sizeof($x)-1].'';
            $place = DB::connection('sqlite')->table('places_3')
             ->select('*')
             ->where('new_address','Like','%'.$y.'%')
             ->orWhere('alternate_address','Like','%'.$y.'%')
             ->limit(20)->get(['id','Address','uCode','pType','updated_at']);
             if(ount($place)>=0 || count($place)>=20) {
               $res = $tnt->searchBoolean($q,20);
               $place = Place::whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get(['id','Address','uCode','pType','updated_at']);

         }
       }
       if ($size <= 4) {
         $y=''.$x[sizeof($x)-1].'';
         $place = DB::connection('sqlite')->table('places_3')
            ->select('*')
            ->where('new_address','Like','%'.$y.'%')
            ->orWhere('alternate_address','Like','%'.$y.'%')
            ->limit(20)->get(['id','Address','uCode','pType','updated_at']);
           if(count($place)>=0 || count($place)>=20) {
            $res = $tnt->searchBoolean($q,20);
            $place = Place::whereIn('id', $res['ids'])->orderByRaw(DB::raw("FIELD(id, ".implode(',' ,$res['ids']).")"))->get([['id','Address','uCode','pType','updated_at']]);

          }
        }
      }
    }
     DB::table('analytics')->increment('search_count',1);
     DB::table('analytics')->increment('business_search_count',1);
     DB::table('tokens')->where('user_id','=',$bUser)->increment('get_count',1);
     // decrease count in autocomplete count
     DB::table('tokens')->where('user_id','=',$bUser)->decrement('autocomplete_cap',1);
     return response()->json(['places'=>$place]);
  }
  else{
           return new JsonResponse([
             'message' => 'Invalid or No Regsitered Key',
         ]);
    }

  }

  public function geocode($apikey,$id)
  {
    $key = base64_decode($apikey);
    $bIdAndKey = explode(':', $key);
    $bUser=$bIdAndKey[0];
    $bKey=$bIdAndKey[1];

    if (Token::where('user_id','=',$bUser)->where('randomSecret','=',$bKey)->where('isActive',1)->exists()) {
      if (is_int($id)) {

          $place = Place::with('images')->where('id',$id)->get(['id','Address','area','city','postCode','uCode','route_description','longitude','latitude','pType','subType','updated_at']);
          DB::table('tokens')->where('user_id','=',$bUser)->increment('geo_code_count',10);
          // decrease count in autocomplete count
          DB::table('tokens')->where('user_id','=',$bUser)->decrement('geo_code_cap',10);
          return $place->toJson();

   }else {
     return new JsonResponse([
        'message' => 'ID must be Integer',
    ]);
   }

  }
  else{
          return new JsonResponse([
             'message' => 'Invalid or No Regsitered Key',
         ]);
    }
  }

}
