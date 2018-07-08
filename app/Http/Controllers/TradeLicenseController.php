<?php namespace App\Http\Controllers;

use App\TradeLicense;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;
class TradeLicenseController extends Controller{



    public function store(Request $request)
    {
      $licenseInfo = TradeLicense::create($request->all());
    //  $licenseInfo->pid = $request->pid;
    //  $licenseInfo->save();
      return response()->json(['Message' => 'Inserted']);
    }

    public function getAllTradeLicenseInfo()
    {
      $place = DB::table('places')->join('tradelicense','places.id','=','tradelicense.pid')
      ->select('places.*','tradelicense.*')
      ->get();
      return response()->json(['Data' => $place]);
    }

    public function getOneTradeLicenseInfo($id)
    {
      $licenseInfo = TradeLicense::where('pid','=',$id)->get();
      return response()->json(['Data' => $licenseInfo]);

    }
    public function GetPlaceWithTradeLicense($id)
    {
      $place = DB::table('places')->join('tradelicense','places.id','=','tradelicense.pid')
      ->select('places.*','tradelicense.*')->where('places.uCode','=',$id)->orWhere('places.id','=',$id)
      ->get();
      return response()->json(['Data' => $place]);
    }

}
