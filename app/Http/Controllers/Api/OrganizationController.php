<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $organizations = $query->withCount('patients')
            ->latest()
            ->paginate(15);

        return response()->json($organizations);
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

        $organization = Organization::create($validator->validated());

        return response()->json([
            'message' => 'Organization created successfully',
            'organization' => $organization,
        ], 201);
    }

    public function show(Organization $organization)
    {
        $organization->loadCount('patients');
        return response()->json($organization);
    }

    public function update(Request $request, Organization $organization)
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

        $oldName = $organization->name;
        $newName = $validator->validated()['name'];
        
        // Check if name is actually changing
        if ($oldName === $newName) {
            return response()->json([
                'message' => 'Organization name unchanged',
                'organization' => $organization,
            ]);
        }

        // Get count of patients that will be affected
        $affectedPatientsCount = Patient::where('organization_id', $oldName)->count();
        
        // Check if user confirmed the update (for frontend confirmation)
        if ($request->has('confirmed') && $request->confirmed === true) {
            // Update the organization's name
            $organization->update($validator->validated());
            
            // Update all patients with the old organization name to use the new name
            if ($affectedPatientsCount > 0) {
                Patient::where('organization_id', $oldName)
                    ->update(['organization_id' => $newName]);
            }
            
            return response()->json([
                'message' => 'Organization updated successfully. ' . $affectedPatientsCount . ' patients updated.',
                'organization' => $organization->fresh(),
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
                'confirmation_message' => "Are you sure you want to update organization name from '{$oldName}' to '{$newName}'? This will update {$affectedPatientsCount} patients associated with this organization.",
            ], 200);
        }
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully',
        ]);
    }

    public function patients(Organization $organization)
    {
        try {
            // Get patients with basic data first - simplified approach
            $patients = $organization->patients()
                ->withCount('visits')
                ->orderBy('id', 'desc')
                ->get();

            // Transform patients to include basic data first
            $transformedPatients = $patients->map(function ($patient) {
                // Debug patient data
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
                                $metadata = json_decode($visit->metadata, true);
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
                                $metadata = json_decode($visit->metadata, true);
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

            return response()->json([
                'organization' => $organization,
                'patients' => $transformedPatients,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in OrganizationController patients method: ' . $e->getMessage(), [
                'organization_id' => $organization->id,
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
            return response()->json(['organizations' => []]);
        }

        $organizations = Organization::where('name', 'like', "%{$search}%")
            ->withCount('patients')
            ->limit(10)
            ->get();

        return response()->json(['organizations' => $organizations]);
    }
}
