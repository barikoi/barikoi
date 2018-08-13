<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\GPX;
use App\User;
class GPXESController extends Controller {


    public function create(Request $request)
    {
      $id = $request->user()->id;
      $gpx = GPX::create($request->all()+['user_id'=>$request->user()->id]);
      return response()->json(['Message'=>$request->user()->id]);
    }

}
