<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;
use Excel;
use Auth;
use App\User;
use App\Area;
use App\Place;
use App\NewPlace;
use App\StructuredPlace;
use App\PlaceType;
use App\PlaceSubType;
use App\SavedPlace;
use App\Referral;
use App\analytics;
use App\Image;
use App\tags;
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
use TeamTNT\TNTSearch\TNTGeoSearch;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use TeamTNT\TNTSearch\TNTSearch;

class PlaceController extends Controller
{
      //


      // generate strings
      public function generateRandomString($length = 10) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
          $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
      }
      //generate numbers
      public function generateRandomNumber($length = 10) {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
          $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
      }

    //Mapper
    public function XauthAddNewPlace(Request $request){
      $yesterday = Carbon::yesterday()->toDateTimeString();
      $user = JWTAuth::parseToken()->authenticate();
      $userId = $user->id;
      $isAllowed = $user->isAllowed;

      $subType = rtrim($request->subType,",");
      if ($isAllowed===0) {

        $randomStringChar=$this->generateRandomString(4);
        //number part
        $charactersNum = '0123456789';
        $charactersNumLength = strlen($charactersNum);
        $randomStringNum = '';
        for ($i = 0; $i < 4; $i++) {
          $randomStringNum .= $charactersNum[rand(0, $charactersNumLength - 1)];
        }

        $ucode =  ''.$randomStringChar.''.$randomStringNum.'';

        $lat = $request->latitude;
        $lon = $request->longitude;
        //	$location = ''.$lon.' '.$lat.'';
        $input = new Place;
        $input->longitude = $lon;
        $input->latitude = $lat;
        $input->Address = title_case(ltrim($request->Address," "));
        $input->city = title_case($request->city);
        $input->area = title_case(trim($request->area," "));
        $input->postCode = $request->postCode;
        $input->pType = $request->pType;
        $input->subType = $subType;
        //longitude,latitude,Address,city,area,postCode,pType,subType,flag,device_ID,user_id,email
        // if($request->has('flag'))
        // {
        //
        //
        // }
          $input->flag = 1;
        if($request->has('device_ID')) {
          $input->device_ID = $request->device_ID;
        }

        //ADN:when authenticated , user_id from client will be passed on this var.
        $input->user_id =$userId;

        if ($request->has('email')){
          $input->email = $request->email;
        }
        if ($request->has('route_description')){
          $input->route_description = $request->route_description;
        }
        //$img1=empty($request->input('images'));
        // if ($request->hasFile('images')) {
        //     dd('write code here');
        //}
        if ($request->has('road_details')){
          $input->road_details = $request->road_details;
        }
        if ($request->has('number_of_floors')){
          $input->number_of_floors = $request->number_of_floors;
        }
        if ($request->has('contact_person_name')){
          $input->contact_person_name = $request->contact_person_name;
        }
        if ($request->has('contact_person_phone')){
          $input->contact_person_phone = $request->contact_person_phone;
        }
        $input->uCode = $ucode;
        $input->isRewarded = 1;
        if ($request->has('tags')){
          $tag = $this->insertTags($request->tags);
          $input->tags = $request->tags;

        }
        $point = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
        $input->location = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
        $input->bounds = DB::raw("ST_Buffer($point, 1/11111)");
        $input->save();

        $this->AddStructuredData($request,$ucode);
        //$placeId=$input->id;
        //if image is there, in post request
        $message1='no image file attached.';
        $imgflag=0;

        //handle image
        //user will get 5 points if uploads images
        $img_point=0; //inititate points for image upload

        if ($request->has('images'))
        {
          $placeId=$input->id; //get latest the places id
          $relatedTo=$request->relatedTo;
          $client_id = '55c393c2e121b9f';
          $url = 'https://api.imgur.com/3/image';
          $headers = array("Authorization: Client-ID $client_id");
          //source:
          //http://stackoverflow.com/questions/17269448/using-imgur-api-v3-to-upload-images-anonymously-using-php?rq=1
          $recivedFiles = $request->get('images');
          //$file_count = count($reciveFile);
          // start count how many uploaded
          $uploadcount = count($recivedFiles);
          //return $uploadcount;
          if($uploadcount>4){
            $message1="Can not Upload more then 4 files";
            $imgflag=0; //not uploaded
          }
          else{
            foreach($recivedFiles as $file)
            {
              //$img = file_get_contents($file);
              //$imgarray  = array('image' => base64_encode($file),'title'=> $title);
              $imgarray  = array('image' => $file);
              $curl = curl_init();
              curl_setopt_array($curl, array(
                CURLOPT_URL=> $url,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $imgarray
              ));
              $json_returned = curl_exec($curl); // blank response
              $json_a=json_decode($json_returned ,true);
              $theImageHash=$json_a['data']['id'];
              // $theImageTitle=$json_a['data']['title'];
              $theImageRemove=$json_a['data']['deletehash'];
              $theImageLink=$json_a['data']['link'];
              curl_close ($curl);

              //save image info in images table;
              $saveImage=new Image;
              $saveImage->user_id=$userId;
              $saveImage->pid=$placeId;
              $saveImage->imageGetHash=$theImageHash;
              //$saveImage->imageTitle=$theImageTitle;
              $saveImage->imageRemoveHash=$theImageRemove;
              $saveImage->imageLink=$theImageLink;
              $saveImage->relatedTo=$relatedTo;
              $saveImage->save();
              $uploadcount--;
            }
            $imgflag=1;
            $message1="Image Saved Successfully";
            $img_point=0;
          }//else end
        } //if reuest has image
        //Slack Webhook : notify

        //    define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
        define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
        // Make your message
        $getuserData=User::where('id','=',$userId)->select('name')->first();
        $name=$getuserData->name;
        $message = array('payload' => json_encode(array('text' => "'".$name."' Added a Place: '".title_case($request->Address)."' near '".$request->area.", ".$request->city."' area with Code:".$ucode." subType: ".$subType.", pType: ".$request->pType."")));
        //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
        // Use curl to send your message
        $c = curl_init(SLACK_WEBHOOK);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $message);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($c);
        curl_close($c);
        //return response()->json($ucode);

        //everything went weel, user gets add place points, return code and the point he recived
        //$new_address = ''.title_case(ltrim($request->Address," ")).', '.$request->area.''
        //$alternate_address = str_replace(array('.', ','), '' , $new_address);

        return response()->json([
          'uCode' => $ucode,
          //'img_flag' => $imgflag,
          //'new_total_points'=>$getTheNewTotal->total_points,
          'points'=>5,//+$img_poin,
          'image_uplod_messages'=>$message1
          // 'place'=>$placeId
        ]);

      }
      else {
        return response()->json(['message'=>'You are not approved. Please contact the office.']);
      }
    }

    public function authAddNewPlace(Request $request){

      $user = JWTAuth::parseToken()->authenticate();
      $userId = $user->id;

      $randomStringChar=$this->generateRandomString(4);
      //number part
      $charactersNum = '0123456789';
      $charactersNumLength = strlen($charactersNum);
      $randomStringNum = '';
      for ($i = 0; $i < 4; $i++) {
        $randomStringNum .= $charactersNum[rand(0, $charactersNumLength - 1)];
      }

      $ucode =  ''.$randomStringChar.''.$randomStringNum.'';

      $lat = $request->latitude;
      $lon = $request->longitude;

      $input = new Place;
      $input->longitude = $lon;
      $input->latitude = $lat;
      $input->Address = title_case($request->Address);
      $input->city = title_case($request->city);
      $input->area = trim($request->area," ");
      if ($request->has('postCode')) {
        $input->postCode = $request->postCode;
      }

      $input->pType = $request->pType;
      $input->subType = $request->subType;
      //longitude,latitude,Address,city,area,postCode,pType,subType,flag,device_ID,user_id,email
      if($request->has('flag'))
      {
        $input->flag = $request->flag;
        if ($request->flag==1) {
          DB::table('analytics')->increment('public_count');
        }else{
          DB::table('analytics')->increment('private_count');
        }
      }
      if($request->has('device_ID')) {
        $input->device_ID = $request->device_ID;
      }

      //ADN:when authenticated , user_id from client will be passed on this var.
      $input->user_id =$userId;

      if ($request->has('email')){
        $input->email = $request->email;
      }
      if ($request->has('route_description')){
        $input->route_description = $request->route_description;
      }
      if ($request->has('contact_person_name')){
        $input->contact_person_name = $request->contact_person_name;
      }
      if ($request->has('contact_person_phone')){
        $input->contact_person_phone = $request->contact_person_phone;
      }
      if ($request->has('tags')){
        $tag = $this->insertTags($request->tags);
        $input->tags = $tag;

      }
      $input->uCode = $ucode;
      $input->isRewarded = 1;
      $input->location = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
      $input->save();
      //$placeId=$input->id;
      //if image is there, in post request
      $message1='no image file attached.';
      $imgflag=0;

      //handle image
      //user will get 5 points if uploads images
      $img_point=0; //inititate points for image upload

      if ($request->has('images'))
      {
        $placeId=$input->id; //get latest the places id
        $relatedTo=$request->relatedTo;
        $client_id = '55c393c2e121b9f';
        $url = 'https://api.imgur.com/3/image';
        $headers = array("Authorization: Client-ID $client_id");
        //source:
        //http://stackoverflow.com/questions/17269448/using-imgur-api-v3-to-upload-images-anonymously-using-php?rq=1
        $recivedFiles = $request->get('images');
        //$file_count = count($reciveFile);
        // start count how many uploaded
        $uploadcount = count($recivedFiles);
        //return $uploadcount;
        if($uploadcount>1){
          $message1="Can not Upload more then 1 images at this moment";
          $imgflag=0; //not uploaded
        }
        else{
          foreach($recivedFiles as $file)
          {
            //$img = file_get_contents($file);
            //$imgarray  = array('image' => base64_encode($file),'title'=> $title);
            $imgarray  = array('image' => $file);
            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL=> $url,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_POST => 1,
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_HTTPHEADER => $headers,
              CURLOPT_POSTFIELDS => $imgarray
            ));
            $json_returned = curl_exec($curl); // blank response
            $json_a=json_decode($json_returned ,true);
            $theImageHash=$json_a['data']['id'];
            // $theImageTitle=$json_a['data']['title'];
            $theImageRemove=$json_a['data']['deletehash'];
            $theImageLink=$json_a['data']['link'];
            curl_close ($curl);

            //save image info in images table;
            $saveImage=new Image;
            $saveImage->user_id=$userId;
            $saveImage->pid=$placeId;
            $saveImage->imageGetHash=$theImageHash;
            //$saveImage->imageTitle=$theImageTitle;
            $saveImage->imageRemoveHash=$theImageRemove;
            $saveImage->imageLink=$theImageLink;
            $saveImage->relatedTo=$relatedTo;
            $saveImage->save();
            $uploadcount--;
          }
          $imgflag=1;
          $message1="Image Saved Successfully";
          $img_point=5;
        }//else end
      } //if reuest has image
      //Slack Webhook : notify

      //    define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
      // Make your message
      $getuserData=User::where('id','=',$userId)->select('name')->first();
      $name=$getuserData->name;
      $message = array('payload' => json_encode(array('text' => "'".$name."' Added a Place: '".$request->Address."' near '".$request->area.",".$request->city."' area with Code:".$ucode."")));
      //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
      // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);



      User::where('id','=',$userId)->increment('total_points',5+$img_point);
      $getTheNewTotal=User::where('id','=',$userId)->select('total_points')->first();

      DB::table('analytics')->increment('code_count');
      //return response()->json($ucode);

      //everything went weel, user gets add place points, return code and the point he recived
      return response()->json([
        'uCode' => $ucode,
        'img_flag' => $imgflag,
        'new_total_points'=>$getTheNewTotal->total_points,
        'points'=>5+$img_point,
        'image_uplod_messages'=>$message1
        // 'place'=>$placeId
      ]);

    }

    public function AddStructuredData(Request $request, $ucode)
    {

      $lat = $request->latitude;
      $lon = $request->longitude;
      //	$location = ''.$lon.' '.$lat.'';
      $input = new StructuredPlace;
      $input->longitude = $lon;
      $input->latitude = $lat;
      //$input->Address = title_case(ltrim($request->Address," "));
      if ($request->has('name')){
        $input->name = $request->name;
      }
      if ($request->has('holding_no')){
        $input->holding_no = $request->holding_no;
      }
      if ($request->has('localName')){
        $input->localName = $request->localName;
      }
      if ($request->has('alternativeName')){
        $input->alternativeName = $request->alternativeName;
      }
      if ($request->has('road_name_number')){
        $input->road_name_number = $request->road_name_number;
      }
      if ($request->has('super_sub_area')){
        $input->super_sub_area = $request->super_sub_area;
      }
      if ($request->has('sub_area')){
        $input->sub_area = $request->sub_area;
      }
      $input->city = title_case($request->city);
      $input->area = title_case(trim($request->area," "));
      $input->postCode = $request->postCode;
      $input->pType = $request->pType;
      $input->subType = $request->subType;
      //longitude,latitude,Address,city,area,postCode,pType,subType,flag,device_ID,user_id,email
      if($request->has('flag'))
      {
        $input->flag = 1;

      }
      $input->user_id =$request->user()->id;

      if ($request->has('email')){
        $input->email = $request->email;
      }

      if ($request->has('number_of_floors')){
        $input->number_of_floors = $request->number_of_floors;
      }
      if ($request->has('contact_person_name')){
        $input->contact_person_name = $request->contact_person_name;
      }
      if ($request->has('contact_person_phone')){
        $input->contact_person_phone = $request->contact_person_phone;
      }
      $input->uCode = $ucode;
      if ($request->has('tags')){
        $tag = $this->insertTags($request->tags);
        $input->tags = $request->tags;

      }
      $point = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
      $input->location = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
      $input->bounds = DB::raw("ST_Buffer($point, 1/11111)");
      $input->save();

      //return response()->json('Added');
    }


    public function AddDSCCData(Request $request)
    {
      $randomStringChar=$this->generateRandomString(4);
      //number part
      $charactersNum = '0123456789';
      $charactersNumLength = strlen($charactersNum);
      $randomStringNum = '';
      for ($i = 0; $i < 4; $i++) {
        $randomStringNum .= $charactersNum[rand(0, $charactersNumLength - 1)];
      }

      $ucode =  ''.$randomStringChar.''.$randomStringNum.'';

      $lat = $request->latitude;
      $lon = $request->longitude;
      //	$location = ''.$lon.' '.$lat.'';
      $input = new StructuredPlace;
      $input->longitude = $lon;
      $input->latitude = $lat;
      //$input->Address = title_case(ltrim($request->Address," "));
      if ($request->has('name')){
        $input->name = $request->name;
      }
      if ($request->has('holding_no')){
        $input->holding_no = $request->holding_no;
      }

      if ($request->has('road_name_number')){
          $input->road_name_number = $request->road_name_number;
      }

      if ($request->has('area')){
        $input->area = $request->area;
      }
      // if ($request->has('super_sub_area')){
      //   $input->road_name_number = $request->road_name_number;
      // }
      // if ($request->has('sub_area')){
      //   $input->road_name_number = $request->road_name_number;
      // }
      if ($request->has('ward')){
        $input->ward = $request->ward;
      }
      if ($request->has('zone')){
        $input->zone = $request->zone;
      }
      $input->city = 'Dhaka';
      $input->area = title_case(trim($request->area," "));
      if ($request->has('postCode')) {
        $input->postCode = $request->postCode;
      }

      $input->pType = 'Residential';
      $input->subType ='House';
      $input->flag = 1;

      $input->user_id =$request->user()->id;

      $input->uCode = $ucode;

      $point = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
      $input->location = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
      $input->bounds = DB::raw("ST_Buffer($point, 1/11111)");
      $input->save();

      //return response()->json('Added');
    }




    //*******ADD PLACE with CUSTOM CODE************************
    //Add new place with custom code
    public function authAddCustomPlace(Request $request)
    {
      $user = JWTAuth::parseToken()->authenticate();
      $userId = $user->id;
      $lat = $request->latitude;
      $lon = $request->longitude;
      //check if it is private and less then 20 meter
      if($request->flag==0){
        $result = DB::table('places')
        ->select(DB::raw('*, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
        //->where('pType', '=','Food')
        ->where('flag','=',0)
        ->where('user_id','=',$userId)
        ->having('distance','<',0.001) //10 meter for private
        ->get();
        $message='Can not Add Another Private Place in 1 meter';
      }
      //check if it is public and less then 50 meter
      if($request->flag==1){
        $result = DB::table('places')
        ->select(DB::raw('*, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
        //->where('pType', '=','Food')
        ->where('flag','=',1)
        ->having('distance','<',0.001) //5 meter for public
        ->get();
        $message='A Public Place is Available in 1 meter';
      }
      if(count($result) === 0)
      {
        $input = new Place;
        $input->longitude = $lon;
        $input->latitude = $lat;
        $input->Address = $request->Address;
        $input->city = $request->city;
        $input->area = $request->area;
        $input->postCode = $request->postCode;
        $input->pType = $request->pType;
        $input->subType = $request->subType;
        //longitude,latitude,Address,city,area,postCode,pType,subType,flag,device_ID,user_id,email
        if($request->has('flag'))
        {
          $input->flag = $request->flag;
          if ($request->flag==1) {
            DB::table('analytics')->increment('public_count');
          }else{
            DB::table('analytics')->increment('private_count');
          }
        }
        if ($request->has('device_ID')) {
          $input->device_ID = $request->device_ID;
        }

        //ADN:when authenticated , user_id from client will be passed on this var.
        $input->user_id =$userId;

        if ($request->has('email')){
          $input->email = $request->email;
        }
        if ($request->has('route_description')){
          $input->route_description = $request->route_description;
        }
        if ($request->has('tags')){
          $tag = $this->insertTags($request->tags);
          $input->tags = $tag;

        }
        $input->uCode = $request->uCode;
        $input->isRewarded = 1;
        $input->location = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
        $input->save();

        //$placeId=$input->id;
        //if image is there, in post request
        $message1='no image file attached.';
        $imgflag=0;//is uploded? initialize

        //handle image
        //user will get 5 points if uploads images
        $img_point=0; //inititate points for image upload

        if ($request->has('images'))
        {
          $placeId=$input->id; //get latest the places id
          $relatedTo=$request->relatedTo;
          $client_id = '55c393c2e121b9f';
          $url = 'https://api.imgur.com/3/image';
          $headers = array("Authorization: Client-ID $client_id");
          //source:
          //http://stackoverflow.com/questions/17269448/using-imgur-api-v3-to-upload-images-anonymously-using-php?rq=1
          $recivedFiles = $request->get('images');
          //$file_count = count($reciveFile);
          // start count how many uploaded
          $uploadcount = count($recivedFiles);
          //return $uploadcount;
          if($uploadcount>1){
            $message1="Can not Upload more then 1 files";
            $imgflag=0;//not uploaded
          }
          else{
            foreach($recivedFiles as $file)
            {
              //$img = file_get_contents($file);
              //$imgarray  = array('image' => base64_encode($file),'title'=> $title);
              $imgarray  = array('image' => $file);
              $curl = curl_init();
              curl_setopt_array($curl, array(
                CURLOPT_URL=> $url,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => 1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $imgarray
              ));
              $json_returned = curl_exec($curl); // blank response
              $json_a=json_decode($json_returned ,true);
              $theImageHash=$json_a['data']['id'];
              // $theImageTitle=$json_a['data']['title'];
              $theImageRemove=$json_a['data']['deletehash'];
              $theImageLink=$json_a['data']['link'];
              curl_close ($curl);

              //save image info in images table;
              $saveImage=new Image;
              $saveImage->user_id=$userId;
              $saveImage->pid=$placeId;
              $saveImage->imageGetHash=$theImageHash;
              //$saveImage->imageTitle=$theImageTitle;
              $saveImage->imageRemoveHash=$theImageRemove;
              $saveImage->imageLink=$theImageLink;
              $saveImage->relatedTo=$relatedTo;
              $saveImage->save();
              $uploadcount--;
            }
            $imgflag=1;
            $message1="Image Saved Successfully";
            $img_point=5;
          }//else end
        } //if reuest has image

        User::where('id','=',$userId)->increment('total_points',5+$img_point);
        $getTheNewTotal=User::where('id','=',$userId)->select('total_points')->first();

        //Slack Webhook : notify
        define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
        // Make your message
        $getuserData=User::where('id','=',$userId)->select('name')->first();
        $name=$getuserData->name;
        $message = array('payload' => json_encode(array('text' => "'".$name."' Added a Place: '".$request->Address."' near '".$request->area.",".$request->city."' area with Code:".$request->uCode. "")));
        //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
        // Use curl to send your message
        $c = curl_init(SLACK_WEBHOOK);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $message);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($c);
        curl_close($c);
        //Webhook ends
        DB::table('analytics')->increment('code_count');
        //return response()->json($ucode);
        return response()->json([
          'uCode' => $request->uCode,
          'points'=>5+$img_point,
          'new_total_points'=>$getTheNewTotal->total_points,
          'img_flag' => $imgflag,
          'image_uplod_messages'=>$message1,
        ]);
      } //count===0
      else{
        return response()->json([
          'message' => $message
        ]);
      }

    }
    public function word(){
      $var=Storage::disk('search')->get('word1.txt');
      //$var = Storage::disk('local')->file_get_contents('word1.txt'); //Take the contents from the file to the variable
      $result = explode(',',$var); //Split it by ','
      //echo $result;
      return $result[array_rand($result)]; //Return a random entry from the array.
    }


    //Store Custom Place
    public function StoreCustomPlace(Request $request)
    {
      $lat = $request->latitude;
      $lon = $request->longitude;
      //check if it is private and less then 20 meter
      if($request->flag==0){
        $result = DB::table('places')
        ->select(DB::raw('*, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
        //->where('pType', '=','Food')
        ->where('flag','=',0)
        ->where('device_ID','=',$request->device_ID)
        ->having('distance','<',0.01) //50 meter for private
        ->get();
        $message='Can not add Multiple Private Address in 10 meter radius from Same Device';
      }
      //check if it is public and less then 50 meter
      if($request->flag==1){

        $result = DB::table('places')
        ->select(DB::raw('*, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
        //->where('pType', '=','Food')
        ->where('flag','=',1)
        ->having('distance','<',0.005) //20 meter for public
        ->get();
        $message='A Public Place is Available in 5 meter';
      }
      if(count($result) === 0)
      {
        $input = new Place;
        $input->longitude = $lon;
        $input->latitude = $lat;
        $input->Address = $request->Address;
        $input->city = $request->city;
        $input->area = $request->area;
        $input->postCode = $request->postCode;
        $input->pType = $request->pType;
        $input->subType = $request->subType;
        //longitude,latitude,Address,city,area,postCode,pType,subType,flag,device_ID,user_id,email
        if($request->has('flag'))
        {
          $input->flag = $request->flag;
          if ($request->flag==1) {
            DB::table('analytics')->increment('public_count');
          }else{
            DB::table('analytics')->increment('private_count');
          }
        }
        if ($request->has('device_ID')) {
          $input->device_ID = $request->device_ID;
        }

        //ADN:when authenticated , user_id from client will be passed on this var.
        if ($request->has('user_id')) {
          $input->user_id = $request->user_id;
        }

        if ($request->has('email')){
          $input->email = $request->email;
        }

        $input->uCode = $request->uCode;
        $input->isRewarded = 0;
        $input->location = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
        $input->save();

        //Slack Webhook : notify
        define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
        // Make your message
        $message = array('payload' => json_encode(array('text' => "Someone Added a Place with Code:".$request->uCode. "")));
        //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
        // Use curl to send your message
        $c = curl_init(SLACK_WEBHOOK);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $message);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($c);
        curl_close($c);

        DB::table('analytics')->increment('code_count');
        //return response()->json($ucode);
        return response()->json([
          'uCode' => $request->uCode,
        ]);
      }
      else{
        return response()->json([
          'message' => $message
        ]);
      }
    }
    /*   public function Slacker($code){
    echo 'Ho';
    define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
    // Make your message
    $message = array('payload' => json_encode(array('text' => "Someone searched for: ".$code. "")));
    // Use curl to send your message
    $c = curl_init(SLACK_WEBHOOK);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, $message);
    curl_exec($c);
    curl_close($c);

    return [];
    } */

    //search address using code
    public function KhujTheSearchTest($code)
    {
      if($token = JWTAuth::getToken()){

        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->id;
        $getuserData=User::where('id','=',$userId)->select('name')->first();
        $name="'".$getuserData->name."'";
      }
      else{
        $name='Someone';
      }

      $place = Place::where('uCode','=',$code)->first();
      DB::table('analytics')->increment('search_count',1);
      //$searched4Code=$code;
      // $this->Slacker($code);
      //webhook adnan: https://hooks.slack.com/services/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
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');

      // Make your message
      $message = array('payload' => json_encode(array('text' => "".$name." searched for: ".$code. "")));
      // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);
      return $place->toJson();
    }
    public function autocomplete()
    {
      $today = Carbon::today()->toDateTimeString();
      $yesterday = Carbon::yesterday()->toDateTimeString();
      $data = Place::whereDate('created_at','=',$today)->count();
      $yesterdayData = Place::whereDate('created_at','=',$yesterday)->count();
      $lastsevenday = Carbon::today()->subDays(6);
      $lastWeek = Place::whereBetween('created_at',[$lastsevenday,$today])->count();

      /*$results = DB::select(
      "SELECT user_id, sum(count) as total
      FROM
      (SELECT
      Address,user_id,created_at, COUNT(Address) count
      FROM
      places
      GROUP BY
      Address
      HAVING
      COUNT(Address) >1)
      AS
      T");
      */

      $names = array_pluck($results, 'total');
      $y = implode('',$names);
      $count  =  count($results);
      $total  = DB::table('places')->count();
      //  $users = DB::table('places')->distinct()->get(['Address','area','longitude','latitude','pType','subType'])->count();
      //  $data = $data->Address;
      return response()->json([
        'Total' => $data,
        'Yesterday'=>$yesterdayData,
        'Duplicate' => $y,
        'all' => $total-$y,
        'lastWeek' => $lastWeek,
        //  'distinct' => $users

      ],200);
    }

    //
    public function KhujTheSearch($code)
    {
      $place = Place::where('uCode','=',$code)->first();
      DB::table('analytics')->increment('search_count',1);
      //$searched4Code=$code;
      // $this->Slacker($code);
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');

      // Make your message
      $message = array('payload' => json_encode(array('text' => "Someone searched for: ".$code. "")));
      // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);

      return $place->toJson();
    }

    public function getListViewItem($code)
    {
      $place = DB::select("SELECT id, longitude,latitude,Address,city,area,postCode,pType,subType,uCode,contact_person_phone,tags,ST_AsGeoJSON(bounds) FROM places WHERE uCode = '$code' OR id = '$code'");
    ///  $place = Place::with('images')->where('uCode','=',$code)->orWhere('id',$code)->first(['id','Address','longitude','latitude','pType','subType','uCode', 'area','city','postCode','contact_person_name','contact_person_phone','road_details','route_description','tags']);
      if (count($place)>0) {
        return response()->json($place[0]);
      }else {
        return response()->json([
          'message' => 'Not Found',
          'status' => '200',
        ]);
      }

    }


    //search with device ID
    public function KhujTheSearchApp($id)
    {

      $place = Place::where('device_ID','=',$id)->where('user_id', null)->get(['id','Address','longitude','latitude','pType','subType','uCode', 'area','city']);

    }

    // Search places by name
    public function search(Request $request)
    {
      //$result = Place::where('area','like',$name)->first();
      $result = DB::select("SELECT id,longitude,latitude,Address,area,city,postCode,uCode, pType, subType FROM
        places
        WHERE
        MATCH (Address, area)
        AGAINST ('.$request->search*' IN BOOLEAN MODE)
        LIMIT 10");

        return response()->json($result);
      }

      // Search places by name
      public function searchNameAndCodeApp(Request $request,$name)
      {
        // $result = Place::where('Address','like','%'.$name.'%')->orWhere('uCode','=',$name)->get();
        $terms=$name;
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->id;
        $getuserData=User::where('id','=',$userId)->select('name')->first();
        $name1="'".$getuserData->name."'";

        $result = Place::where('uCode', '=', $name)
        ->orWhere(function($query) use ($name)
        {
          $query->where('Address','like',$name.'%')
          ->where('flag', '=', 1);
        })
        ->get();
        DB::table('analytics')->increment('search_count',1);
        //$searched4Code=$code;
        // $this->Slacker($code);
        //webhook adnan: https://hooks.slack.com/services/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
        // https://hooks.slack.com/services/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

        if (isset($_SERVER['HTTP_CLIENT_IP']))
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
        $clientDevice = gethostbyaddr($ipaddress);

        define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');


        // Make your message
        $message = array('payload' => json_encode(array('text' => "".$name1." searched for: '".$name. "' from App, ip:".$clientDevice)));
        // Use curl to send your message
        $c = curl_init(SLACK_WEBHOOK);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $message);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($c);
        curl_close($c);

        //Save the log to a .json file
        /*
        $file = file_get_contents('search_log.json', true);
        $data = json_decode($file,true);
        unset($file);
        */
        $file=Storage::disk('search')->get('search_log.json');
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
        /*
        return new JsonResponse([
        'search_result'=>$posts,
        'array'=>$terms,
        'log_saved'=>$log_save
      ]); */
      return $result->toJson();
    }

    public function get_client_ip(Request $request) {
      //$ipaddress = '';
      //$_SERVER['HTTP_USER_AGENT'];

      // if (isset($_SERVER['HTTP_USER_AGENT']))
      // $ipaddress = $_SERVER['HTTP_USER_AGENT'];
      // dd($request);
      //return $request->server('HTTP_USER_AGENT');
      // $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
      // $isp = geoip_isp_by_name($_SERVER['REMOTE_ADDR']);
      $ip=$_SERVER['REMOTE_ADDR'];

      $url=file_get_contents("http://whatismyipaddress.com/ip/$ip");

      preg_match_all('/<th>(.*?)<\/th><td>(.*?)<\/td>/s',$url,$output,PREG_SET_ORDER);

      $isp=$output[1][2];

      $city=$output[9][2];

      $state=$output[8][2];

      $zipcode=$output[12][2];

      $country=$output[7][2];
      // if (isset($_SERVER['HTTP_CLIENT_IP']))
      //     $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
      // else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
      //     $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
      // else if(isset($_SERVER['HTTP_X_FORWARDED']))
      //     $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
      // else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
      //     $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
      // else if(isset($_SERVER['HTTP_FORWARDED']))
      //     $ipaddress = $_SERVER['HTTP_FORWARDED'];
      // else if(isset($_SERVER['REMOTE_ADDR']))
      //     $ipaddress = $_SERVER['REMOTE_ADDR'];
      // else
      //     $ipaddress = 'UNKNOWN';
      // return gethostbyaddr($ipaddress);
      echo $isp;
    }

    // Search places by name
    public function searchNameAndCodeWeb($name)
    {
      // $result = Place::where('Address','like','%'.$name.'%')->orWhere('uCode','=',$name)->get();
      $result = Place::where('uCode', '=', $name)
      ->orWhere(function($query) use ($name)
      {
        $query->where('Address','like',$name.'%')
        ->where('flag', '=', 1);
      })
      ->get();

      DB::table('analytics')->increment('search_count',1);
      //$searched4Code=$code;
      // $this->Slacker($code);
      //webhook adnan: https://hooks.slack.com/services/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
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');

      if (isset($_SERVER['HTTP_CLIENT_IP']))
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
      $clientDevice = gethostbyaddr($ipaddress);

      // Make your message
      $message = array('payload' => json_encode(array('text' => "Someone searched for: '".$name. "' from Website, ip:".$clientDevice)));
      // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);
      return $result->toJson();
    }

    // fetch all data
    public function shobaix(Request $request)
    {
      if ($request->has('dateFrom') && $request->has('dateTill')) {
        $Places = Place::whereBetween('created_at',[$request->dateFrom,$request->dateTill])->get(['id','Address','area','pType','subType','longitude','latitude','uCode','user_id','created_at']);
        return new JsonResponse([
            'Message' => $Places,
          ],200);
        }
      else {
        $today = Carbon::today()->toDateTimeString();
        $places = Place::whereDate('created_at','=',$today)->get(['id','Address','area','longitude','latitude','pType','subType','uCode','created_at']);
      //  $Updatedplaces = Place::whereDate('updated_at','=',$today)->get(['id','Address','area','longitude','latitude','pType','subType','uCode','updated_at']);
      return response()->json($places);
        }
    }
    //Test paginate
    public function shobaiTest()
    {
      $places = Place::with('images')->with('user')->orderBy('id', 'DESC')->paginate(50);
      return $places->toJson();
    }
    /*
    @@
    @Delete Place
    @@
    */
    public function mucheFeliMyPlace(Request $request,$barikoiCode)
    {

      // $x =DB::select("SELECT * FROM places where uCode = $barikoiCode");

       Place::where('uCode','=',$barikoiCode)->delete();
       DB::select("INSERT
         INTO deleted_places
         SELECT *
         FROM places where uCode = '$barikoiCode'");
    //  DB::table('places_last_cleaned')->where('uCode','=', $barikoicode)->delete();
    if (DB::table('placesf')->where('uCode', '=', $barikoiCode)->exists()) {
      DB::table('placesf')->where('uCode','=', $barikoiCode)->delete();
    }
    if (DB::table('StructuredPlaces')->where('uCode', '=', $barikoiCode)->exists()) {
      DB::table('StructuredPlaces')->where('uCode','=', $barikoiCode)->delete();
    }
    //
    define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');

    $message = array('payload' => json_encode(array('text' => "'".$request->user()->name."' DELETED ".$barikoiCode.",".$barikoiCode."' area with Code:".$barikoiCode."")));
    //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
    // Use curl to send your message
    $c = curl_init(SLACK_WEBHOOK);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_POSTFIELDS, $message);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
    $res = curl_exec($c);
    curl_close($c);
      return response()->json('Place Deleted!');

    }

    public function mucheFeli(Request $request,$barikoicode)
    {
      DB::select("INSERT
        INTO deleted_places
        SELECT *
        FROM places where places.uCode = '$barikoicode'");
      $email = $request->user()->email;
      DB::select("UPDATE deleted_places set email = '$email' where uCode = '$barikoicode'");
      $places = Place::where('uCode','=',$barikoicode)->first();
      if (DB::table('StructuredPlaces')->where('uCode', '=', $barikoicode)->exists()) {
        DB::table('StructuredPlaces')->where('uCode','=', $barikoicode)->delete();
      }

      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');

      $message = array('payload' => json_encode(array('text' => "'".$request->user()->name."' DELETED a Place: '".title_case($places->Address)."' near '".$barikoicode.",".$barikoicode."' area with Code:".$barikoicode."")));
      //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
      // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);

    //  DB::table('places_last_cleaned')->where('uCode','=', $barikoicode)->delete();
      //DB::table('placesf')->where('uCode','=', $barikoicode)->delete();
      //DB::table('placesMaster')->where('uCode','=', $barikoicode)->delete();
      $places->delete();
      if (DB::table('placesf')->where('uCode', '=', $barikoicode)->exists()) {
        DB::table('placesf')->where('uCode','=', $barikoicode)->delete();
      }
      return response()->json('Deleted');
    }

    /*
    DELETE Finish
    */
    //Update My Place
    public function halnagadMyPlace(Request $request,$id){

      $userId = $request->user()->id;

      $places = Place::where('uCode','=',$id)->orWhere('id',$id)->first();
      $image=Image::where('pid',$places->id)->delete();
      $address = $places->Address;
      if ($request->has('longitude')) {
        $places->longitude = $request->longitude;
      }
      if ($request->has('latitude')) {
        $places->latitude = $request->latitude;
      }
      if ($request->has('Address')) {
        $places->Address = $request->Address;
      }
      if ($request->has('city')) {
        $places->city = $request->city;
      }
      if ($request->has('area')) {
        $places->area = $request->area;
      }

      if ($request->has('postCode')) {
        $places->postCode = $request->postCode;
      }
      if ($request->has('flag')) {
        $places->flag = $request->flag;
      }

      if ($request->has('pType')) {
        $places->pType = $request->pType;
      }
      if ($request->has('subType')) {
        $places->subType = $request->subType;
      }
      if ($request->has('route_description')){
        $places->route_description = $request->route_description;
      }
      if ($request->has('contact_person_name')) {
        $places->contact_person_name = $request->contact_person_name;
      }

      if ($request->has('contact_person_phone')) {
        $places->contact_person_phone = $request->contact_person_phone;
      }

      if ($request->has('road_details')){
        $places->road_details = $request->road_details;
      }
      if ($request->has('number_of_floors')){
        $places->number_of_floors = $request->number_of_floors;
      }
      if ($request->has('tags')){
        if (is_array($request->tags)) {
         return response()->json('invalid input');
       }else {
         $tag = $this->insertTags($request->tags);
         $places->tags = $request->tags;
       }

      }
      if ($request->has('longitude') && $request->has('latitude')) {
        $places->location = DB::raw("ST_GEOMFROMTEXT('POINT($request->longitude $request->latitude)')");
      }
      if ($request->has('bounds')) {
        $places->bounds = DB::raw("ST_GEOMFROMTEXT('POLYGON(($request->bounds))')");
      }

      $places->save();
      if (DB::table('StructuredPlaces')->where('uCode','=',$id)->orWhere('id',$id)->exists()) {
        $this->AddStructuredData($request,$id);
      }

      if ($request->has('images'))
      {
        $placeId=$places->id; //get latest the places id
        $relatedTo ='place';
        $client_id = '55c393c2e121b9f';
        $url = 'https://api.imgur.com/3/image';
        $headers = array("Authorization: Client-ID $client_id");
        //source:
        //http://stackoverflow.com/questions/17269448/using-imgur-api-v3-to-upload-images-anonymously-using-php?rq=1
        $recivedFiles = $request->get('images');
        //$file_count = count($reciveFile);
        // start count how many uploaded
        $uploadcount = count($recivedFiles);
        //return $uploadcount;
        $file = $recivedFiles;
        //$img = file_get_contents($file);
        //$imgarray  = array('image' => base64_encode($file),'title'=> $title);
        $imgarray  = array('image' => $file);
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL=> $url,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_POST => 1,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_HTTPHEADER => $headers,
          CURLOPT_POSTFIELDS => $imgarray
        ));
        $json_returned = curl_exec($curl); // blank response
        $json_a=json_decode($json_returned ,true);
        $theImageHash=$json_a['data']['id'];
        // $theImageTitle=$json_a['data']['title'];
        $theImageRemove=$json_a['data']['deletehash'];
        $theImageLink=$json_a['data']['link'];
        curl_close ($curl);

        //save image info in images table;
        $saveImage=new Image;
        $saveImage->user_id=$userId;
        $saveImage->pid=$placeId;
        $saveImage->imageGetHash=$theImageHash;
        //$saveImage->imageTitle=$theImageTitle;
        $saveImage->imageRemoveHash=$theImageRemove;
        $saveImage->imageLink=$theImageLink;
        $saveImage->relatedTo=$relatedTo;
        $saveImage->save();
        $uploadcount--;


      }
      if ($request->has('pType') && $request->has('subType')) {

      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
      // Make your message
      $getuserData=User::where('id','=',$userId)->select('name')->first();
      $name=$getuserData->name;
      $message = array('payload' => json_encode(array('text' => "'".$name."' UPDATED a Place To: '".title_case($request->Address)." From ".$address." pType: ".$request->pType.", Subtype: ".$request->subType."")));
      //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
      // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);
    }else {
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
      // Make your message
      $getuserData=User::where('id','=',$userId)->select('name')->first();
      $name=$getuserData->name;
      $message = array('payload' => json_encode(array('text' => "'".$name."' UPDATED a Place To: '".title_case($request->Address)." From ".$address."")));
      //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
      // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);
    }

      return response()->json(['message' => 'Updated', 'status code'=> '200']);

    }
    public function updateStructuredAddress(Request $request,$id)
    {
      $input = StructuredPlace::where('uCode','=',$id)->orWhere('id',$id)->first();
      $lat = $request->latitude;
      $lon = $request->longitude;

      if ($request->has('name')){
        $input->name = $request->name;
      }
      if ($request->has('holding_no')){
        $input->holding_no = $request->holding_no;
      }
      if ($request->has('localName')){
        $input->localName = $request->localName;
      }
      if ($request->has('alternativeName')){
        $input->alternativeName = $request->alternativeName;
      }
      if ($request->has('road_name_number')){
        $input->road_name_number = $request->road_name_number;
      }
      if ($request->has('super_sub_area')){
        $input->super_sub_area = $request->super_sub_area;
      }
      if ($request->has('sub_area')){
        $input->sub_area = $request->sub_area;
      }
      if ($request->has('city')) {
        $input->city = title_case($request->city);
      }
      if ($request->has('area')) {
        $input->area = title_case(trim($request->area," "));
      }
      if ($request->has('postCode')) {
        $input->postCode = $request->postCode;
      }
      if ($request->has('pType')) {
        $input->pType = $request->pType;
      }
      if ($request->has('subType')) {
        $input->subType = $request->subType;
      }
      if($request->has('flag'))
      {
        $input->flag = 1;

      }
      $input->user_id =$request->user()->id;

      if ($request->has('email')){
        $input->email = $request->email;
      }

      if ($request->has('number_of_floors')){
        $input->number_of_floors = $request->number_of_floors;
      }
      if ($request->has('contact_person_name')){
        $input->contact_person_name = $request->contact_person_name;
      }
      if ($request->has('contact_person_phone')){
        $input->contact_person_phone = $request->contact_person_phone;
      }
      $point = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
      $input->location = DB::raw("ST_GEOMFROMTEXT('POINT($lon $lat)')");
      $input->bounds = DB::raw("ST_Buffer($point, 1/11111)");
      $input->save();
    }
    //update
    public function halnagad(Request $request,$barikoicode){
      $places = Place::where('uCode','=',$barikoicode)->first();
      if ($request->has('longitude')) {
        $places->longitude = $request->longitude;
      }
      if ($request->has('latitude')) {
        $places->latitude = $request->latitude;
      }
      $places->Address = $request->Address;
      $places->city = $request->city;
      $places->area = $request->area;
      if($request->has('user_id')){
        $places->user_id = $request->user_id;
      }
      $places->postCode = $request->postCode;
      $places->flag = $request->flag;
      $places->save();
      //$splaces = SavedPlace::where('pid','=',$id)->update(['Address'=> $request->Address]);
      define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
      // Make your message
      $getuserData=User::where('id','=',$userId)->select('name')->first();
      $name=$getuserData->name;
      $message = array('payload' => json_encode(array('text' => "'".$name."' UPDATED a Place: '".title_case($request->Address)."' near '".$request->area.",".$request->city."")));
      //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
      // Use curl to send your message
      $c = curl_init(SLACK_WEBHOOK);
      curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($c, CURLOPT_POST, true);
      curl_setopt($c, CURLOPT_POSTFIELDS, $message);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
      $res = curl_exec($c);
      curl_close($c);

      return response()->json('updated');
    }

    public function placeType(Request $request)
    {
      $type = new PlaceType;
      $type->type = $request->pType;
      $type->save();

      return response()->json('Done');
    }
    public function placeTypeUpdate(Request $request,$id)
    {
      $type = PlaceType::findOrFail($id);
      $type->type = $request->pType;
      $type->save();

      return response()->json('Done');
    }
    public function placeSubType(Request $request)
    {
      $type = new PlaceSubType;
      $type->type = $request->pType;
      $type->subtype = $request->subType;
      $type->save();
      return response()->json('Added');
    }
    public function placeSubTypeUpdate(Request $request,$id)
    {
      $type = PlaceSubType::findOrFail($id);
      $type->type = $request->pType;
      $type->subtype = $request->subType;
      $type->save();
      return response()->json('updated');
    }

    public function getPlaceType()
    {
      $type = PlaceType::orderBy('type','asc')->get();

      return $type->toJson();
    }

    public function getPlaceSubType($type)
    {
      $type =str_replace('%20', ' ', $type);
      $subtype = placeSubType::where('type','=',$type)->orderBy('subtype','asc')->get();
      //    $subtype = $subtype->subtype;
      //      return response()->json($subtype);

      return $subtype->toJson();
    }
    public function ashpash($ucode)
    {
      $places = Place::with('images')->where('uCode','=',$ucode)->first();
      $lat = $places->latitude;
      $lon = $places->longitude;
      $distance = 0.1;

      $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, uCode,contact_person_phone,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lon+($distance/111)), ($lat+($distance/111))),
        Point(($lon-($distance/111)), ($lat-($distance/111)))
      ), location )
      ORDER BY distance_in_meters");
      DB::table('analytics')->increment('search_count',1);
      return response()->json($result);
    }
    public function amarashpash(Request $request)
    {
      $lat = $request->latitude;
      $lon = $request->longitude;
      $distance = 0.1;
      //  $id = $request->user()->id;
      $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType,contact_person_phone,uCode,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lon+($distance/111)), ($lat+($distance/111))),
        Point(($lon-($distance/111)), ($lat-($distance/111)))
      ), location )
      ORDER BY distance_in_meters");
      DB::table('users')->where('id',$request->user()->id)->update(['user_last_lon'=>$lon,'user_last_lat'=>$lat]);

    return response()->json($result);

    }

    public function amarashpashAuth(Request $request)
    {
      $lat = $request->latitude;
      $lon = $request->longitude;
      $distance = 0.1;
      //  $id = $request->user()->id;
      $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, uCode,contact_person_phone,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lon+($distance/111)), ($lat+($distance/111))),
        Point(($lon-($distance/111)), ($lat-($distance/111)))
      ),location ) AND (pType != 'Residential' AND flag = 1)
      ORDER BY distance_in_meters");
      DB::table('users')->where('id',$request->user()->id)->update(['user_last_lon'=>$lon,'user_last_lat'=>$lat]);

    return response()->json($result);


    }

    public function amarashpashCatagorized(Request $request)
    {
      $lat = $request->latitude;
      $lon = $request->longitude;
      $distance = 1.5;
      if ($request->has('ptype')) {
        $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, uCode,contact_person_phone,ST_AsText(location)
        FROM places
        WHERE ST_Contains( ST_MakeEnvelope(
          Point(($lon+($distance/111)), ($lat+($distance/111))),
          Point(($lon-($distance/111)), ($lat-($distance/111)))
        ), location ) AND ( pType LIKE '%$request->ptype%')
        ORDER BY distance_in_meters
        LIMIT 50");
      }else {
        $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, uCode,contact_person_phone,ST_AsText(location)
        FROM places
        WHERE ST_Contains( ST_MakeEnvelope(
          Point(($lon+($distance/111)), ($lat+($distance/111))),
          Point(($lon-($distance/111)), ($lat-($distance/111)))
        ), location ) AND (subType LIKE '%$request->subtype%')
        ORDER BY distance_in_meters
        LIMIT 20");
      }
      DB::table('analytics')->increment('search_count',1);
      if (count($result)>0) {
        return response()->json($result);
      }else {
        return response()->json(['Message' => 'Not Found'],200);
      }






    }

    public function amarashpashVerification(Request $request)
    {
      $lat = $request->latitude;
      $lon = $request->longitude;

      $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, uCode,contact_person_phone,ST_AsText(location), postCode,tags,
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lon+($distance/111)), ($lat+($distance/111))),
        Point(($lon-($distance/111)), ($lat-($distance/111)))
      ), location )
      ORDER BY distance_in_meters");
      DB::table('analytics')->increment('search_count',1);
      return response()->json($result);

    }

    public function amarashpashVerificationDtool(Request $request)
    {
      $lat = $request->latitude;
      $lon = $request->longitude;

      $distance = 0.1;
      $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, postCode ,uCode,contact_person_phone,contact_person_name,tags,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lon+($distance/111)), ($lat+($distance/111))),
        Point(($lon-($distance/111)), ($lat-($distance/111)))
      ), location )
      ORDER BY distance_in_meters");
      DB::table('analytics')->increment('search_count',1);
      $totalNumber = count($result);

      return response()->json($result);

    }

    public function amarashpashVerificationAnalytics(Request $request)
    {
      $lat = $request->latitude;
      $lon = $request->longitude;
      $distance = 0.3;
      $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters, longitude,latitude,Address,city,area,pType,subType, postCode,uCode,contact_person_phone,contact_person_name,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lon+($distance/111)), ($lat+($distance/111))),
        Point(($lon-($distance/111)), ($lat-($distance/111)))
      ), location )
      ORDER BY distance_in_meters");
      $Residential = $this->measureDistance('Residential',$distance,$lat,$lon);
      $Shops = $this->measureDistance('Shop',$distance,$lat,$lon);
      $Food = $this->measureDistance('Food',$distance,$lat,$lon);
      $Education= $this->measureDistance('Education',$distance,$lat,$lon);
      $Religious= $this->measureDistance('Religious_Place',$distance,$lat,$lon);
      DB::table('analytics')->increment('search_count',1);
      $rg = $this->reverseGeocodeDash($lat,$lon);

      return response()->Json([
        'Your are Currently at or nearby' => $rg,
        'Residential' => count($Residential),
        //  'House to Shop Ratio' => count($Residential)/count($Shops),
        'Shops' => count($Shops),
        'Food'  => count($Food),
        'Education' => count($Education),
        'Masjids' => count($Religious),
        //  'Total Shop' => $totalShop+$totalFood,
        //  'Total house' => $totalHouse,
        //'gd'=>$gd,

        'Places' => $result


      ]);

    }

    /*public function analytics()
    {
    $numbers=analytics::all();
    return $numbers->toJson();
    }
    */
    public function savedPlaces(Request $request)
    {
      $saved = new SavedPlace;
      $saved->uCode = $request->uCode;
      $saved->Address = $request->Address;
      $saved->device_ID = $request->device_ID;
      $saved->email = $request->email;
      $saved->save();
      DB::table('analytics')->increment('saved_count');

      return response()->json('saved');

    }

    public function DeleteSavedPlace(Request $request,$code)
    {
      //$places = SavedPlace::where('uCode','=',$code)->where('device_ID','=',$request->device_ID)->get();
      $places = DB::table('saved_places')->where('uCode','=',$code)->where('device_ID','=', $request->device_ID)->delete();
      //	$places->delete();
      return response()->json('Done');
    }

    public function count()
    {
      $place = DB::table('places')->count();
      $placePub = DB::table('places')->where('flag',1)->count();
      $placePri = DB::table('places')->where('flag',0)->count();
      return response()->json([
        'place_total'=>$place,
        'place_public'=>$placePub,
        'place_private'=>$placePri]);
      }
      /*  public function devices()
      {
      $users = DB:table('places')->distinct('device_ID')->count();
      return response()->json($users);
    }*/
    public function contactUs(Request $request)
    {
      $name = $request->name;
      $email = $request->email;
      $Messsage = $request->message;

      $message = ''.$name.', '.$email.' wants to get connected';
      $channel = 'random';
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

      return response()->json('Thank you, We will get back to you soon.');
    }

    public function tourism()
    {
      $ghurbokoi = Place::with('images')->where('pType','=','Tourism')->get(['id','Address','longitude','latitude','pType','subType','uCode', 'area','city','postCode','contact_person_phone']);

      return $ghurbokoi->toJson();
    }

    public function duplicate($id)
    {
      $today = Carbon::today()->toDateTimeString();
      $today2 = Carbon::today();
      $yesterday = Carbon::yesterday()->toDateTimeString();
      $x =DB::select(
        "SELECT
        Address,area,pType,user_id,created_at, COUNT(*)
        FROM
        places
        WHERE
        user_id = $id
        GROUP BY
        Address,user_id
        HAVING
        COUNT(*) >1
        ORDER BY
        created_at");
        $results = DB::select(
          "SELECT user_id, sum(count) as total
          FROM
          (SELECT
            id,Address,pType,user_id,created_at, COUNT(id) count
            FROM
            places
            WHERE
            user_id = $id
            GROUP BY
            Address
            HAVING
            COUNT(id) >1)
            T
            GROUP BY
            user_id
            ORDER BY
            created_at");
            $todays = DB::select(
              "SELECT user_id, sum(count) as total
              FROM
              (SELECT
                id,Address,pType,user_id,created_at, COUNT(id) count
                FROM
                places
                WHERE
                DATE(created_at) = DATE(NOW())
                AND  user_id = $id
                GROUP BY
                Address
                HAVING
                COUNT(id) >1)
                T
                GROUP BY
                user_id
                ORDER BY
                Address");

                $names = array_pluck($results, 'total');
                $y = implode('',$names);

                $count = count($x);
                return response()->Json(['Duplicates' => $y-$count, 'Total Duped Places' => $count, 'todays' => $todays ]);
              }
              public function fakeCatcher(Request $request)
              {
                $place = Place::where('user_id',$request->id)->where('Address','like','%'.$request->Address.'%')->where('pType','Residential')->delete();
                $count = count($place);
                return response()->json([
                  'count'=> $count,
                  'Places' => $place,
                ]);
              }


              public function duplicateforMapper(Request $request)
              {
                $id = $request->user()->id;
                $today = Carbon::today()->toDateTimeString();
                $yesterday = Carbon::yesterday()->toDateTimeString();
                $results = DB::select(
                  "SELECT
                  Address, area,pType,user_id,created_at, COUNT(*)
                  FROM
                  places
                  WHERE
                  user_id = $id
                  GROUP BY
                  Address,area,user_id
                  HAVING
                  COUNT(*) >1
                  ORDER BY
                  created_at");

                  $count = count($results);
                  return response()->json([
                    'count' => $count,
                    'date' =>$today,
                    'duplicates' => $results,


                  ]);
                }

                public function getPlaceByType(Request $request)
                {
                  $place = Place::where('subType','like', '%'.$request->subType.'%')->get(['id','Address','area','longitude','latitude','pType','subType']);
                  $count = count($place);
                  return response()->json([
                    'Total' => $count,
                    'Places' => $place,
                  ]);
                }

                public function getAllSubtype()
                {
                  $subtype = PlaceSubType::orderBy('subtype','desc')->get();
                  return $subtype->toJson();
                }
                public function dropEdit(Request $request,$id)
                {
                  if (($request->user()->id)===1 || ($request->user()->id)===12 || ($request->user()->id)===17 || ($request->user()->id)===320 || ($request->user()->id)===1111 || ($request->user()->id)===1222 || ($request->user()->id)===1279 || ($request->user()->id)===1276  ) {
                    $place = Place::findOrFail($id);
                    $place->longitude = $request->longitude;
                    $place->latitude = $request->latitude;
                    $place->location = DB::raw("ST_GEOMFROMTEXT('POINT($request->longitude $request->latitude)')");
                    $place->save();

                    // $placef = DB::table('placesf')->where('id',$id)->first();
                    // $placef->longitude = $request->longitude;
                    // $placef->latitude = $request->latitude;
                    // $placef->location = DB::raw("ST_GEOMFROMTEXT('POINT($request->longitude $request->latitude)')");
                     return response()->json(['Message '=>' Updated']);
                  }else {
                    return response()->json(['Message '=>' Not Permitted']);
                  }




                }
                public function dropEditApp(Request $request,$id)
                {
                  $place = Place::where('uCode','=',$id)->first();
                  $place->longitude = $request->longitude;
                  $place->latitude = $request->latitude;
                //  $place->save();

                  return response()->json(['Message '=>' Updated']);
                }

                /*Export Data*/
                // public function export($id){
                //   $today = Carbon::today()->toDateTimeString();
                //   $lastsixday = Carbon::today()->subDays(6);
                //   $places=Place::where('user_id',$id)->whereBetween('created_at',[$lastsixday,$today])->get(['id','Address','area','postCode','pType','subType','created_at']);
                //   Excel::create(''.$id.'', function($excel) use ($places) {
                //     $excel->sheet('ExportPlaces', function($sheet) use ($places) {
                //       $sheet->fromArray($places);
                //     });
                //   })->export('xls');
                //
                // }
                // // public function exportDataIdWise(Request $request){
                //   $start = $request->start;
                //   $end = $request->end;
                //
                //   $places=Place::whereBetween('id',[$start,$end])->get();
                //   Excel::create(''.$end.'', function($excel) use ($places) {
                //     $excel->sheet('ExportPlaces', function($sheet) use ($places) {
                //       $sheet->fromArray($places);
                //     });
                //   })->export('xls');
                //
                // }

                // public function exportToday(){
                //   $today = Carbon::today()->toDateTimeString();
                //   $lastsixday = Carbon::today()->subDays(6);
                //   $places=Place::whereDate('created_at',$today)->get(['id','Address','area','postCode','pType','subType','created_at']);
                //   Excel::create(''.$today.'', function($excel) use ($places) {
                //     $excel->sheet('ExportPlaces', function($sheet) use ($places) {
                //       $sheet->fromArray($places);
                //     });
                //   })->export('xls');
                //
                // }

              public function reverseGeocode(Request $request)
              {

                  $lat = $request->latitude;
                  $lon = $request->longitude;
                  $distance = 0.1;
                  $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters,longitude,latitude,pType,Address,area,city,subType,uCode, postCode,contact_person_phone,ST_AsText(location), ST_AsGeoJSON(bounds)
                  FROM places
                  WHERE ST_Contains( ST_MakeEnvelope(
                    Point(($lon+($distance/111)), ($lat+($distance/111))),
                    Point(($lon-($distance/111)), ($lat-($distance/111)))
                  ), location )
                  ORDER BY distance_in_meters LIMIT 1");
                  if (count($result)>0) {
                    return response()->json($result);
                  }

                  else {
                    $result = DB::select("SELECT longitude,latitude,pType,Address,area,city,subType,uCode, contact_person_phone,ST_ASTEXT(bounds) FROM places WHERE ST_Contains(places.bounds, ST_GEOMFROMTEXT('POINT($lon $lat)')) LIMIT 1");
                     if (count($result)>0) {
                       return response()->json($result);
                     }else {
                    $road = DB::select("SELECT road_name_number from roads where ST_Distance(road_geometry,POINT($lon,$lat))<20/11114");
                    if (count($road)>0) {
                      return new JsonResponse([
                         ['Address' => $road[0]->road_name_number,
                         'uCode' => 'Not Available',
                         'subType' => 'Not Available',
                         'pType' => 'Not Available',
                         'longitude'=> $lon,
                         'latitude' => $lat,
                         'city' =>'N/A',
                         'area' => 'N/A',
                         'id' =>'N/A',
                         'distance_in_meters' => 0,
                         'contact_person_name' => 'N/A',
                         'ST_AsText(location)' => 'N/A',
                         'Data Source' => 'Barikoi Roads'
                       ],
                      ]);
                    }else {
                      $client = new \GuzzleHttp\Client();
                        $res = $client->request('GET', 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='.$request->latitude.'&lon='.$request->longitude.'&accept-language=en');
                        //preg_match_all('!\d+!', $str->Address, $matches);
                        $res = $res->getBody();
                        $data = json_decode( $res, true );
                        $Address = $data['display_name'];
                        return new JsonResponse([
                           ['Address' => $Address,
                           'uCode' => 'Not Available',
                           'subType' => 'Not Available',
                           'pType' => 'Not Available',
                           'longitude'=> $lon,
                           'latitude' => $lat,
                           'city' =>'N/A',
                           'area' => 'N/A',
                           'id' =>'N/A',
                           'distance_in_meters' => 0,
                           'contact_person_name' => 'N/A',
                           'ST_AsText(location)' => 'N/A',
                           'Data Source' => 'OpenstreetMap'
                         ],
                        ]);
                      }
                    }
                  }


            }

            public function reverseGeocodeDash($longitude,$latitude)
            {
              $lat = $latitude;
              $lon = $longitude;
              $distance = 0.2;
              $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters,longitude,latitude,pType,Address,area,city,subType, ST_AsText(location), ST_AsGeoJSON(bounds)
              FROM places
              WHERE ST_Contains( ST_MakeEnvelope(
                Point(($lon+($distance/111)), ($lat+($distance/111))),
                Point(($lon-($distance/111)), ($lat-($distance/111)))
              ), location )
              ORDER BY distance_in_meters LIMIT 1");
              /*  $result= Place::with('images')
              ->select(DB::raw('Address,area,city, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
              ->having('distance','<',1)
              ->orderBy('distance')
              ->limit(1)
              ->get();*/
              return $result;
            }

            public function reverseGeocodeNew(Request $request)
            {
              $lat = $request->latitude;
              $lon = $request->longitude;
              $distance = 0.1;
              //$result = DB::select("SELECT id, slc($lat, $lon, y(location), x(location))*10000 AS distance_in_meters, Address,area,longitude,latitude,pType,subType, astext(location) FROM places_2 WHERE MBRContains(envelope(linestring(point(($lat+(0.2/111)), ($lon+(0.2/111))), point(($lat-(0.2/111)),( $lon-(0.2/111))))), location) order by distance_in_meters LIMIT 1");
              $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters,longitude,latitude,pType,Address,area,city,subType,uCode,contact_person_phone, ST_AsText(location)
              FROM places
              WHERE ST_Contains( ST_MakeEnvelope(
                Point(($lon+($distance/111)), ($lat+($distance/111))),
                Point(($lon-($distance/111)), ($lat-($distance/111)))
              ), location )
              ORDER BY distance_in_meters LIMIT 1");
              /*  $result= Place::with('images')
              ->select(DB::raw('Address,area,city, ((ACOS(SIN('.$lat.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$lat.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lon.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
              ->having('distance','<',1)
              ->orderBy('distance')
              ->limit(1)
              ->get();*/
              return $result;
            }

            public function reverseGeocodeForAddressAddition(Request $request)
            {
              $lat = $request->latitude;
              $lon = $request->longitude;
              $distance = 0.5;
              //$result = DB::select("SELECT id, slc($lat, $lon, y(location), x(location))*10000 AS distance_in_meters, Address,area,longitude,latitude,pType,subType, astext(location) FROM places_2 WHERE MBRContains(envelope(linestring(point(($lat+(0.2/111)), ($lon+(0.2/111))), point(($lat-(0.2/111)),( $lon-(0.2/111))))), location) order by distance_in_meters LIMIT 1");
              $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters,longitude,latitude,pType,Address,area,city,postCode,subType,uCode, ST_AsText(location)
              FROM places
              WHERE ST_Contains( ST_MakeEnvelope(
                Point(($lon+($distance/111)), ($lat+($distance/111))),
                Point(($lon-($distance/111)), ($lat-($distance/111)))
              ), location )
              ORDER BY distance_in_meters LIMIT 10");

              return $result;
            }

            public function measureDistance($type,$distance,$lat,$lon)
            {


              $result = DB::select("SELECT id, ST_Distance_Sphere(Point($lon,$lat), location) as distance_in_meters,longitude,latitude,pType Address,subType, ST_AsText(location)
              FROM places
              WHERE ST_Contains( ST_MakeEnvelope(
                Point(($lon+($distance/111)), ($lat+($distance/111))),
                Point(($lon-($distance/111)), ($lat-($distance/111)))
              ), location ) AND match(pType) against ('$type' IN BOOLEAN MODE)
              ORDER BY distance_in_meters");

              return $result;
            }

            // get ward

            public function getWard()
            {
              $Place = DB::table('places')->get(['ward']);

              //return response()->json(['ward' => $Place]);
            }
            public function getAreaWise(Request $request)
            {
              $Place = DB::table('places')->where('area', 'LIKE',$request->area)->get(['id','Address','longitude','latitude','pType','subType','uCode', 'area','city']);
              $count = count($Place);
              return response()->json(['Total' => $count,'Area' => $Place]);
            }
            public function getWardWise(Request $request)
            {
              $Place = DB::table('places')->where('ward',$request->ward)->get(['id','Address','longitude','latitude','pType','subType','uCode', 'area','city']);
              $count = count($Place);
              return response()->json(['Total' => $count,'Places' => $Place]);
            }

            public function getRoadWise(Request $request)
            {
              $Place = DB::table('placesf')->where('area','LIKE','%'.$request->data.'%')->get(['id','Address','longitude','latitude','pType','subType','uCode','area','city','postCode','user_id']);
              $count = count($Place);
              return response()->json(['Total' => $count,'Places' => $Place]);
            }


            /*
            @@ Analytics
            */

    public function analytics()
      {

          $today = Carbon::today()->toDateTimeString();
          $yesterday = Carbon::yesterday()->toDateTimeString();
          $data = DB::connection('sqlite')->table('places_3')->whereDate('created_at','=',$today)->count();
          $yesterdayData = DB::connection('sqlite')->table('places_3')->whereDate('created_at','=',$yesterday)->count();
          $lastsevenday = Carbon::today()->subDays(6);
          $weekbeforelastweek = Carbon::today()->subDays(12);
          $before2weeks = Carbon::today()->subDays(18);
          $lastWeek = DB::connection('sqlite')->table('places_3')->whereBetween('created_at',[$lastsevenday,$today])->count();
          $beforelastWeek = DB::connection('sqlite')->table('places_3')->whereBetween('created_at',[$weekbeforelastweek,$lastsevenday])->count();
          $twoweeksago = DB::connection('sqlite')->table('places_3')->whereBetween('created_at',[$before2weeks,$weekbeforelastweek])->count();
          $contributor = User::where('isAllowed',0)->count();

          $totalSearch = analytics::select('search_count')->get();
          $totalCount = DB::connection('sqlite')->table('places_3')->select('id')->count();
        //  $publicCount = DB::connection('sqlite')->table('places_3')->where('flag',1)->count();
        //  $privateCount = DB::connection('sqlite')->table('places_3')->where('flag',0)->count();


          return response()->json([
            'Total Code' =>$totalCount,
          //  'Public Code' => $publicCount,
          //  'Private Code' => $privateCount,
            'search_count' => $totalSearch,
            //  'Duplicates'   => $y,
            'Todays' => $data,
            'Yesterday'=>$yesterdayData,
            'lastWeek' => $lastWeek,
            'weekBeforeLastWeek' => $beforelastWeek,
            'twoweeksago' => $twoweeksago,
            'contributor' => $contributor,
          ]);
        }


        /*

        @SaveFiles
        */
        public function saveFile($file)
        {
          $filename = str_replace(' ', '_', $file->getClientOriginalName());
          Storage::put($filename,  File::get($file));
          return $filename;
        }
        public function deleteFile($name)
        {
          Storage::delete($name);
          return response()->json('success');
        }
        public function getFileList(){
          $files = Storage::files('/');
          return response()->json($files);
        }
        public function viewFile($name){
          $path = storage_path('storage/'.$name);
          return response()->view($path);
        }


        /*
        @@Saved Places

        */
        public function getPlacesByUserDeviceId(Request $request)
        {
          $userId = $request->user()->id;
         //update all places with this 'deviceId' ,where user_id is null -> update the user id to $userId;
          //$placesWithDvid=Place::where('device_ID','=',$deviceId)->where('user_id', null)->update(['user_id' => $userId]);
          //get the places with user id only
          if ($request->has('limit')) {
            $place = Place::where('user_id','=',$userId)->orderBy('id', 'DESC')->limit($request->limit)->get(['id','Address','longitude','latitude','pType','subType','uCode', 'area','city','postCode','contact_person_phone','contact_person_name','tags']);
          }else {
            $place = Place::where('user_id','=',$userId)->orderBy('id', 'DESC')->limit(1000)->get(['id','Address','longitude','latitude','pType','subType','uCode', 'area','city','postCode','contact_person_phone','contact_person_name','tags']);
          }

          return $place->toJson();
          //return $deviceId;
        }

        public function getPlacesByUserIdPaginate(Request $request)
        {

          $userId = $request->user()->id;
          //get the places with user id only
          $place = Place::where('user_id','=',$userId)->orderBy('id', 'DESC')->paginate(10);
          return $place->toJson();
          //return $deviceId;
        }

          public function getPlacesByUserId()
          {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user->id;
            //get the places with user id only
            $place = Place::with('images')->where('user_id','=',$userId)->get(['id','Address','longitude','latitude','pType','subType','postCode','uCode', 'area','city','contact_person_phone','contact_person_name','tags']);
            return response()->json($place);
            //return $deviceId;
          }

          // get all saved places for a userId
          public function getSavedPlacesByUserId()
          {
              $user = JWTAuth::parseToken()->authenticate();
              $userId = $user->id;
              $savedPlaces=Place::join('saved_places', function ($join) {
                  $join->on('places.id', '=', 'saved_places.pid');
              })
              ->where('saved_places.user_id','=',$userId)
              ->get(['places.id','Address','longitude','latitude','pType','subType','postCode','uCode', 'area','city','contact_person_phone','contact_person_name']);
               return $savedPlaces->toJson();
          }
          //Add Favorite Place
          public function authAddFavoritePlace(Request $request)
          {
              $user = JWTAuth::parseToken()->authenticate();
              $userId = $user->id;
              $saved = new SavedPlace;
              $saved->user_id = $userId; //user who is adding a place to his/her favorite
              $code = $request->barikoicode; // place is
              $getPid=Place::where('uCode','=',$code)->first();
              $pid=$getPid->id;
              $saved->pid=$pid;
              //return $pid;
              $saved->save();
              DB::table('analytics')->increment('saved_count');
              return response()->json('saved');
          }
          // Delete a place from favorite
          public function authDeleteFavoritePlace(Request $request,$bariCode)
          {
              $user = JWTAuth::parseToken()->authenticate();
              $userId = $user->id;
              $toBeDeleted=$bariCode;
              $findThePid=Place::where('uCode','=',$toBeDeleted)->first();
              $toDelete = SavedPlace::where('pid','=',$findThePid->id)->where('user_id','=',$userId)->delete();
              return response()->json('Done');
              //return $toDelete;
          }
          //generate ref code for users dosen't have the code already
          public function authRefCodeGen(){
              $user = JWTAuth::parseToken()->authenticate();
              $userId = $user->id;
              //Generate Referral Code

              $userInfo=User::where('id','=',$userId)->select('ref_code','isReferred')->first();
              $isRef_code=$userInfo->ref_code;
            //  return $isRef_code;
              if ($isRef_code==NULL){
              $length = 6;
              //exclude 0 & O;
              $characters = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
              $refCode = '';
              for ($p = 0; $p < $length; $p++) {
                  $refCode .= $characters[mt_rand(0, strlen($characters))];
              }
                User::where('id','=',$userId)->where('ref_code','=',null)->update(['ref_code'=>$refCode]);
                return new JsonResponse([
                  'message'=>'Your Referral Code:'.$refCode
                  ]);
                # code...
              }
              else{
                return new JsonResponse([
                  'message'=>'Your Referral Code:'.$isRef_code
                  ]);
              }
          }

          public function saveUserHome(Request $request,$id)
          {
            $user_id = $request->user()->id;
            DB::table('users')->where('id',$user_id)->update(['home_pid'=>$id]);
            return response()->json(['Message'=> 'Home Added']);
          }
          public function saveUserWork(Request $request,$id)
          {
            $user_id = $request->user()->id;
            DB::table('users')->where('id',$user_id)->update(['work_pid'=>$id]);
            return response()->json(['Message'=> 'Work Added']);
          }


          public function insertTags($tags)
          {
            if (DB::table('tags')->where('tags', '=', $tags)->exists()) {
              $tag=DB::table('tags')->where('tags', '=', $tags)->get(['tags']);

              return $tag;

            }else{
              $tag = new tags;
              $tag->tags = $tags;
              $tag->save();
              return $tags;
            }



          }
          public function GetTags(Request $request)
          {
            $tag = DB::table('tags')->where('tags','LIKE','%'.$request->tags.'%')->get(['tags']);

            return response()->json([
              'tags'=> $tag,
              'status' =>'200'
            ]);
          }
          public function addNewFiletoindex()
          {
            $place = DB::table('places')->orderBy('created_at','desc')->first();
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

            $index = $tnt->getIndex();

            //to insert a new document to the index
            $index->insert(['id' => '11', 'title' => 'new title', 'article' => 'new article']);
          }








}
