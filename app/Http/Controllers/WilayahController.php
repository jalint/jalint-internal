<?php

namespace App\Http\Controllers;

use Aliziodev\IndonesiaRegions\Facades\Indonesia;
use Illuminate\Http\Request;

class WilayahController extends Controller
{
    public function index()
    {
        $provinces = Indonesia::getForSelect();

        return response()->json($provinces);
    }

    public function getCity(Request $request)
    {
        $cities = Indonesia::getForSelect($request->id);

        return response()->json($cities);
    }
}
