<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $query = Doctor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $doctors = $query->withCount('patients')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return response()->json($doctors);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $doctor = Doctor::create($validator->validated());

        return response()->json([
            'message' => 'Doctor created successfully',
            'doctor' => $doctor,
        ], 201);
    }

    public function show(Doctor $doctor)
    {
        $doctor->loadCount('patients');
        return response()->json($doctor);
    }

    public function update(Request $request, Doctor $doctor)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $doctor->update($validator->validated());

        return response()->json([
            'message' => 'Doctor updated successfully',
            'doctor' => $doctor->fresh(),
        ]);
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();

        return response()->json([
            'message' => 'Doctor deleted successfully',
        ]);
    }

    public function patients(Doctor $doctor)
    {
        $patients = $doctor->patients()
            ->with(['visits' => function ($q) {
                $q->orderBy('id', 'desc')->take(5);
            }])
            ->withCount('visits')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'doctor' => $doctor,
            'patients' => $patients,
        ]);
    }

    public function search(Request $request)
    {
        $search = $request->get('q', '');
        
        if (empty($search)) {
            return response()->json(['doctors' => []]);
        }

        $doctors = Doctor::where('name', 'like', "%{$search}%")
            ->withCount('patients')
            ->limit(10)
            ->get();

        return response()->json(['doctors' => $doctors]);
    }
}
