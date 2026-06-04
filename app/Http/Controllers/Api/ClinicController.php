<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClinicRequest;
use App\Http\Resources\ClinicResource;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function current(Request $request)
    {
        return response()->json([
            'data' => new ClinicResource($request->user()->clinic),
        ]);
    }

    public function update(UpdateClinicRequest $request)
    {
        $clinic = $request->user()->clinic;

        $clinic->update($request->validated());

        return response()->json([
            'data' => new ClinicResource($clinic->fresh()),
        ]);
    }
}
