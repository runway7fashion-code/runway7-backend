<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;

class CountryController extends Controller
{
    public function index()
    {
        return response()->json(
            Country::active()->ordered()->get(['name', 'code', 'phone', 'flag'])
        );
    }
}
