<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Place;
use App\User;
use App\PlaceType;
use App\PlaceSubType;
use App\analytics;
use App\SavedPlace;
use App\Image;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Http\Response;

class LandmarkNavController extends Controller
{
    protected function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000){
        $latFrom = deg2rad($latitudeFrom);    // convert from degrees to radians
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }
    //$from = "Więckowskiego 72, Łódź";
    // $to = "Gazowa 1, Łódź";

    // $from = urlencode($from);
    // $to = urlencode($to);

    // $data = file_get_contents("http://maps.googleapis.com/maps/api/distancematrix/json?origins=$from&destinations=$to&language=en-EN&sensor=false");
    // $data = json_decode($data);

    // $time = 0;
    // $distance = 0;

    // foreach($data->rows[0]->elements as $road) {
    //     $time += $road->duration->value;
    //     $distance += $road->distance->value;
    // }

    // echo "To: ".$data->destination_addresses[0];
    // echo "<br/>";
    // echo "From: ".$data->origin_addresses[0];
    // echo "<br/>";
    // echo "Time: ".$time." seconds";
    // echo "<br/>";
    // echo "Distance: ".$distance." meters";
    //https://maps.googleapis.com/maps/api/distancematrix/json?origins=41.43206,-81.38992|-33.86748,151.20699&destinations=San+Francisco|Victoria+BC&mode=bicycling&language=fr-FR&key=AIzaSyB8YUPTo99oths5C0CJeEBl99pfghiZjDI
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function haversine(Request $request)
    {
        $latA=$request->latitudeA;
        $lonA=$request->longitudeA;
        $latB=$request->latitudeB;
        $lonB=$request->longitudeB;
//for google matrix
        $latF = urlencode($latA);
        $lonF =urldecode($lonA);
        $latT = urlencode($latB);
        $lonT =urldecode($lonB);
        $res=$this->haversineGreatCircleDistance($latA,$lonA,$latB,$lonB);
//https://maps.googleapis.com/maps/api/distancematrix/json?origins=23.80691158060759,90.35649910569191&destinations=23.81927384906965,90.42719610035418&language=en-EN&key=AIzaSyB8YUPTo99oths5C0CJeEBl99pfghiZjDI
        //http://maps.googleapis.com/maps/api/distancematrix/json?origins=23.80691158060759,90.35649910569191&destinations=23.81927384906965,90.4271961003541&language=en-EN&sensor=false
        //$data = file_get_contents("http://maps.googleapis.com/maps/api/distancematrix/json?origins=$from&destinations=$to&language=en-EN&sensor=false");
        // $data = file_get_contents("http://maps.googleapis.com/maps/api/distancematrix/json?origins=$latF,$lonF&destinations=$latT,$lonT&language=en-EN&sensor=false");
        // $data = json_decode($data);
        // $time = 0;
        // $distance = 0;

        // foreach($data->rows[0]->elements as $road) {
        //     $time += $road->duration->value;
        //     $distance += $road->distance->value;
        // }
        // echo "To: ".$data->destination_addresses[0];
        // echo "<br/>";
        // echo "From: ".$data->origin_addresses[0];
        // echo "<br/>";
        //echo "haversine greate circle: ".($res/1000) ." km";
        // echo "<br/>";
        // echo "google distance matrix";
        // echo "<br/>";
        // echo "Time: ".round($time/60,2) ." minutes";
        // echo "<br/>";
        // echo "Distance: ".round($distance/1000,2) ." km";

        return round($res/1000,5,PHP_ROUND_HALF_UP);
    }
    public function index(Request $request){
        $latA=$request->latitudeA;
        $lonA=$request->longitudeA;
        $latB=$request->latitudeB;
        $lonB=$request->longitudeB;
        $dist=$this->haversine($request);
        $resultA = DB::table('places_3')
                  ->select(DB::raw('*, ((ACOS(SIN('.$latA.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$latA.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lonA.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
                  //->where('pType','=','Landmark')
                  ->where('subType','=','Bus Stand')
                  ->having('distance','<',round($dist/2,6,PHP_ROUND_HALF_UP))
                  ->orderBy('distance')
                  ->limit(1)
                  ->get();
       // $resultAlat=$resultA['latitude'];
       // $resultAlon=$resultA['longitude'];
          $resA=json_decode(json_encode($resultA),true);
          $lonBusfromA=$resA[0]['longitude'];
          $latBusfromA=$resA[0]['latitude'];

          $nrstLandMarkA = DB::table('places_3')
                  ->select(DB::raw('*, ((ACOS(SIN('.$latA.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$latA.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lonA.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
                  ->where('pType','=','Landmark')
                  //->orWhere('subType','=','Bus Stand')
                  ->having('distance','<',round($dist/4,6,PHP_ROUND_HALF_UP))
                  ->orderBy('distance')
                  ->limit(1)
                  ->get();

        $resultB = DB::table('places_3')
                  ->select(DB::raw('*, ((ACOS(SIN('.$latB.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$latB.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lonB.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
                  //->where('pType','=','Landmark')
                  ->where('subType','=','Bus Stand')
                  ->having('distance','<',round($dist/2,6,PHP_ROUND_HALF_UP))
                  ->orderBy('distance')
                  ->limit(1)
                  ->get();
          $resB=json_decode(json_encode($resultB),true);
          $lonBusfromB=$resB[0]['longitude'];
          $latBusfromB=$resB[0]['latitude'];
          $nrstLandMarkB = DB::table('places_3')
                  ->select(DB::raw('*, ((ACOS(SIN('.$latB.' * PI() / 180) * SIN(latitude * PI() / 180) + COS('.$latB.' * PI() / 180) * COS(latitude * PI() / 180) * COS(('.$lonB.' - longitude) * PI() / 180)) * 180 / PI()) * 60 * 1.1515 * 1.609344) as distance'))
                  ->where('pType','=','Landmark')
                  //->orWhere('subType','=','Bus Stand')
                  ->having('distance','<',round($dist/4,6,PHP_ROUND_HALF_UP))
                  ->orderBy('distance')
                  ->limit(1)
                  ->get();

          //print $dist;
          return New  JsonResponse([
                "haver_distance" => $dist,
                "halfway" => round($dist/2,6,PHP_ROUND_HALF_UP),
                "transA" => $resA,
                "nearest_landmark_from_transA"=>$nrstLandMarkA,
                "transB" => $resultB,
                "nearest_landmark_from_transB"=>$nrstLandMarkB,
            ],200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function LandmarkNav(Request $request)
    {
      $latA=$request->latitudeA;
      $lonA=$request->longitudeA;
      $latB=$request->latitudeB;
      $lonB=$request->longitudeB;
      $distance = 0.5;

      $StartToLandmark = DB::select("SELECT id, ST_Distance_Sphere(Point($lonA,$latA), location) as distance, longitude,latitude,Address,city,area,pType,subType, uCode,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lonA+($distance/111)), ($latA+($distance/111))),
        Point(($lonA-($distance/111)), ($latA-($distance/111)))
      ), location ) AND pType = 'Landmark'
      ORDER BY distance LIMIT 1");

      $resA=json_decode(json_encode($StartToLandmark),true);
      $DistanceFromStartToLandmark=$resA[0]['distance'];
      $LongitudeLandmark=$resA[0]['longitude'];
      $LatitudeLandmark=$resA[0]['latitude'];

      $LandmarkToBusStand = DB::select("SELECT id, ST_Distance_Sphere(Point($LongitudeLandmark,$LatitudeLandmark), location) as distance, longitude,latitude,Address,city,area,pType,subType, uCode,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($LongitudeLandmark+($distance/111)), ($LatitudeLandmark+($distance/111))),
        Point(($LongitudeLandmark-($distance/111)), ($LatitudeLandmark-($distance/111)))
      ), location ) AND subType = 'Bus Stand'
      ORDER BY distance LIMIT 1");

      $StartToBusStand = DB::select("SELECT id, ST_Distance_Sphere(Point($lonA,$latA), location) as distance, longitude,latitude,Address,city,area,pType,subType, uCode,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lonA+($distance/111)), ($latA+($distance/111))),
        Point(($lonA-($distance/111)), ($latA-($distance/111)))
      ), location ) AND subType = 'Bus Stand'
      ORDER BY distance LIMIT 1");

      $resB=json_decode(json_encode($StartToBusStand),true);
      $DistanceFromStartToBustand=$resB[0]['distance'];


      $EndToBusStand = DB::select("SELECT id, ST_Distance_Sphere(Point($lonA,$latA), location) as distance, longitude,latitude,Address,city,area,pType,subType,uCode,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lonB+($distance/111)), ($latB+($distance/111))),
        Point(($lonB-($distance/111)), ($latB-($distance/111)))
      ), location ) AND subType = 'Bus Stand'
      ORDER BY distance LIMIT 1");

      $EndToLandmark = DB::select("SELECT id, ST_Distance_Sphere(Point($lonB,$latB), location) as distance, longitude,latitude,Address,city,area,pType,subType, uCode,ST_AsText(location)
      FROM places
      WHERE ST_Contains( ST_MakeEnvelope(
        Point(($lonB+($distance/111)), ($latB+($distance/111))),
        Point(($lonB-($distance/111)), ($latB-($distance/111)))
      ), location ) AND pType = 'Landmark'
      ORDER BY distance LIMIT 1");

      $TotalDistanceOfBusLandmark = $DistanceFromStartToBustand+$DistanceFromStartToLandmark;

      $res=$this->haversineGreatCircleDistance($latA,$lonA,$latB,$lonB);
      if ($TotalDistanceOfBusLandmark<$res) {
        return New  JsonResponse([
              "Total Strightline Distance" => $res/1000,
              "halfway" => round($res/2,6,PHP_ROUND_HALF_UP),
              "Start To Landmark" => $StartToLandmark,
              "Landmark to Bus Stand"=>$LandmarkToBusStand,
              "Bus stand to End" => $EndToBusStand,
              "Landmark to End"=>$EndToLandmark,
          ],200);
        /*return response()->json(['Start To Landmark' => $StartToLandmark,'Landmark to Bus Stand'=>$LandmarkToBusStand,

        //'Distance from Start to Landmark'=>$DistanceFromStartToLandmark,
        //'Distance from Start to Bus Stand '=>$DistanceFromStartToBustand,
        'End To Bus Stand' => $EndToBusStand,
        'End To Landmark'=> $EndToLandmark,
        'Distance Start to End' =>  $res/1000
      ]);*/
      }
      else {
        return response()->json([
          'Message' => 'You can just walk or take a rickshaw'
      ]);
    }

    }
}
