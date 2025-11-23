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

        $oldName = $doctor->name;
        $newName = $validator->validated()['name'];
        
        // Check if name is actually changing
        if ($oldName === $newName) {
            return response()->json([
                'message' => 'Doctor name unchanged',
                'doctor' => $doctor,
            ]);
        }

        // Get count of patients that will be affected
        $affectedPatientsCount = Patient::where('doctor_id', $oldName)->count();
        
        // Check if user confirmed the update (for frontend confirmation)
        if ($request->has('confirmed') && $request->confirmed === true) {
            // Update the doctor's name
            $doctor->update($validator->validated());
            
            // Update all patients with the old doctor name to use the new name
            if ($affectedPatientsCount > 0) {
                Patient::where('doctor_id', $oldName)
                    ->update(['doctor_id' => $newName]);
            }
            
            return response()->json([
                'message' => 'Doctor updated successfully. ' . $affectedPatientsCount . ' patients updated.',
                'doctor' => $doctor->fresh(),
                'affected_patients_count' => $affectedPatientsCount,
            ]);
        } else {
            // Return confirmation required response
            return response()->json([
                'message' => 'Confirmation required',
                'confirmation_required' => true,
                'old_name' => $oldName,
                'new_name' => $newName,
                'affected_patients_count' => $affectedPatientsCount,
                'confirmation_message' => "Are you sure you want to update doctor name from '{$oldName}' to '{$newName}'? This will update {$affectedPatientsCount} patients associated with this doctor.",
            ], 200);
        }
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();

        return response()->json([
            'message' => 'Doctor deleted successfully',
        ]);
    }

    public function patients(Doctor $doctor, Request $request)
    {
        try {
            // Get pagination parameters
            $perPage = $request->get('per_page', 10); // Default 10 per page
            $page = $request->get('page', 1);
            
            // Get filter parameters
            $labNoFilter = $request->get('lab_no', '');
            $attendanceDateFilter = $request->get('attendance_date', '');
            $deliveryDateFilter = $request->get('delivery_date', '');
            
            // Get all patients first (we need to filter after transformation)
            $patientsQuery = $doctor->patients()
                ->withCount('visits')
                ->orderBy('id', 'desc');
            
            // Get all patients (we'll paginate after filtering)
            $allPatients = $patientsQuery->get();

            // Transform collection
            $transformedPatients = $allPatients->map(function ($patient) {
                // Same transformation logic as before
                \Log::info("Patient {$patient->id} ({$patient->name}) - lab={$patient->lab}, phone={$patient->phone}");

                // Try to get lab number from lab requests if patient doesn't have one
                $labNumbers = [];
                if ($patient->lab) {
                    $labNumbers = [$patient->lab];
                } else {
                    // Try to get from lab requests
                    $labRequests = \App\Models\LabRequest::where('patient_id', $patient->id)->get();
                    if ($labRequests->count() > 0) {
                        $labNumbers = $labRequests->pluck('lab_no')->filter()->unique()->values()->toArray();
                    }
                }

                // Try to get financial data from patient record itself
                $patientTotalPaid = 0;
                $patientTotalAmount = 0;
                if (isset($patient->amount_paid) && $patient->amount_paid) {
                    $patientTotalPaid = (float)$patient->amount_paid;
                }
                if (isset($patient->total_amount) && $patient->total_amount) {
                    $patientTotalAmount = (float)$patient->total_amount;
                }

                // Try to get number of samples from patient record
                $numberOfSamples = 0;
                if (isset($patient->number_of_samples) && $patient->number_of_samples) {
                    $numberOfSamples = (int)$patient->number_of_samples;
                }

                // Initialize with basic data
                $result = [
                    'id' => $patient->id,
                    'name' => $patient->name ?? '',
                    'phone' => $patient->phone ?? '',
                    'whatsapp_number' => $patient->whatsapp_number ?? null,
                    'gender' => $patient->gender ?? '',
                    'visits_count' => $patient->visits_count ?? 0,
                    'total_paid' => $patientTotalPaid,
                    'total_amount' => $patientTotalAmount,
                    'remaining_balance' => $patientTotalAmount - $patientTotalPaid,
                    'total_tests' => $numberOfSamples,
                    'lab_numbers' => $labNumbers,
                    'attendance_dates' => [],
                    'delivery_dates' => [],
                    'visits' => [],
                ];

                // Try to get additional data if possible
                try {
                    // Get visits for this patient
                    $visits = \App\Models\Visit::where('patient_id', $patient->id)
                        ->orderBy('id', 'desc')
                        ->take(5)
                        ->get();

                    \Log::info("Patient {$patient->id} ({$patient->name}) - Found {$visits->count()} visits");

                    if ($visits->count() > 0) {
                        // Calculate financial data from visits
                        $totalPaid = 0;
                        $totalAmount = 0;
                        foreach ($visits as $visit) {
                            \Log::info("Visit {$visit->id}: amount_paid={$visit->amount_paid}, total_amount={$visit->total_amount}, final_amount={$visit->final_amount}");
                            
                            // Try different field names for amount paid
                            $paid = $visit->amount_paid ?? $visit->paid_amount ?? 0;
                            if ($paid) {
                                $totalPaid += (float)$paid;
                            }
                            
                            // Try different field names for total amount
                            $amount = $visit->total_amount ?? $visit->final_amount ?? $visit->amount ?? 0;
                            if ($amount) {
                                $totalAmount += (float)$amount;
                            }
                        }
                        
                        \Log::info("Patient {$patient->id} - Calculated from visits: total_paid={$totalPaid}, total_amount={$totalAmount}");
                        
                        // Add visit data to patient data (don't replace if patient has data)
                        $result['total_paid'] = max($result['total_paid'], $totalPaid);
                        $result['total_amount'] = max($result['total_amount'], $totalAmount);
                        $result['remaining_balance'] = $result['total_amount'] - $result['total_paid'];

                        // Try to get number of samples from visit metadata
                        $visitSamples = 0;
                        foreach ($visits as $visit) {
                            if (isset($visit->metadata) && $visit->metadata) {
                                $metadata = is_string($visit->metadata) ? json_decode($visit->metadata, true) : ($visit->metadata ?? []);
                                if (isset($metadata['number_of_samples'])) {
                                    $visitSamples = max($visitSamples, (int)$metadata['number_of_samples']);
                                } elseif (isset($metadata['patient_data']['number_of_samples'])) {
                                    $visitSamples = max($visitSamples, (int)$metadata['patient_data']['number_of_samples']);
                                }
                            }
                        }
                        
                        // Use the maximum number of samples found
                        $result['total_tests'] = max($result['total_tests'], $visitSamples);

                        // Get attendance and delivery dates from visit metadata
                        $attendanceDates = [];
                        $deliveryDates = [];
                        
                        foreach ($visits as $visit) {
                            // Get attendance date from visit_date
                            if ($visit->visit_date) {
                                $attendanceDates[] = $visit->visit_date;
                            }
                            
                            // Try to get delivery date from metadata or calculate it
                            $deliveryDate = null;
                            if (isset($visit->metadata) && $visit->metadata) {
                                $metadata = is_string($visit->metadata) ? json_decode($visit->metadata, true) : ($visit->metadata ?? []);
                                if (isset($metadata['delivery_date'])) {
                                    $deliveryDate = $metadata['delivery_date'];
                                } elseif (isset($metadata['patient_data']['delivery_date'])) {
                                    $deliveryDate = $metadata['patient_data']['delivery_date'];
                                }
                            }
                            
                            // If no delivery date in metadata, try to get from patient record
                            if (!$deliveryDate && isset($patient->delivery_date)) {
                                $deliveryDate = $patient->delivery_date;
                            }
                            
                            // If still no delivery date, use attendance date + 1 day as fallback
                            if (!$deliveryDate && $visit->visit_date) {
                                $deliveryDate = date('Y-m-d', strtotime($visit->visit_date . ' +1 day'));
                            }
                            
                            if ($deliveryDate) {
                                $deliveryDates[] = $deliveryDate;
                            }
                        }
                        
                        $result['attendance_dates'] = array_unique($attendanceDates);
                        $result['delivery_dates'] = array_unique($deliveryDates);

                        $result['visits'] = $visits;
                    } else {
                        \Log::info("Patient {$patient->id} ({$patient->name}) - No visits found");
                    }
                } catch (\Exception $e) {
                    // If there's an error getting additional data, just use basic data
                    \Log::warning('Error getting additional patient data: ' . $e->getMessage());
                }

                return $result;
            });

            // Apply filters after transformation
            $filteredPatients = $transformedPatients->filter(function ($patient) use ($labNoFilter, $attendanceDateFilter, $deliveryDateFilter) {
                // Filter by lab number
                if (!empty($labNoFilter)) {
                    $labMatch = false;
                    if (!empty($patient['lab_numbers'])) {
                        foreach ($patient['lab_numbers'] as $labNo) {
                            if (stripos($labNo, $labNoFilter) !== false) {
                                $labMatch = true;
                                break;
                            }
                        }
                    }
                    if (!$labMatch) {
                        return false;
                    }
                }

                // Filter by attendance date
                if (!empty($attendanceDateFilter)) {
                    $attendanceMatch = false;
                    if (!empty($patient['attendance_dates'])) {
                        foreach ($patient['attendance_dates'] as $attendanceDate) {
                            if (date('Y-m-d', strtotime($attendanceDate)) === $attendanceDateFilter) {
                                $attendanceMatch = true;
                                break;
                            }
                        }
                    }
                    if (!$attendanceMatch) {
                        return false;
                    }
                }

                // Filter by delivery date
                if (!empty($deliveryDateFilter)) {
                    $deliveryMatch = false;
                    if (!empty($patient['delivery_dates'])) {
                        foreach ($patient['delivery_dates'] as $deliveryDate) {
                            if (date('Y-m-d', strtotime($deliveryDate)) === $deliveryDateFilter) {
                                $deliveryMatch = true;
                                break;
                            }
                        }
                    }
                    if (!$deliveryMatch) {
                        return false;
                    }
                }

                return true;
            })->values();

            // Get total count before pagination (for accurate pagination info)
            $totalFiltered = $filteredPatients->count();
            
            // Apply pagination to filtered results
            $offset = ($page - 1) * $perPage;
            $paginatedPatients = $filteredPatients->slice($offset, $perPage)->values();
            
            // Calculate pagination info
            $lastPage = $totalFiltered > 0 ? ceil($totalFiltered / $perPage) : 1;
            $from = $totalFiltered > 0 ? $offset + 1 : 0;
            $to = min($offset + $perPage, $totalFiltered);

            return response()->json([
                'doctor' => $doctor,
                'patients' => $paginatedPatients,
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $totalFiltered,
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in DoctorController patients method: ' . $e->getMessage(), [
                'doctor_id' => $doctor->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch patients data',
                'message' => $e->getMessage()
            ], 500);
        }
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
