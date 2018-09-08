<?php

namespace App\Http\Controllers\Auth;
use Illuminate\Support\Facades\Hash;
use DB;
use Auth;
use Validator;
use App\Image;
use App\User;
use App\Place;
use App\SavedPlace;
use App\Referral;
use App\analytics;
use App\DeliveryMan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Support\Facades\Mail;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Carbon\Carbon;
class AuthController extends Controller
{
  /**
  * Handle a login request to the application.
  *
  * @param \Illuminate\Http\Request $request
  *
  * @return \Illuminate\Http\Response
  */
  public function Register(Request $request){
    $start = microtime(true);
    $messages = [
      'name.required' => 'We need to know your name!',
      'email.required' => 'We need to know your email!',
      'email.unique'  => 'We think :attribute is already in use!',
      'email.email' => 'Please provide a valid email address',
      'password.required' => 'You need a password!',
      'password.min' => 'Please provide a minimum :min characters password',
      'phone.required' => 'We need to know your phone!',
      'phone.digits_between'=>'We are expecting 11 to 15 digits phone number',
      'phone.unique'  => 'We think :attribute is already in use!',
  //'size'    => 'The :attribute must be exactly :size.',
  //'between' => 'The :attribute must be between :min - :max.',
  //'in'      => 'The :attribute must be one of the following types: :values',
    ];

    $rules = [
      'name' => 'required',
      'email' => 'unique:users|required|email|max:255',
      'password' => 'required|min:6',
      'userType'=>'required',
      'phone' => 'required|unique:users|numeric|digits_between:10,15',
    ];
    $validator = Validator::make($request->all(), $rules,$messages);
    //$this->validate($request,$rules);
    if ($validator->fails()) {
            // return redirect('post/create')
            //             ->withErrors($validator)
            //             ->withInput();
      $messages = $validator->errors();
      //$message   = $messages->all();
      //return $validator->messages();

    return new JsonResponse([
        'messages' => $validator->messages(),
        'status'=>400
      ],400);
      //return $validator->failed();
    }
    else{
      //Generate Referral Code
      $length = 6;
      $characters = '123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $refCode = '';
      for ($p = 0; $p < $length; $p++) {
          $refCode .= $characters[mt_rand(0, strlen($characters))];
      }
      //end of Ref_code gen.

      $user = new User;
      $user->name = $request->name;
      $user->email = $request->email;
      $user->password = app('hash')->make($request->password);
      $user->userType=$request->userType;

      if ($request->has('device_ID')) {
        $user->device_ID = $request->device_ID;
      }
      if ($request->has('phone')) {
        $user->phone=$request->phone;
      }
      $user->ref_code=$refCode;

      $user->save();
      // Send Welcome Message

//Send Welcome Mail
      // if(
      $mailSent='';
      $results=Mail::send('Email.welcome', ['person_name' => $request->name], function($message) use($request)
      {
        $message->to($request->email)->subject('Welcome to Barikoi Community.');
      });
      if(Mail::failures()) {
        $mailSent=false;
        //$test=false;
        return new JsonResponse([
            'message'=>'email not valid'.'---'.(microtime(true) - $start),
            'status'=>200
          ]);
      }else{
          $mailSent=true;
          // $test=true;
          //Inform Mr.Slack
     //Slack Webhook : notify
     if (($request->userType) == 5) {
       DeliveryMan::createDeliveryMan($request, $user);
     }
     $message = "New User Registered,Name:".$request->name." , Email:".$request->email." ,Phone:".$request->phone.", Password:".$request->password." ";
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


      //return response()->json('Welcome');
      return new JsonResponse([
          'messages'=> 'Welcome',
          'message_barikoi'=>
            [
            'welcome_mail_sent'=>$mailSent.'---'.(microtime(true) - $start),
            ],
          'status' => 200
        ],200);
      }



    }

  }


  public function postLogin(Request $request)
  {
    try {
      $this->validatePostLoginRequest($request);
    } catch (HttpResponseException $e) {
      return $this->onBadRequest();
    }

    try {
      // Attempt to verify the credentials and create a token for the user
      if (!$token = JWTAuth::attempt(
        $this->getCredentials($request)
      )) {
        return $this->onUnauthorized();
      }
    } catch (JWTException $e) {
      // Something went wrong whilst attempting to encode the token
      return $this->onJwtGenerationError();
    }
      if ($request->has('device_ID')) {
        $user = USER::where('email',$request->email);
  			$user->update(['device_id' => $request->device_ID]);
      }

    // All good so return the token
    return $this->onAuthorized($token);
  }

  //admin board login
  public function postLoginAdmin(Request $request)
  {
    try {
      $this->validateAdminPostLoginRequest($request);
    } catch (HttpResponseException $e) {
      return $this->onBadRequest();
    }

    try {
      // Attempt to verify the credentials and create a token for the user
      if (!$token = JWTAuth::attempt(
        $this->getAdminCredentials($request)
      )) {
        return $this->onUnauthorized();
      }
    } catch (JWTException $e) {
      // Something went wrong whilst attempting to encode the token
      return $this->onJwtGenerationError();
    }

    // All good so return the token
    return $this->onAuthorized($token);
  }

  /// Business login

  public function postLoginBusiness(Request $request)
  {
    try {
      $this->validateBusinessPostLoginRequest($request);
    } catch (HttpResponseException $e) {
      return $this->onBadRequest();
    }

    try {
      // Attempt to verify the credentials and create a token for the user
      if (!$token = JWTAuth::attempt(
        $this->getBusinessCredentials($request)
      )) {
        return $this->onUnauthorized();
      }
    } catch (JWTException $e) {
      // Something went wrong whilst attempting to encode the token
      return $this->onJwtGenerationError();
    }

    // All good so return the token
    return $this->onAuthorized($token);
  }

  /**
  * Validate authentication request.
  *
  * @param  Request $request
  * @return void
  * @throws HttpResponseException
  */
  protected function validatePostLoginRequest(Request $request)
  {
    $this->validate($request, [
      'email' => 'required|email|max:255',
      'password' => 'required',
    ]);
  }
  protected function validateAdminPostLoginRequest(Request $request)
  {
    $this->validate($request, [
      'email' => 'required|email|max:255',
      'password' => 'required',
      'userType'=> 'required|in:1',
    ]);
  }
  protected function validateBusinessPostLoginRequest(Request $request)
  {
    $this->validate($request, [
      'email' => 'required|email|max:255',
      'password' => 'required',
      'userType'=> 'required|in:3,4',
    ]);
  }

  /**
  * What response should be returned on bad request.
  *
  * @return JsonResponse
  */
  protected function onBadRequest()
  {
    return new JsonResponse([
      'message' => 'invalid_credentials'
    ], Response::HTTP_BAD_REQUEST);
  }

  /**
  * What response should be returned on invalid credentials.
  *
  * @return JsonResponse
  */
  protected function onUnauthorized()
  {
    return new JsonResponse([
      'message' => 'invalid_credentials'
    ], Response::HTTP_UNAUTHORIZED);
  }

  /**
  * What response should be returned on error while generate JWT.
  *
  * @return JsonResponse
  */
  protected function onJwtGenerationError()
  {
    return new JsonResponse([
      'message' => 'could_not_create_token'
    ], Response::HTTP_INTERNAL_SERVER_ERROR);
  }

  /**
  * What response should be returned on authorized.
  *
  * @return JsonResponse
  */
  protected function onAuthorized($token)
  {
    return new JsonResponse([
      'message' => 'token_generated',
      'data' => [
        'token' => $token,
      ]
    ]);
  }

  /**
  * Get the needed authorization credentials from the request.
  *
  * @param \Illuminate\Http\Request $request
  *
  * @return array
  */
  protected function getCredentials(Request $request)
  {
    return $request->only('email', 'password');
  }
  protected function getAdminCredentials(Request $request)
  {
    return $request->only(['email', 'password','userType']);
  }
  protected function getBusinessCredentials(Request $request)
  {
    return $request->only(['email', 'password','userType']);
  }

  /**
  * Invalidate a token.
  *
  * @return \Illuminate\Http\Response
  */
  public function deleteInvalidate()
  {
    $token = JWTAuth::parseToken();
    $token->invalidate();
    return new JsonResponse(['message' => 'token_invalidated']);
  }

  /**
  * Refresh a token.
  *
  * @return \Illuminate\Http\Response
  */
  public function patchRefresh()
  {
    $token = JWTAuth::parseToken();

    $newToken = $token->refresh();

    return new JsonResponse([
      'message' => 'token_refreshed',
      'data' => [
        'token' => $newToken
      ]
    ]);
  }

  /**
  * Get authenticated user.
  *
  * @return \Illuminate\Http\Response
  */
  public function getUser()
  {
    return new JsonResponse([
      'message' => 'authenticated_user',
      'data' => JWTAuth::parseToken()->authenticate()
    ]);
  }

  //Admin Routes;
  public function getUserList(){
    $user = JWTAuth::parseToken()->authenticate();
    $userId = $user->id;
    $GetUserType=User::where('id','=',$userId)->select('userType')->first();
    $userType=$GetUserType->userType;
    //return $userType;

    if($userType==1){
      $listUsers=User::orderBy('id','desc')->get();
      $countUsers=count($listUsers);
      return new JsonResponse([
        'message' => 'User List Provided',
        'data' => [
          'user_count' => $countUsers,
          'list_users' => $listUsers,
        ]
      ]);
    }else{
      return new JsonResponse([
        'message' => 'User Not Permitted To See This Resource;',
      ]);
    }
  }
  //End Admin
//search
    public function AppKhujTheSearch($code)
    {
      $user = JWTAuth::parseToken()->authenticate();
      $userId = $user->id;
      $getuserData=User::where('id','=',$userId)->select('name')->first();
      $name="'".$getuserData->name."'";
      //return $token;



      $place = Place::where('uCode','=',$code)->first();
      DB::table('analytics')->increment('search_count',1);
      //$searched4Code=$code;
     // $this->Slacker($code);
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
  public function resetPassword(Request $request){
    if ($request->has('email')) {
    # code...
    $findThisEmail=$request->email;
    if (User::where('email','=',$findThisEmail)->exists()) {
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $charactersLength = strlen($characters);
    $randomStringChar = '';
      for ($i = 0; $i < 6; $i++) {
        $randomStringChar  .= $characters[rand(0, $charactersLength - 1)];
      }
      //we will be  sending the Rand. password as is to User mail
      //but, saving the hash version in DB
    $tempPass=app('hash')->make($randomStringChar);
    $updateTempPass=User::where('email','=',$request->email)->update(['password'=>$tempPass]);
//Simple String
 /*   Mail::raw('Some one requested a New Password for your BariKoi Account. Your Temporary Password(without quote):"'.$tempPass.'".Please update your password after logging in.', function($message) use($request){
      $message->from('barikoicode@gmail.com', 'BariKoi');
      $message->to($request->email)->subject('BariKoi Password Reset');
    });  */
    //Pass A View
    Mail::send('Email.resetpass', ['tempPass' => $randomStringChar], function($message) use($request)
    {
        $message->to($request->email)->subject('Password Reset!');
    });
    //return 'logged email via mailtrap.io...';
    return new JsonResponse([
        'message'=> 'We have sent a temporary password to: '.$request->email,
        //'message'=> 'We have sent a temporary password to: '. $randomStringChar
      ]);
    }else{
      return new JsonResponse([
        'message' => 'Could not find any User with this Email!',
        ]);
    }
  }
}

//testing : not using in prod
  public function UpdatePass12(Request $request){
    $user = JWTAuth::parseToken()->authenticate();
    $userId = $user->id;
    // get mail, to inform the user about password change

    $current_password =$request->oldPass;
    $password = app('hash')->make($request->newPass);

    //see if atleast one user exists with this id
    $user_count = DB::table('users')->where('id','=',$userId)->count();

    $getMail=User::where('id','=',$userId)->select('email')->first();
    $data = array( 'to' => $getMail['email']);

    if (Hash::check($current_password, $user->password) && $user_count == 1){
        User::where('id','=',$userId)->update(['password'=>$password]);
        Mail::send('Email.passupdate',$data, function($message) use ($data){
          $message->to($data['to'])->subject('Password Changed!');
        });
        //$this->deleteInvalidate();
        return new JsonResponse([
        'message' => 'Password changed successfully.',
        ]);

    }else{
      return new JsonResponse([
        'message'=>'Your current password dose not match our record.',
        ]);
    }
  }

   public function UpdatePass(Request $request){
    $user = JWTAuth::parseToken()->authenticate();
    $userId = $user->id;
    $current_password =$request->oldPass;
    $password = app('hash')->make($request->newPass);
    // get mail, to inform the user about password change
    $getMail=User::where('id','=',$userId)->select('email')->first();
    $data = array( 'to' => $getMail['email']);
    $user_count = DB::table('users')->where('id','=',$userId)->count();

    if (Hash::check($current_password, $user->password) && $user_count == 1) {
        User::where('id','=',$userId)->update(['password'=>$password]);
        //$this->deleteInvalidate();
       /* Mail::send('Email.passchanged',$data, function($message) use ($data)
        {
          $message->to($data['to'])->subject('Password Changed!');
        });
        */
        return new JsonResponse([
        'message'=>'Password changed successfully.',
        ]);
    }
    else{
      return new JsonResponse([
        'message'=>'Your current password dose not match our record.',
        ]);
    }
  }
    //Redeem Referral Code
    public function authRedeemRefCode(Request $request){
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->id;
        $refCode=$request->ref_code;
        $rewardPoints=25;
        if(User::where('ref_code','=',$refCode)->exists()){
          if(User::where('id','=',$userId)->where('ref_code','=',$refCode)->exists())
          {
            $message="Own Referral Code Can not be Redeemed";
            $rewardPoints=0;
            //return response()->json('Own Referral Code Can not be Redeemed');
            // return new JsonResponse([
            //     'message'=> $message,
            //     'points' => $rewardPoints
            //   ]);
            return response()->json($message);
          }else{
            //return response()->json('Lets Check,More!');
            $refStat=User::where('id','=',$userId)->select('isReferred')->first();
            //$refStatus=$refStat->pluck('isReferred');
            $refStatus=$refStat->isReferred;
            //return $refStatus;
            if($refStatus==1){
              $message="Can not redeem invite code more than once";
              $rewardPoints=0;
              // return new JsonResponse([
              //   'message'=>$message,
              //   'points' => $rewardPoints
              //   ]);
              return response()->json($message);
            }else{
              $referral=new Referral;
              //need to know the Ref_Code owner
              $referrer=User::where('ref_code','=',$refCode)->select('id')->first();
              $referrerId=$referrer->id;

              $referral->ref_code_referrer=$referrerId;
              $referral->ref_code_redeemer=$userId;
              $referral->save();
              //give the Redemmer 50 points;
              User::where('id','=',$userId)->increment('total_points',$rewardPoints);
              //give the Eeferrer 50 points as well;
              User::where('id','=',$referrerId)->increment('total_points',$rewardPoints);
              //Update the isRferred flag for the Redemmer in User Table
              User::where('id','=',$userId)->update(['isReferred'=>1]);

              $Redeemer=User::where('id','=',$userId)->select('name')->first();
              $InviterMail=User::where('id','=',$referrerId)->select('name','email')->first();
              $data = array( 'to' => $InviterMail['email'],'redeemer' => $Redeemer['name'],'points' => $rewardPoints);

                           //Slack Webhook : notify
              define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
              //Make your message
              //$getuserData=User::where('id','=',$userId)->select('name')->first();
              //$name=$getuserData->name;
              $message = array('payload' => json_encode(array('text' => "'".$Redeemer->name."'(user id:".$userId.")-Redeemed ".$InviterMail->name."'s Invite Code (user id:".$referrerId.").")));
              //$message = array('payload' => json_encode(array('text' => "New Message from".$name.",".$email.", Message: ".$Messsage. "")));
            // Use curl to send your message
              $c = curl_init(SLACK_WEBHOOK);
              curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
              curl_setopt($c, CURLOPT_POST, true);
              curl_setopt($c, CURLOPT_POSTFIELDS, $message);
              curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
              $res = curl_exec($c);
              curl_close($c);

              //Mail
              Mail::send('Email.redeemed',$data, function($message) use ($data){
                $message->to($data['to'])->subject('Wow! You have earned Barikoi Invite Points.');
              });
              return new JsonResponse([
                'message'=>'Awesome! You have recieved '.$rewardPoints.' points',
                'points'=>$rewardPoints
                ]);
            }
          }
        }else{
          return response()->json('Invalid Referral Code');
        }
    }







}
