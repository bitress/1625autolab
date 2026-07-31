<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleCatalogController extends Controller
{
    public function listMakes()
    {
        $makes = DB::table('vehicle_makes')->orderBy('name', 'asc')->get(['id', 'name']);

        return response()->json(['makes' => $makes]);
    }

    public function createMake(Request $request)
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Invalid name.'], 422);
        }

        $id = DB::table('vehicle_makes')->insertGetId([
            'name' => $name,
        ]);

        return response()->json(['id' => $id, 'name' => $name], 201);
    }

    public function updateMake(Request $request, int $id)
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Invalid name.'], 422);
        }

        DB::table('vehicle_makes')->where('id', $id)->update(['name' => $name]);

        return response()->json(['id' => $id, 'name' => $name]);
    }

    public function deleteMake(int $id)
    {
        DB::table('vehicle_makes')->where('id', $id)->delete();

        return response()->json(['message' => 'deleted']);
    }

    public function listModels(Request $request)
    {
        $make = trim((string) $request->query('make'));
        if ($make === '') {
            return response()->json(['models' => []]);
        }

        $models = DB::table('vehicle_models')
            ->join('vehicle_makes', 'vehicle_models.make_id', '=', 'vehicle_makes.id')
            ->where('vehicle_makes.name', $make)
            ->orderBy('vehicle_models.name', 'asc')
            ->select('vehicle_models.id', 'vehicle_models.make_id as makeId', 'vehicle_models.name')
            ->get();

        return response()->json(['models' => $models]);
    }

    public function createModel(Request $request)
    {
        $makeId = (int) $request->input('makeId');
        $name = trim((string) $request->input('name'));

        if ($makeId <= 0 || $name === '') {
            return response()->json(['success' => false, 'message' => 'makeId and name are required.'], 422);
        }

        $id = DB::table('vehicle_models')->insertGetId([
            'make_id' => $makeId,
            'name' => $name,
        ]);

        return response()->json(['id' => $id, 'makeId' => $makeId, 'name' => $name], 201);
    }

    public function updateModel(Request $request, int $id)
    {
        $makeId = (int) $request->input('makeId');
        $name = trim((string) $request->input('name'));

        if ($makeId <= 0 || $name === '') {
            return response()->json(['success' => false, 'message' => 'makeId and name are required.'], 422);
        }

        DB::table('vehicle_models')->where('id', $id)->update([
            'make_id' => $makeId,
            'name' => $name,
        ]);

        return response()->json(['id' => $id, 'makeId' => $makeId, 'name' => $name]);
    }

    public function deleteModel(int $id)
    {
        DB::table('vehicle_models')->where('id', $id)->delete();

        return response()->json(['message' => 'deleted']);
    }
}
