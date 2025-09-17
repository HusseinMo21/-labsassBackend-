@extends('layouts.app')

@section('title', 'Report Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-file-medical-alt"></i> Report Details
                        <span class="badge bg-{{ $report->status_badge }} ms-2">
                            {{ ucfirst(str_replace('_', ' ', $report->status)) }}
                        </span>
                        <span class="badge bg-{{ $report->priority_badge }} ms-1">
                            {{ ucfirst($report->priority) }}
                        </span>
                    </h3>
                    <div>
                        @if($report->isEditable())
                        <a href="{{ route('reports.edit', $report) }}" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        @endif
                        @if($report->status === 'draft')
                        <form method="POST" action="{{ route('reports.submit-review', $report) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit for Review
                            </button>
                        </form>
                        @endif
                        @if($report->canBeApproved())
                        <form method="POST" action="{{ route('reports.approve', $report) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        @endif
                        @if($report->canBePrinted())
                        <a href="{{ route('reports.print', $report) }}" class="btn btn-secondary" target="_blank">
                            <i class="fas fa-print"></i> Print
                        </a>
                        @endif
                        @if($report->canBeDelivered())
                        <form method="POST" action="{{ route('reports.deliver', $report) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-truck"></i> Mark as Delivered
                            </button>
                        </form>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-info-circle"></i> Basic Information
                            </h5>
                            
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Lab Number:</strong></td>
                                    <td>{{ $report->lab_no }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Barcode:</strong></td>
                                    <td>
                                        @if($report->barcode)
                                            <span class="badge bg-light text-dark">{{ $report->barcode }}</span>
                                        @else
                                            <span class="text-muted">Not generated</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Patient Number:</strong></td>
                                    <td>{{ $report->nos ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Reference:</strong></td>
                                    <td>{{ $report->reff ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td>{{ $report->type ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Report Date:</strong></td>
                                    <td>{{ $report->report_date ? $report->report_date->format('Y-m-d') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Patient Information -->
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-user"></i> Patient Information
                            </h5>
                            
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Patient:</strong></td>
                                    <td>
                                        @if($report->patient)
                                            <a href="{{ route('patients.show', $report->patient) }}">
                                                {{ $report->patient->name }}
                                            </a>
                                        @else
                                            <span class="text-muted">Not linked</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Lab Request:</strong></td>
                                    <td>
                                        @if($report->labRequest)
                                            <a href="{{ route('lab-requests.show', $report->labRequest) }}">
                                                {{ $report->labRequest->lab_no }}
                                            </a>
                                        @else
                                            <span class="text-muted">Not linked</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Age:</strong></td>
                                    <td>{{ $report->age ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Gender:</strong></td>
                                    <td>{{ $report->sex ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Receiving Date:</strong></td>
                                    <td>{{ $report->recieving ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Discharge Date:</strong></td>
                                    <td>{{ $report->discharge ?? 'N/A' }}</td>
                                </tr>
                            </table>
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
                                <label class="form-label"><strong>Clinical History:</strong></label>
                                <div class="border rounded p-3 bg-light">
                                    {{ $report->clinical ?: 'No clinical history provided' }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><strong>Nature of Specimen:</strong></label>
                                <div class="border rounded p-3 bg-light">
                                    {{ $report->nature ?: 'No specimen information provided' }}
                                </div>
                            </div>
                        </div>

                        <!-- Examination Results -->
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-microscope"></i> Examination Results
                            </h5>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Gross Examination:</strong></label>
                                <div class="border rounded p-3 bg-light">
                                    {{ $report->gross ?: 'No gross examination findings' }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><strong>Microscopic Examination:</strong></label>
                                <div class="border rounded p-3 bg-light">
                                    {{ $report->micro ?: 'No microscopic examination findings' }}
                                </div>
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
                            
                            <div class="border rounded p-3 bg-light">
                                {{ $report->conc ?: 'No conclusion provided' }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-lightbulb"></i> Recommendation
                            </h5>
                            
                            <div class="border rounded p-3 bg-light">
                                {{ $report->reco ?: 'No recommendations provided' }}
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Status Information -->
                    <div class="row">
                        <div class="col-12">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-history"></i> Status & Timeline
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user-edit fa-2x text-primary mb-2"></i>
                                            <h6>Created By</h6>
                                            <p class="mb-1">{{ $report->createdBy->name ?? 'N/A' }}</p>
                                            <small class="text-muted">{{ $report->created_at->format('Y-m-d H:i') }}</small>
                                        </div>
                                    </div>
                                </div>
                                
                                @if($report->reviewedBy)
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="fas fa-eye fa-2x text-warning mb-2"></i>
                                            <h6>Reviewed By</h6>
                                            <p class="mb-1">{{ $report->reviewedBy->name }}</p>
                                            <small class="text-muted">{{ $report->reviewed_at->format('Y-m-d H:i') }}</small>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                
                                @if($report->approvedBy)
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                            <h6>Approved By</h6>
                                            <p class="mb-1">{{ $report->approvedBy->name }}</p>
                                            <small class="text-muted">{{ $report->approved_at->format('Y-m-d H:i') }}</small>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                
                                @if($report->printed_at)
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <i class="fas fa-print fa-2x text-info mb-2"></i>
                                            <h6>Printed</h6>
                                            <p class="mb-1">Report Printed</p>
                                            <small class="text-muted">{{ $report->printed_at->format('Y-m-d H:i') }}</small>
                                        </div>
                                    </div>
                                </div>
                                @endif
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
                            @if($report->isEditable())
                            <form method="POST" action="{{ route('reports.destroy', $report) }}" class="d-inline" 
                                  onsubmit="return confirm('Are you sure you want to delete this report?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
