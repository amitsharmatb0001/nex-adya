<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AdminService;

class AdminController extends Controller
{
    public function me(Request $request) {
        return response()->json(['ok'=>true,'data'=>$request->user()->only(['id','name','email','role'])]);
    }

    public function dashboard(Request $request, AdminService $service) {
        $summary = $service->overview();
        return response()->json(['ok'=>true,'data'=>$summary]);
    }
}
