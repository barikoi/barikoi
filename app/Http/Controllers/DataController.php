<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use DB;
use App\Place;
class DataController extends Controller {

  /*
  @@ area wise divide search
  */
    public function getArea()
    {
      $area = DB::select("SELECT id, name FROM Area order by name ASC");
      return response()->json(['area' => $area]);
    }
    public function getAreaByPolygon()
    {
      $area = DB::select("SELECT  id, name,  ST_AsGeoJSON(area) FROM Area order by name ASC");
      return response()->json(['area' => $area]);
    }
    // Insert polygon
    public function insertArea(Request $request)
    {
      $insert = DB::select("INSERT INTO Area (area, name) VALUES (GEOMFROMTEXT('POLYGON(($request->area))'),'$request->name')");

      return response()->json(['Message' => 'Inserted'],200);
    }
    public function updateArea(Request $request,$id)
    {
      $insert = DB::select("UPDATE Area SET area = GEOMFROMTEXT('POLYGON(($request->area))') WHERE id = '$id'");
      return response()->json(['Message' => 'Polygon updated'],200);
    }

    public function getAreaDataPolygonWise(Request $request)
    {
      if ($request->has('subType')) {
        $subtype = $request->subType;
      }else {
        $subtype = 'bkash';
      }
      if ($request->has('area')) {
        $area = $request->area;
      }
      else {
        $area = 'Baridhara DOHS';
      }
      if ($subtype=='all') {
        $places = DB::select("SELECT id, Address, area, subType, pType, longitude,latitude, uCode,user_id,created_at,updated_at,astext(location) FROM places WHERE st_within(location,(select area from Area where name='$area') )");
      }
      else {
        $places = DB::select("SELECT id, Address, area, subType, pType, longitude,latitude, uCode,user_id,created_at,updated_at,astext(location) FROM places WHERE st_within(location,(select area from Area where name='$area') ) and subType LIKE '%$subtype%'");


      }
          return response()->json([
              'Total' => count($places),
              'places'=> $places
            ]);

    }
    // search data by polygon
    public function SearchInPolygon(Request $request)
    {

      if ($request->has('area')) {
        $area = $request->area;
      //  $area = 'ST_GeomFromText(POLYGON(('$area')))'
      }


    //  $places = DB::select("SELECT id, Address, subType, pType, longitude,latitude,uCode, astext(location) FROM places_2 WHERE st_within(location,(select area from Area where name='$area') ) and Address LIKE '%$address%' LIMIT 5");
      $places = DB::select("SELECT id, Address, area,subType, pType, longitude,latitude,uCode, user_id,created_at,updated_at,astext(location) FROM placesf WHERE st_within(location,ST_GeomFromText('POLYGON(($area))'))");

        return response()->json([
            'Total' => count($places),
            'places'=> $places
          ]);

    }




  /*
    @@ fix data spelling mistake
  */
      public function UpdateWordZone(Request $request)
      {
        $place = Place::where($request->param, 'LIKE', '%'.$request->data.'%')->update([$request->updateField => $request->ward]);

        return response()->json('Updated');
      }
      public function replace(Request $request)
      {
        DB::table('places')->update(['Address' => DB::raw("REPLACE(Address, '".$request->x."', '".$request->y."')")]);
        DB::table('places_last_cleaned')->update(['Address' => DB::raw("REPLACE(Address, '".$request->x."', '".$request->y."')")]);
        DB::table('placesf')->update(['Address' => DB::raw("REPLACE(Address, '".$request->x."', '".$request->y."')")]);
        return response()->json('ok');
      }

      public function dataFix()
      {
        DB::select("SELECT Address, area, REPLACE(Address, 'Road 103', 'Bir Uttam Shamsul Alam Avenue') from places WHERE Address LIKE '%Road 103%' AND area = 'Kakrail'");
      }

      public function FixDataInsidePolygon(Request $request)
      {
        $polygon =$request->polygon;
        //$address = $request->address;

        $places = DB::select("UPDATE places SET Address = REPLACE(Address, '".$request->x."', '".$request->y."') WHERE st_within(location,(GeomFromText('POLYGON(($polygon))')) )");//and Address LIKE '%$address%'
        $places = DB::select("UPDATE places_last_cleaned SET Address = REPLACE(Address, '".$request->x."', '".$request->y."') WHERE st_within(location,(GeomFromText('POLYGON(($polygon))')) )");//and Address LIKE '%$address%'
        $places = DB::select("UPDATE placesf SET Address = REPLACE(Address, '".$request->x."', '".$request->y."') WHERE st_within(location,(GeomFromText('POLYGON(($polygon))')) )");//and Address LIKE '%$address%'

          return response()->json([
              'Message' => 'Updated'
            ]);
      }

      public function FindPointInsidePolygon($lon,$lat)
      {


          $area = DB::select("SELECT name FROM Area WHERE ST_Contains(Area.area, GEOMFROMTEXT('POINT($lon $lat)'))");

          return response()->json($area);


      }

      // search data by polygon
      public function DeleteInsidePolygon(Request $request)
      {

        if ($request->has('area')) {
          $area = $request->area;
          $places = DB::select("DELETE FROM places WHERE st_within(location,ST_GeomFromText('POLYGON(($area))'))");

        }
        elseif($request->has('pType') &&  $request->has('area')) {
          $pType = $request->pType;
          $area = $request->area;
          $places = DB::select("DELETE FROM places WHERE st_within(location,ST_GeomFromText('POLYGON(($area))')) (AND pType = '$request->type')");

        }
        else{
          $user_id = $request->user_id;
          $places = DB::select("DELETE FROM places WHERE st_within(location,ST_GeomFromText('POLYGON(($area))')) (AND pType = '$request->type' AND user_id='$user_id')");

        }

      //  $places = DB::select("SELECT id, Address, subType, pType, longitude,latitude,uCode, astext(location) FROM places_2 WHERE st_within(location,(select area from Area where name='$area') ) and Address LIKE '%$address%' LIMIT 5");

          return response()->json([
              'Total' => count($places),
              'places'=> $places
            ]);

      }
      public function getWardFromMap()
      {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', 'https://map.barikoi.xyz:8070/api/show/ward');
        //preg_match_all('!\d+!', $str->Address, $matches);
        $res = $res->getBody();
        $data = json_decode( $res, true );
        return $data;
      }
      public function getZoneFromMap()
      {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', 'https://map.barikoi.xyz:8070/api/zone');
        //preg_match_all('!\d+!', $str->Address, $matches);
        $res = $res->getBody();
        $data = json_decode( $res, true );
        return $data;
      }

      /*
      Transfer data to new column

      */

      public function MergeColumn(Request $request)
      {
        $table = $request->table;
        $index = $request->index;
        $field = $request->field;
        //DB::select("UPDATE places_copy SET location =  GeomFromText(CONCAT('POINT(',longitude, ' ', latitude,')'))");
        //DB::select("UPDATE places_3 SET new_address = CONCAT(Address,", ", area)")
        DB::select("ALTER TABLE places_copy MODIFY location GEOMETRY NOT NULL");
        DB::select("ALTER TABLE '$table' ADD '$index' INDEX('$field') ");
      }

}
