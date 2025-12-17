<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Restaurant;

class RestaurantAdminController extends Controller
{
    // GET /api/v3/restaurants
    public function index()
    {
        $data = Restaurant::latest()->paginate(25);
        return response()->json(['ok'=>true,'data'=>$data]);
    }

    // GET /api/v3/restaurants/{id}
    public function show(int $id)
    {
        $row = Restaurant::with('owners.user')->findOrFail($id);
        return response()->json(['ok'=>true,'data'=>$row]);
    }

    // PATCH /api/v3/restaurants/{id}
    public function update(Request $r, int $id)
    {
        $row = Restaurant::findOrFail($id);
        $payload = $r->validate([
            'name' => ['nullable','string'],
            'city' => ['nullable','string'],
            'slug' => ['nullable','string'],
            'is_active' => ['nullable','boolean'],
        ]);
        $row->fill($payload);
        $row->save();
        return response()->json(['ok'=>true,'data'=>$row]);
    }

    // DELETE /api/v3/restaurants/{id}
    public function destroy(int $id)
    {
        $row = Restaurant::findOrFail($id);
        $row->delete();
        return response()->json(['ok'=>true,'message'=>'Restaurant deleted']);
    }
}
