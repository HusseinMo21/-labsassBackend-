@extends('layouts.app')

@section('title', 'Create Enhanced Report')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-medical-alt"></i> Create Enhanced Report
                    </h3>
                </div>

                <form method="POST" action="{{ route('reports.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-info-circle"></i> Basic Information
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="lab_no" class="form-label">Lab Number</label>
                                    <input type="text" class="form-control" id="lab_no" name="lab_no" 
                                           value="{{ old('lab_no') }}" placeholder="Auto-generated if empty">
                                </div>

                                <div class="mb-3">
                                    <label for="nos" class="form-label">Patient Number (NOS)</label>
                                    <input type="text" class="form-control" id="nos" name="nos" 
                                           value="{{ old('nos') }}">
                                </div>

                                <div class="mb-3">
                                    <label for="reff" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="reff" name="reff" 
                                           value="{{ old('reff') }}">
                                </div>

                                <div class="mb-3">
                                    <label for="type" class="form-label">Report Type</label>
                                    <select class="form-select" id="type" name="type">
                                        <option value="">Select Type</option>
                                        <option value="pathology" {{ old('type') == 'pathology' ? 'selected' : '' }}>Pathology</option>
                                        <option value="hematology" {{ old('type') == 'hematology' ? 'selected' : '' }}>Hematology</option>
                                        <option value="biochemistry" {{ old('type') == 'biochemistry' ? 'selected' : '' }}>Biochemistry</option>
                                        <option value="microbiology" {{ old('type') == 'microbiology' ? 'selected' : '' }}>Microbiology</option>
                                        <option value="immunology" {{ old('type') == 'immunology' ? 'selected' : '' }}>Immunology</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="normal" {{ old('priority') == 'normal' ? 'selected' : '' }}>Normal</option>
                                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                                        <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Patient Information -->
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-user"></i> Patient Information
                                </h5>

                                <div class="mb-3">
                                    <label for="patient_id" class="form-label">Patient</label>
                                    <select class="form-select" id="patient_id" name="patient_id">
                                        <option value="">Select Patient</option>
                                        @foreach($patients as $patient)
                                        <option value="{{ $patient->id }}" {{ old('patient_id') == $patient->id ? 'selected' : '' }}>
                                            {{ $patient->name }} ({{ $patient->phone }})
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="lab_request_id" class="form-label">Lab Request</label>
                                    <select class="form-select" id="lab_request_id" name="lab_request_id">
                                        <option value="">Select Lab Request</option>
                                        @foreach($labRequests as $request)
                                        <option value="{{ $request->id }}" {{ old('lab_request_id') == $request->id ? 'selected' : '' }}>
                                            {{ $request->lab_no }} - {{ $request->patient->name ?? 'N/A' }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="age" class="form-label">Age</label>
                                    <input type="text" class="form-control" id="age" name="age" 
                                           value="{{ old('age') }}">
                                </div>

                                <div class="mb-3">
                                    <label for="sex" class="form-label">Gender</label>
                                    <select class="form-select" id="sex" name="sex">
                                        <option value="">Select Gender</option>
                                        <option value="male" {{ old('sex') == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('sex') == 'female' ? 'selected' : '' }}>Female</option>
                                        <option value="other" {{ old('sex') == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="report_date" class="form-label">Report Date</label>
                                    <input type="date" class="form-control" id="report_date" name="report_date" 
                                           value="{{ old('report_date', date('Y-m-d')) }}">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <!-- Clinical Information -->
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-stethoscope"></i> Clinical Information
                                </h5>

                                <div class="mb-3">
                                    <label for="clinical" class="form-label">Clinical History</label>
                                    <textarea class="form-control" id="clinical" name="clinical" rows="4" 
                                              placeholder="Enter clinical history and symptoms...">{{ old('clinical') }}</textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="nature" class="form-label">Nature of Specimen</label>
                                    <textarea class="form-control" id="nature" name="nature" rows="3" 
                                              placeholder="Describe the nature of the specimen...">{{ old('nature') }}</textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="recieving" class="form-label">Receiving Date</label>
                                    <input type="text" class="form-control" id="recieving" name="recieving" 
                                           value="{{ old('recieving') }}" placeholder="e.g., 2025-01-15">
                                </div>
                            </div>

                            <!-- Examination Results -->
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-microscope"></i> Examination Results
                                </h5>

                                <div class="mb-3">
                                    <label for="gross" class="form-label">Gross Examination</label>
                                    <textarea class="form-control" id="gross" name="gross" rows="4" 
                                              placeholder="Enter gross examination findings...">{{ old('gross') }}</textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="micro" class="form-label">Microscopic Examination</label>
                                    <textarea class="form-control" id="micro" name="micro" rows="4" 
                                              placeholder="Enter microscopic examination findings...">{{ old('micro') }}</textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="discharge" class="form-label">Discharge Date</label>
                                    <input type="text" class="form-control" id="discharge" name="discharge" 
                                           value="{{ old('discharge') }}" placeholder="e.g., 2025-01-16">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <!-- Conclusion & Recommendation -->
                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-clipboard-check"></i> Conclusion
                                </h5>

                                <div class="mb-3">
                                    <label for="conc" class="form-label">Conclusion</label>
                                    <textarea class="form-control" id="conc" name="conc" rows="4" 
                                              placeholder="Enter the final conclusion...">{{ old('conc') }}</textarea>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h5 class="text-primary mb-3">
                                    <i class="fas fa-lightbulb"></i> Recommendation
                                </h5>

                                <div class="mb-3">
                                    <label for="reco" class="form-label">Recommendation</label>
                                    <textarea class="form-control" id="reco" name="reco" rows="4" 
                                              placeholder="Enter recommendations for treatment/follow-up...">{{ old('reco') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Reports
                            </a>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Create Report
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-fill patient information when patient is selected
document.getElementById('patient_id').addEventListener('change', function() {
    const patientId = this.value;
    if (patientId) {
        // You can add AJAX call here to fetch patient details
        // and auto-fill age, sex, etc.
    }
});

// Auto-fill lab request information when lab request is selected
document.getElementById('lab_request_id').addEventListener('change', function() {
    const labRequestId = this.value;
    if (labRequestId) {
        // You can add AJAX call here to fetch lab request details
        // and auto-fill related information
    }
});
</script>
@endsection
