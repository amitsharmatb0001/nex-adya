<?php

namespace App\Http\Controllers\Api\V1\Address;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    // GET /api/v1/addresses
    public function index(Request $request)
    {
        $rows = Address::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json(['ok'=>true, 'data'=>$rows]);
    }

    // POST /api/v1/addresses
    public function store(StoreAddressRequest $request)
    {
        $userId = $request->user()->id;

        $addr = DB::transaction(function () use ($request, $userId) {
            $data = $request->validated();
            $data['user_id'] = $userId;

            $addr = Address::create($data);

            if (!empty($data['is_default'])) {
                Address::where('user_id', $userId)
                    ->where('id', '!=', $addr->id)
                    ->update(['is_default' => false]);
            }

            return $addr;
        });

        return response()->json(['ok'=>true, 'data'=>$addr], 201);
    }

    // PATCH /api/v1/addresses/{id}
    public function update(UpdateAddressRequest $request, int $id)
    {
        $userId = $request->user()->id;
        $addr = Address::where('user_id', $userId)->findOrFail($id);

        DB::transaction(function () use ($request, $addr, $userId) {
            $data = $request->validated();
            $addr->update($data);

            if (array_key_exists('is_default', $data) && $data['is_default']) {
                Address::where('user_id', $userId)
                    ->where('id', '!=', $addr->id)
                    ->update(['is_default' => false]);
            }
        });

        return response()->json(['ok'=>true, 'data'=>$addr->fresh()]);
    }

    // DELETE /api/v1/addresses/{id}
    public function destroy(Request $request, int $id)
    {
        $userId = $request->user()->id;
        $addr = Address::where('user_id', $userId)->findOrFail($id);

        // Optional guard: don't allow deleting the only/default address
        $addr->delete();

        return response()->json(['ok'=>true]);
    }
}
