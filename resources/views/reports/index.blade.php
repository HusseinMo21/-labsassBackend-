@extends('layouts.app')

@section('title', 'Enhanced Reports')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-file-medical-alt"></i> Enhanced Reports
                    </h3>
                    <div>
                        <a href="{{ route('reports.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Report
                        </a>
                        <a href="{{ route('reports.statistics') }}" class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> Statistics
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card-body">
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="under_review" {{ request('status') == 'under_review' ? 'selected' : '' }}>Under Review</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="printed" {{ request('status') == 'printed' ? 'selected' : '' }}>Printed</option>
                                <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="priority" class="form-select">
                                <option value="">All Priority</option>
                                <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                <option value="normal" {{ request('priority') == 'normal' ? 'selected' : '' }}>Normal</option>
                                <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="lab_no" class="form-control" placeholder="Lab Number" value="{{ request('lab_no') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="patient_name" class="form-control" placeholder="Patient Name" value="{{ request('patient_name') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>

                    <!-- Reports Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Lab No</th>
                                    <th>Patient</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Date</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports as $report)
                                <tr>
                                    <td>
                                        <strong>{{ $report->lab_no }}</strong>
                                        @if($report->barcode)
                                        <br><small class="text-muted">{{ $report->barcode }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($report->patient)
                                            {{ $report->patient->name }}
                                        @else
                                            <span class="text-muted">{{ $report->nos ?? 'N/A' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $report->type ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $report->status_badge }}">
                                            {{ ucfirst(str_replace('_', ' ', $report->status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $report->priority_badge }}">
                                            {{ ucfirst($report->priority) }}
                                        </span>
                                    </td>
                                    <td>{{ $report->report_date ? $report->report_date->format('Y-m-d') : 'N/A' }}</td>
                                    <td>{{ $report->createdBy->name ?? 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('reports.show', $report) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if($report->isEditable())
                                            <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endif
                                            @if($report->status === 'draft')
                                            <form method="POST" action="{{ route('reports.submit-review', $report) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary" title="Submit for Review">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @if($report->canBeApproved())
                                            <form method="POST" action="{{ route('reports.approve', $report) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @if($report->canBePrinted())
                                            <a href="{{ route('reports.print', $report) }}" class="btn btn-sm btn-secondary" title="Print">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-file-medical-alt fa-3x mb-3"></i>
                                        <br>No reports found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $reports->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
