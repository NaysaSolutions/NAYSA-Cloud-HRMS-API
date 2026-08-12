<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SecureController extends Controller
{
    public function store(Request $req)
    {
        // business logic (already on tenant connection)
        return response()->json(['status' => 'saved']);
    }
}
