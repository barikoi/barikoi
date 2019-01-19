<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
/*
Route::get('/live', function () {
    return view('live');
});*/
$api = $app->make(Dingo\Api\Routing\Router::class);

$api->version('v1',  function ($api) {

      //   $api->post('/image', [
      //   'as' => 'image.singe',
      //   'uses' => 'App\Http\Controllers\ImageController@store'
      // ]);

  //start, test routes//


   $api->get('/place/get/type1/',[
     'as' => 'place.type.get1',
     'uses' => 'App\Http\Controllers\PlaceController@getPlaceType1',
   ]);
//==================BOT=========

    $api->post('/search/nearby',[
      'as' => 'bot.search.nearby',
      'uses' => 'App\Http\Controllers\SearchController@findNearby',
    ]);
    $api->post('/search/all',[
      'as' => 'bot.search.all',
      'uses' => 'App\Http\Controllers\SearchController@findAll',
    ]);

    $api->post('/search/travel',[
      'as' => 'bot.search.travel',
      'uses' => 'App\Http\Controllers\SearchController@travel',
    ]);
    $api->post('/search/food',[
      'as' => 'bot.search.food',
      'uses' => 'App\Http\Controllers\SearchController@food',
    ]);
//=============================
  $api->post('/landmarks', [
    'as' => 'nearest.landmarks',
    'uses' => 'App\Http\Controllers\LandmarkNavController@index',
  ]);
  $api->post('/landmark/nav', [
    'as' => 'nearest.landmarks',
    'uses' => 'App\Http\Controllers\LandmarkNavController@LandmarkNav',
  ]);
//Route to get client IP
  $api->get('/ip', [
    'as' => 'ip',
    'uses' => 'App\Http\Controllers\PlaceController@get_client_ip',
  ]);
//This route generate random Words
  $api->get('/word',[
    'as' => 'random.words',
    'uses' => 'App\Http\Controllers\PlaceController@word',
  ]);
//Public Tourism api
  $api->get('/ghurbokoi','App\Http\Controllers\PlaceController@tourism');
  $api->get('/paginate','App\Http\Controllers\PlaceController@shobaiTest');
  $api->get('/autocomplete','App\Http\Controllers\PlaceController@autocomplete');
  $api->get('/delivery/price','App\Http\Controllers\DeliveryKoisController@deliveryPrice');
  $api->get('/delivery/company','App\Http\Controllers\DeliveryKoisController@GetDeliveryCompany');
  $api->get('/get/place/by/type','App\Http\Controllers\PlaceController@getPlaceByType');
  $api->get('/place/get/all/subtype','App\Http\Controllers\PlaceController@getAllSubtype');
  $api->get('/tnt','App\Http\Controllers\SearchController@indextntsearch');
  $api->post('/sms/test','App\Http\Controllers\DeliveryKoisController@testsms');
  $api->get('/download/today','App\Http\Controllers\PlaceController@exportToday');
  $api->get('/download/{id}','App\Http\Controllers\PlaceController@export');
  $api->get('/range/download','App\Http\Controllers\PlaceController@exportDataIdWise');
  $api->post('insert/area','App\Http\Controllers\DataController@InsertArea');
  $api->get('find/point/area/{longitude}/{latitude}','App\Http\Controllers\DataController@FindPointInsidePolygon');
  $api->patch('update/area/{id}','App\Http\Controllers\DataController@updateArea');
  $api->get('aci','App\Http\Controllers\testController@aci');
  $api->get('fuzzysearch/','App\Http\Controllers\SearchController@TestFuzzySearch');
  $api->get('fix/data/inside/polygon','App\Http\Controllers\DataController@FixDataInsidePolygon');
  $api->get('osm/','App\Http\Controllers\testController@osm');
  //$api->get('index/','App\Http\Controllers\PlaceController@updateTntIndex');
  $api->group([
      'middleware' => 'api.throttle', 'limit' => 60, 'expires' => 1
  ], function ($api) {
  $api->post('test/search','App\Http\Controllers\SearchController@testSearch');

  $api->post('/api/search','App\Http\Controllers\SearchController@APIsearch');
  //search using BariKoi Code fofr business
  $api->get('/api/search/autocomplete/{apikey}/place','App\Http\Controllers\BusinessApiController@DeveloperAutoComplete'); //
  //$api->get('/api/search/autocomplete/test/{apikey}/place','App\Http\Controllers\BusinessApiController@testSearchthree'); //
  $api->get('/api/search/geocode/{apikey}/place/{id}','App\Http\Controllers\BusinessApiController@geocode');
  $api->get('api/search/reverse/geocode/{apikey}/place','App\Http\Controllers\BusinessApiController@reverseGeocodeNew');
  $api->get('api/search/nearby/{apikey}/{distance}/{limit}','App\Http\Controllers\BusinessApiController@reverseNearBy');
  $api->get('api/search/nearby/catagory/{apikey}/{distance}/{limit}','App\Http\Controllers\BusinessApiController@nearbyCatagorized');
  //$api->get('/api/search/nearby/{search}','App\Http\Controllers\SearchController@APInearBy');
  $api->get('/api/search/analytics','App\Http\Controllers\BusinessApiController@totalApiUser');

  $api->get('reverse/without/auth','App\Http\Controllers\PlaceController@reverseGeocode');


});

  //end, test routes//
  //barikoi pool-bot
  //bot,ride search
    $api->get('/bot/pool/ride/search', [
      'as' => 'ride.search.bot',
      'uses' => 'App\Http\Controllers\PoolRideController@indexBot'
    ]);
  // Auth/ login/ reggetister
  $api->post('/auth/register', [
    'as' => 'api.auth.register',
    'uses' => 'App\Http\Controllers\Auth\AuthController@Register',
  ]);

  $api->post('/auth/login', [
    'as' => 'api.auth.login',
    'uses' => 'App\Http\Controllers\Auth\AuthController@postLogin',
  ]);

  $api->post('admin/login', [
    'as' => 'api.admin.login',
    'uses' => 'App\Http\Controllers\Auth\AuthController@postLoginAdmin',
  ]);
  $api->post('business/login', [
    'as' => 'api.business.login',
    'uses' => 'App\Http\Controllers\Auth\AuthController@postLoginBusiness',
  ]);

  //ADN: Password Reset/email
  $api->post('/auth/password/reset',[
     'as' => 'auth.password.reset',
     'uses' => 'App\Http\Controllers\Auth\AuthController@resetPassword',
   ]);

//get all codes by device id
  $api->get('/place/get/app/{id}/',[
    'as' => 'place.get.app',
    'uses' => 'App\Http\Controllers\PlaceController@KhujTheSearchApp',
  ]);

  $api->get('/web/search/{nameorcode}',[
    'as' => 'web.searchby.nameorcode',
    'uses' => 'App\Http\Controllers\PlaceController@searchNameAndCodeWeb',
  ]);

  //App\Http\Controllers\SearchController@index
  //App\Http\Controllers\PlaceController@searchNameAndCodeWeb
/// Get place type and subtype
  $api->get('/place/get/sub/type/{type}/',[
      'as' => 'place.get.sub.type',
      'uses' => 'App\Http\Controllers\PlaceController@getPlaceSubType',
   ]);

   $api->get('/place/get/type/',[
     'as' => 'place.type.get',
     'uses' => 'App\Http\Controllers\PlaceController@getPlaceType',
   ]);
  /// Post place type and subtype
   $api->post('/place/type',[
     'as' => 'place.type',
     'uses' => 'App\Http\Controllers\PlaceController@placeType',
   ]);
   $api->post('/place/sub/type',[
     'as' => 'place.sub.type',
     'uses' => 'App\Http\Controllers\PlaceController@placeSubType',
   ]);


  // Post place addresss lon lat
    $api->post('/place/post',[
      'as' => 'place.post',
      'uses' => 'App\Http\Controllers\PlaceController@StorePlace',
    ]);
  // Post custom code
    $api->post('/place/custom/post',[
      'as' => 'place.post.custom',
      'uses' => 'App\Http\Controllers\PlaceController@StoreCustomPlace',
    ]);
    //get place by barikoicode
    $api->get('/place/get/{id}/',[
      'as' => 'place.get.byid',
      'uses' => 'App\Http\Controllers\PlaceController@KhujTheSearch',
    ]);

    $api->get('/place/get/test/{id}/',[
      'as' => 'place.get.testbyid',
      'uses' => 'App\Http\Controllers\PlaceController@KhujTheSearchTest',
    ]);
    //get all the codes admin panel
    $api->get('/place/get/',[
      'as' => 'places.get',
      'uses' => 'App\Http\Controllers\PlaceController@shobaix',
    ]);


    $api->get('/place/duplicate/{id}',[
      'as' => 'place.duplicate',
      'uses' => 'App\Http\Controllers\PlaceController@duplicate',
    ]);
    $api->delete('/place/fake',[
      'as' => 'place.fake',
      'uses' => 'App\Http\Controllers\PlaceController@fakeCatcher',
    ]);



    //update place by place code
    $api->post('/place/update/{barikoicode}',[
      'as' => 'places.update',
      'uses' => 'App\Http\Controllers\PlaceController@halnagad',
    ]);
//Get near by public places
    $api->get('/public/place/{ucode}',[
      'as' => 'place.public',
      'uses' => 'App\Http\Controllers\PlaceController@ashpash',
    ]);

    //Get near by public places by  lon lat
    // $api->get('/public/find/nearby/place/{latitude}/{longitude}',[
    //       'as' => 'place.lon.public',
    //       'uses' => 'App\Http\Controllers\PlaceController@amarashpash',
    //     ]);
    $api->get('/public/find/nearby/place/',[
      'as' => 'place.lon.public',
      'uses' => 'App\Http\Controllers\PlaceController@amarashpash',
    ]);
    $api->get('/verification/nearby/place/',[
      'as' => 'place.lon.verify',
      'uses' => 'App\Http\Controllers\PlaceController@amarashpashVerification',
    ]);
    $api->get('/verification/nearby/place/dtool',[
      'as' => 'place.lon.verify',
      'uses' => 'App\Http\Controllers\PlaceController@amarashpashVerificationDtool',
    ]);
    $api->get('/verification/nearby/place/analytics',[
      'as' => 'place.lon.verify.analytics',
      'uses' => 'App\Http\Controllers\PlaceController@amarashpashVerificationAnalytics',
    ]);

    //Get near by public places by  Name
    $api->post('/public/find',[
      'as' => 'place.searchby.name',
      'uses' => 'App\Http\Controllers\PlaceController@search',
    ]);
   //test


    //get a place from nearby list
    $api->get('/place/{bcode}',[
      'as' => 'single.place',
      'uses' => 'App\Http\Controllers\PlaceController@getListViewItem',
    ]);
    $api->get('/saved/place/get/{id}',[
      'as' => 'places.saved.get',
      'uses' => 'App\Http\Controllers\PlaceController@getSavedPlace',
    ]);

    $api->post('/saved/place/delete/{id}',[
      'as' => 'places.saved.delete',
      'uses' => 'App\Http\Controllers\PlaceController@DeleteSavedPlace',
    ]);

  $api->post('/connect/us/',[
      'as' => 'place.contact',
      'uses' => 'App\Http\Controllers\PlaceController@contactUS',
    ]);

    //full text

  /*  $api->post('/search',[
      'as' => 'search.fulltext',
      'uses' => 'App\Http\Controllers\SearchController@index',
    ]);
  */

    $api->get('/all/leaderboard/contributor',[
      'as' => 'public.leaderboard.leaderboard',
      'uses' => 'App\Http\Controllers\LeaderBoardController@ContributorLeaderBoard',
    ]);

    //Leaderboard Till Date
    $api->get('/all/leaderboard',[
      'as' => 'public.leaderboard.tilldate',
      'uses' => 'App\Http\Controllers\LeaderBoardController@indexTillDate',
    ]);

    //Leaderboard Weekly
    $api->get('/weekly/leaderboard',[
      'as' => 'public.leaderboard.weekly',
      'uses' => 'App\Http\Controllers\LeaderBoardController@indexWeekly',
    ]);

    //Leaderboard Monthly
    $api->get('/monthly/leaderboard',[
      'as' => 'public.leaderboard.monthly',
      'uses' => 'App\Http\Controllers\LeaderBoardController@indexMonthly',
    ]);





    //review
        //review-rating
    //all reviews for an address (which is a business)
      // $api->get('/reviews/{pid}',[
      //   'as' => 'all.reviews.',
      //   'uses' => 'App\Http\Controllers\ReviewController@index',
      // ]);
  /*
   */
//Contributor
   $api->get('/places/contributors/{id}','App\Http\Controllers\UserProfileController@ContributorAddedPlacesX'); // Get Places but Contributors
   $api->get('/bikerental/docs','App\Http\Controllers\testController@rentalDocs');


///================================Auth api starts ===========================================================================
    $api->get('get/custom/polygon','App\Http\Controllers\DataController@SearchInPolygon');
    $api->delete('delete/inside/polygon','App\Http\Controllers\DataController@DeleteInsidePolygon');
      $api->group(['middleware' => 'throttle:100,1'], function ($api)  {

      $api->get('geo','App\Http\Controllers\testController@NewPlace');
      $api->get('poly','App\Http\Controllers\testController@TestPolygon');


      $api->get('get/area','App\Http\Controllers\DataController@getArea');



      /*@@ data controller address
      */

    });
    $api->get('area/polygon','App\Http\Controllers\DataController@getAreaDataPolygonWise');
    $api->post('/tnt/search','App\Http\Controllers\SearchController@testSearch');
    /// This search is used in current app and every other internal search
    $api->post('/tnt/search/test','App\Http\Controllers\SearchController@testSearchthree');
    $api->post('/tnt/search/two','App\Http\Controllers\SearchController@testSearchtwo');
    $api->post('/tnt/search/admin','App\Http\Controllers\SearchController@getTntsearch');


    $api->get('get/area/by/polygon','App\Http\Controllers\DataController@getAreaByPolygon');

    $api->get('search/polygon','App\Http\Controllers\DataController@SearchInPolygon');
    $api->get('/public/find/nearby/by/catagory/noauth','App\Http\Controllers\PlaceController@amarashpashCatagorized');
    $api->group([
        'middleware' => 'api.auth',
    ], function ($api) {

      //Test Routes: with images
      //ADD a new PLACE
      $api->post('/test/auth/place/newplace',[
        'as' => 'test.api.auth.place.new',
        'uses' => 'App\Http\Controllers\PlaceController@authAddNewPlace',
      ]);
      // MAPPERS ADDING A NEW PLACE
      $api->post('/test/auth/place/newplace/mapper',[
        'as' => 'test.api.auth.place.new.mapper',
        'uses' => 'App\Http\Controllers\PlaceController@XauthAddNewPlace',
      ]);

      //add place with custom CODE
      $api->post('/test/auth/place/newplacecustom',[
        'as' => 'test.api.auth.place.newcustom',
        'uses' => 'App\Http\Controllers\PlaceController@authAddCustomPlace',
      ]);
      //Test Routes

      $api->get('/', [
          'uses' => 'App\Http\Controllers\APIController@getIndex',
          'as' => 'api.index'
        ]);
               //Refresh Token
      $api->patch('/auth/refresh', [
          'uses' => 'App\Http\Controllers\Auth\AuthController@patchRefresh',
          'as' => 'api.auth.refresh'
      ]);
      // GET USER INFORMATION
      $api->get('/auth/user', [
          'uses' => 'App\Http\Controllers\Auth\AuthController@getUser',
          'as' => 'api.auth.user'
      ]);

      //Delete Token
      $api->delete('/auth/invalidate', [
          'uses' => 'App\Http\Controllers\Auth\AuthController@deleteInvalidate',
          'as' => 'api.auth.invalidate'
      ]);

      //GET PLACES analytics
      $api->get('/analytics',[
        'as' => 'place.collection.analytics',
        'uses' => 'App\Http\Controllers\PlaceController@analytics',
      ]);

      $api->post('/auth/UpdatePass',[
        'as' => 'user.updatePass',
        'uses' => 'App\Http\Controllers\Auth\AuthController@UpdatePass',
      ]);

      //mail test route : dont use in prod
      $api->post('/auth/UpdatePass12',[
        'as' => 'user.updatePass',
        'uses' => 'App\Http\Controllers\Auth\AuthController@UpdatePass12',
      ]);

      //ADN: Show all codes for a specific Authenticated user by user_id (My Places)
      $api->get('/auth/placebyuid/paginate', [
        'uses' => 'App\Http\Controllers\PlaceController@getPlacesByUserIdPaginate',
        'as' => 'api.auth.uid.paginate'
      ]);
      // USED IN MOBILE APP and used to provide data with a limit (Dtool/Verification APP)
      $api->get('/auth/placebyuid/{deviceid}', [
        'uses' => 'App\Http\Controllers\PlaceController@getPlacesByUserDeviceId',
        'as' => 'api.auth.deviceid'
      ]);
      // Updates a places position with drag and drop! USED in Verify APP
      $api->patch('/drop/update/app/{id}',[
        'as' => 'drop.update.app',
        'uses' => 'App\Http\Controllers\PlaceController@dropEditApp',
      ]);



      //Show all places by User ID: for web mainly
      /// get places for the users! in the app and web
      $api->get('/auth/placeby/userid/', [
        'uses' => 'App\Http\Controllers\PlaceController@getPlacesByUserId',
        'as' => 'api.auth.userid'
      ]);

      /*
      @@ USER HOME AND WORK

      */
      $api->get('save/user/home/{pid}','App\Http\Controllers\PlaceController@saveUserHome');
      $api->get('save/user/work/{pid}','App\Http\Controllers\PlaceController@saveUserWork');


      /**
      @@Delivery Koi Routes
      **/
      $api->post('/order','App\Http\Controllers\DeliveryKoisController@PlaceOrder'); // Api for Placing Order
      $api->post('/order/dashboard','App\Http\Controllers\DeliveryKoisController@PlaceOrderDashBoard'); // Api for Placing Order

      // Api for Getting Order {id = order id}
      $api->patch('/order/update/{id}','App\Http\Controllers\DeliveryKoisController@updateOrder'); // Updating order {id = order id}
      $api->patch('/order/accept/{id}','App\Http\Controllers\DeliveryKoisController@AcceptOrder'); // Updating order {id = order id}
      $api->patch('/order/started/{id}','App\Http\Controllers\DeliveryKoisController@OrderOngoing'); // Order Starts {id = order id}
      $api->patch('/order/delivered/{id}','App\Http\Controllers\DeliveryKoisController@OrderDelivered'); // Order Delivered {id = order id}
      $api->patch('/order/Cancelled/{id}','App\Http\Controllers\DeliveryKoisController@OrderCancelled'); // Order Delivered {id = order id}
      $api->patch('/order/returned/{id}','App\Http\Controllers\DeliveryKoisController@OrderReturned'); // Order Delivered {id = order id}

      $api->patch('/delivery/man/location/update','App\Http\Controllers\DeliveryKoisController@DeliveryLocation'); // DeliveryManLocationUpdate

      $api->delete('/order/delete/{id}','App\Http\Controllers\DeliveryKoisController@DeleteOrder'); // Order Delivered {id = order id}
      $api->patch('/order/assign','App\Http\Controllers\DeliveryKoisController@AssignOrderByAdmin'); // assign order to drivers


      $api->get('/order/user','App\Http\Controllers\DeliveryKoisController@UserOrders'); //User ID
      $api->get('/order/delivery/man/orders','App\Http\Controllers\DeliveryKoisController@DeliveryMansOrders'); //User ID
      $api->get('/order/delivery/man/ongoing/orders','App\Http\Controllers\DeliveryKoisController@OngoingOrderByDeliveryMan'); //User ID
      $api->get('/order/delivery/man/cancelled/orders','App\Http\Controllers\DeliveryKoisController@CancelledOrderByDeliveryMan'); //User ID
      $api->get('/order/delivery/man/finished/orders','App\Http\Controllers\DeliveryKoisController@AllDeliveredOrders'); //User ID

      //Admin

      $api->get('/order/available','App\Http\Controllers\DeliveryKoisController@AvailableOrders');
      $api->get('/order/all','App\Http\Controllers\DeliveryKoisController@getAllOrder');
      $api->get('/order/all/marchent','App\Http\Controllers\DeliveryKoisController@getAllOrder');
      $api->get('/order/booked/all','App\Http\Controllers\DeliveryKoisController@getBookedOrder');
      $api->get('/order/delivered/all','App\Http\Controllers\DeliveryKoisController@getDeliveredOrder');
      $api->get('/order/cancelled/all','App\Http\Controllers\DeliveryKoisController@getCancelledOrder');
      $api->get('/order/returned/all','App\Http\Controllers\DeliveryKoisController@AllReturnedOrders');
      $api->get('/order/ongoing/all','App\Http\Controllers\DeliveryKoisController@getOngoingOrder'); // Admin get all orders
      $api->get('/order/{id}','App\Http\Controllers\DeliveryKoisController@OrderByID'); // get order by order id
      $api->get('/get/deliveryman','App\Http\Controllers\DeliveryKoisController@getDeliveryMan');
      $api->get('/get/delivery/man/location/by/company','App\Http\Controllers\DeliveryKoisController@getLocationByCompany');
      $api->get('/get/delivery/man/location/for/admin','App\Http\Controllers\DeliveryKoisController@getLocationForAdmin');

      // Logistics & Marchant
      $api->get('/logistic/analytics','App\Http\Controllers\DeliveryKoisController@logisticsAnalytics'); // get order by order id


      //============================= Delivery koi Routes Ends ===============================================
      $api->get('/notification/all','App\Http\Controllers\DeliveryKoisController@notification');




      //============================= contributors Routes  ====================================================
      $api->get('/contributor/all','App\Http\Controllers\UserProfileController@Contributors'); // Get All Contributors
      $api->get('/places/contributor/{id}','App\Http\Controllers\UserProfileController@ContributorAddedPlaces'); // Get Places but Contributors
      $api->get('/places/latest','App\Http\Controllers\UserProfileController@latest'); // Get Places but Contributors



      //============================= contributors ends here Routes  ===========================================
      //BIKE RENTAL HANDYMAMA
      $api->post('/handymama','App\Http\Controllers\testController@HandyMama');
      $api->get('/bikerental','App\Http\Controllers\testController@BikeRental');


      /**
        @@data manipulation
      **/
      //fix data from the admin (Data FIX)
      $api->get('replace','App\Http\Controllers\DataController@replace');
      $api->get('updateword','App\Http\Controllers\DataController@UpdateWordZone');
      $api->get('get/ward','App\Http\Controllers\PlaceController@getWard');
      $api->get('get/by/area','App\Http\Controllers\PlaceController@getAreaWise');
      $api->get('get/by/ward','App\Http\Controllers\PlaceController@getWardWise');
      $api->post('get/by/road','App\Http\Controllers\PlaceController@getRoadWise');



    //=================================================
      //ADN: add a new place
      ///***********
      $api->get('/public/find/nearby/auth/',[
        'as' => 'place.nearby.public',
        'uses' => 'App\Http\Controllers\PlaceController@amarashpashAuth',
      ]);
      //GET CATAGORIZED nearby DATA
      $api->get('/public/find/nearby/by/catagory','App\Http\Controllers\PlaceController@amarashpashCatagorized');
      //**********


      //search for client:app
      $api->get('/auth/search/{bcode}',[
        'as' => 'auth.app.search',
        'uses' => 'App\Http\Controllers\Auth\AuthController@AppKhujTheSearch',
      ]);

      //ADN: Update Place by Place Code or PLACE ID

      $api->post('/auth/place/update/{placeid}',[
        'as' => 'api.auth.places.update',
        'uses' => 'App\Http\Controllers\PlaceController@halnagadMyPlace',
      ]);
      $api->patch('update/place/{placeid}',[
        'as' => 'api.auth.places.update',
        'uses' => 'App\Http\Controllers\PlaceController@halnagadMyPlace',
      ]);

      //ADN:Delete place by BariKoi code or place ID
      $api->get('/auth/place/delete/{barikoicode}',[
        'as' => 'auth.places.delete',
        'uses' => 'App\Http\Controllers\PlaceController@mucheFeliMyPlace',
      ]);

    //delete place by place id()
     $api->get('/place/delete/{id}',[
       'as' => 'places.delete',
       'uses' => 'App\Http\Controllers\PlaceController@mucheFeli',
     ]);

      //ADN: Get All List of Favorite Places for Authenticated User by user_id
      $api->get('/auth/savedplacebyuid',[
        'uses' => 'App\Http\Controllers\PlaceController@getSavedPlacesByUserId',
        'as' => 'api.auth.savedplaces'
      ]);
      //ADN: Add place to favorite
      $api->post('/auth/save/place',[
        'as' => 'api.auth.places.favorite.add',
        'uses' => 'App\Http\Controllers\PlaceController@authAddFavoritePlace',
      ]);
      //ADN:remove place from favorite or from saved places
      $api->get('/auth/saved/place/delete/{barikoicode}',[
        'as' => 'api.auth.places.favorite.delete',
        'uses' => 'App\Http\Controllers\PlaceController@authDeleteFavoritePlace',
      ]);

      //Generate Ref_Code for Early Users;(22thpril Onward,Ref_Code auto generated on Registration)
      $api->get('/auth/generate/refcode/',[
        'as' => 'api.auth.generate.refcode',
        'uses' => 'App\Http\Controllers\PlaceController@authRefCodeGen',
      ]);

      //Redeem A Ref_Code
      $api->post('/auth/redeem/referrals',[
        'as' => 'api.auth.redeem.refcode',
        'uses' => 'App\Http\Controllers\Auth\AuthController@authRedeemRefCode',
      ]);

      //ADN: busines_key generate
      $api->POST('/auth/business/keygen/',[
        'as' => 'auth.business.keygen.email',
        'uses' => 'App\Http\Controllers\BusinessApiController@generateApiKey',
      ]);
     //Current Active key and Number of Total Key
      $api->get('/auth/business/CurrentActiveKey/',[
        'as' => 'business.current.active.key',
        'uses' => 'App\Http\Controllers\BusinessApiController@getCurrentActiveKey',
      ]);

      //Add Business Details by Business User
      $api->post('/auth/business/AddDescription/{pid}',[
        'as' => 'business.add.description',
        'uses' => 'App\Http\Controllers\BusinessApiController@AddBusinessDescription',
      ]);

      //Show Business Details by Business User
      $api->get('/auth/business/ShowDescription/{pid}',[
        'as' => 'business.show.description',
        'uses' => 'App\Http\Controllers\BusinessApiController@ShowBusinessDescription',
      ]);

      //Get Users List for the admin
      $api->get('/auth/admin/userlist',[
        'as' => 'admin.listusers',
        'uses' => 'App\Http\Controllers\Auth\AuthController@getUserList',
      ]);

      //user info for the admin
      $api->get('/user/{id}',[
        'as' => 'user.individual',
        'uses' => 'App\Http\Controllers\UserManagementController@index',
      ]);

      //user profile details:Client
      $api->get('/user/profile/details',[
        'as' => 'user.profile.details',
        'uses' => 'App\Http\Controllers\UserProfileController@index'
      ]);
//User profile  picture
      $api->post('/user/profile/photo',[
        'as' => 'upolad.profile.pic',
        'uses' => 'App\Http\Controllers\UserProfileController@storeProPic',
      ]);
      // GET USER PROFILE PICTURE
      $api->get('/user/profile/photo',[
        'as' => 'show.profile.pic',
        'uses' => 'App\Http\Controllers\UserProfileController@showProPic',
      ]);

      $api->delete('/user/profile/photo',[
        'as' => 'remove.profile.pic',
        'uses' => 'App\Http\Controllers\UserProfileController@destroyProPic',
      ]);
      //places added by user
      $api->get('/user/{id}/places',[
        'as' => 'places.by.user',
        'uses' => 'App\Http\Controllers\UserManagementController@show',
      ]);
      //delete place
      $api->delete('/user/{id}/place',[
        'as' => 'delete.place',
        'uses' => 'App\Http\Controllers\UserManagementController@destroy',
      ]);
      //update place
      $api->post('/user/{id}/place',[
        'as' => 'update.place',
        'uses' => 'App\Http\Controllers\UserManagementController@update',
      ]);
      //review-rating
      //save a review+rating for a place id


      //search from app
      $api->get('/app/search/{nameorcode}',[
        'as' => 'app.searchby.nameorcode',
        'uses' => 'App\Http\Controllers\PlaceController@searchNameAndCodeApp',
      ]);

      //rewards controller starts
      /// rewards list for users
      $api->get('/rewards', [
        'as' => 'rewards.list',
        'uses' => 'App\Http\Controllers\RewardsController@index',
      ]);
      // request to redeem reward points
      $api->post('/reward', [
        'as' => 'rewards.redeem.request',
        'uses' => 'App\Http\Controllers\RewardsController@store',
      ]);
      //get the list of reward request/queue
      $api->get('/rewardhistory', [
        'as' => 'rewards.redeem.request',
        'uses' => 'App\Http\Controllers\RewardsController@show',
      ]);
      #User Part Ends#


    /*

  Admin Part Starts
  */
      $api->get('/get/count',[
        'as' => 'place.count',
        'uses' => 'App\Http\Controllers\PlaceController@count',
      ]);
      //show the requested queue , for Admin
      $api->get('/admin/requests', [
        'as' => 'rewards.request.queue',
        'uses' => 'App\Http\Controllers\RewardRequestQueueController@index',
      ]);

      $api->get('/admin/requests/{id}', [
        'as' => 'rewards.request.queue.item',
        'uses' => 'App\Http\Controllers\RewardRequestQueueController@show',
      ]);

      $api->post('/admin/requests/update/{id}', [
        'as' => 'rewards.request.queue',
        'uses' => 'App\Http\Controllers\RewardRequestQueueController@update',
      ]);


      //reward management controller(admin) starts
      //show reward list
      $api->get('/admin/rewards', [
        'as' => 'all.rewards',
        'uses' => 'App\Http\Controllers\RewardsManagementController@index',
      ]);
      //show a reward item
      $api->get('/admin/reward/{id}', [
        'as' => 'reward.details',
        'uses' => 'App\Http\Controllers\RewardsManagementController@show',
      ]);
      //store new reward from admin
      $api->post('/admin/reward', [
        'as' => 'add.reward',
        'uses' => 'App\Http\Controllers\RewardsManagementController@store',
      ]);
      //update a reward item
      $api->post('/admin/reward/{id}', [
        'as' => 'update.reward',
        'uses' => 'App\Http\Controllers\RewardsManagementController@update',
      ]);
      //delete a reward item
      $api->delete('/admin/reward/{id}', [
        'as' => 'reward.delete',
        'uses' => 'App\Http\Controllers\RewardsManagementController@destroy',
      ]);



      $api->post('/image/delete', [
        'as' => 'image.delete',
        'uses' => 'App\Http\Controllers\ImageController@destroyImage'
      ]);

      $api->post('/image', [
        'as' => 'image.single',
        'uses' => 'App\Http\Controllers\ImageController@store'
      ]);
      //search log
      $api->get('/searchlog', [
        'as' => 'search.log',
        'uses' => 'App\Http\Controllers\SearchController@searchLog',
      ]);






      /**
       * Routes for resource bike
       */
      $api->get('bike', 'App\Http\Controllers\BikesController@all');
      $api->get('bike/{id}', 'App\Http\Controllers\BikesController@get');
      $api->post('bike', 'App\Http\Controllers\BikesController@add');
      $api->put('bike/{id}', 'App\Http\Controllers\BikesController@put');
      $api->delete('bike/{id}', 'App\Http\Controllers\BikesController@remove');
      $api->patch('bike/availability/{id}', 'App\Http\Controllers\BikesController@BikeAvailability');
      /*
      * Routes for Rent
      */
      $api->get('rent/analytics', 'App\Http\Controllers\RentsController@rentDashboard');
      $api->get('rent', 'App\Http\Controllers\RentsController@rentAll');
      $api->get('rent/{id}', 'App\Http\Controllers\RentsController@rentDetails'); //Get rent details for individual rent request admin
      $api->get('rent/by/user','App\Http\Controllers\RentsController@ShowRentRequestByUserId'); // Show individual users rent history
      $api->patch('rent/change/status/{id}','App\Http\Controllers\RentsController@changeRentStatus');// change rent status
      $api->post('rent', 'App\Http\Controllers\RentsController@Index'); // Create a rent request
      $api->delete('rent/{id}','App\Http\Controllers\RentsController@DeleteRent');

      /*
      REVERSE GEOCODING
      */
      $api->get('reverse','App\Http\Controllers\PlaceController@reverseGeocode');
      $api->get('reverse/for/addition','App\Http\Controllers\PlaceController@reverseGeocodeForAddressAddition');
      $api->get('reverse/dash','App\Http\Controllers\PlaceController@reverseGeocodeNew');


      /* Routes for resource trade-license
      Auth applied
       */
      $api->get('tradelicense', 'App\Http\Controllers\TradeLicenseController@getAllTradeLicenseInfo');
      $api->get('tradelicense/{id}', 'App\Http\Controllers\TradeLicenseController@GetPlaceWithTradeLicense');
      $api->post('tradelicense', 'App\Http\Controllers\TradeLicenseController@store');
      $api->put('tradelicense/{id}', 'App\Http\Controllers\TradeLicenseController@put');
      $api->delete('tradelicense/{id}', 'App\Http\Controllers\TradeLicenseController@remove');
      /**
       * Routes for resource g-p-x
       */
      $api->get('gpx', 'App\Http\Controllers\GPXESController@read');
      $api->get('gpx/{id}', 'App\Http\Controllers\GPXESController@readUserId');
      $api->post('gpx', 'App\Http\Controllers\GPXESController@create');
      $api->put('g-p-x/{id}', 'GPXESController@put');
      $api->delete('g-p-x/{id}', 'GPXESController@remove');

      //---------------------Business User Routes-----------------------//
        //Register a Business user: from "Admin panel" or "SignUp as a Business feature"
        $api->post('/business/register', [
            'as' => 'api.business.register',
            'uses' => 'App\Http\Controllers\BusinessApiController@RegisterBusinessUser',
        ]);
        //pass the encoded API-KEY alog with post request
        $api->post('/business/StorePlace/{apikey}',[
          'as' => 'business.store.place',
          'uses' => 'App\Http\Controllers\BusinessApiController@addPlaceByBusinessUser',
        ]);


        //places added by a business user
        $api->get('/business/PlacesAdded/{apikey}',[
          'as' => 'business.added.place',
          'uses' => 'App\Http\Controllers\BusinessApiController@PlacesAddedByBusinessUser',
        ]);

        //places added by a business user
        $api->get('/business/UpdatePlace/{apikey}',[
          'as' => 'business.update.place',
          'uses' => 'App\Http\Controllers\BusinessApiController@UpdatePlaceByBusinessUser',
        ]);

        $api->get('/developer/analytics','App\Http\Controllers\BusinessApiController@TokenAnalysis');

        //----------------------------- Business API ENDs----------------------------------

        $api->patch('/drop/update/{id}',[
          'as' => 'drop.update',
          'uses' => 'App\Http\Controllers\PlaceController@dropEdit',
        ]);


    });
});
