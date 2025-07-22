<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class SampleTracking extends Model
{
    use HasFactory;

    protected $table = 'sample_tracking';

    protected $fillable = [
        'visit_test_id',
        'sample_id',
        'status',
        'location',
        'notes',
        'collected_at',
        'received_at',
        'processing_started_at',
        'analysis_started_at',
        'completed_at',
        'disposed_at',
        'collected_by',
        'received_by',
        'processed_by',
        'analyzed_by',
        'disposed_by',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'received_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'analysis_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'disposed_at' => 'datetime',
    ];

    public function visitTest()
    {
        return $this->belongsTo(VisitTest::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function analyzedBy()
    {
        return $this->belongsTo(User::class, 'analyzed_by');
    }

    public function disposedBy()
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }

    public function updateStatus($status, $userId = null)
    {
        $this->status = $status;
        
        switch ($status) {
            case 'collected':
                $this->collected_at = now();
                $this->collected_by = $userId;
                break;
            case 'received':
                $this->received_at = now();
                $this->received_by = $userId;
                break;
            case 'processing':
                $this->processing_started_at = now();
                $this->processed_by = $userId;
                break;
            case 'analyzing':
                $this->analysis_started_at = now();
                $this->analyzed_by = $userId;
                break;
            case 'completed':
                $this->completed_at = now();
                break;
            case 'disposed':
                $this->disposed_at = now();
                $this->disposed_by = $userId;
                break;
        }
        
        $this->save();
    }

    public static function generateSampleId()
    {
        return 'SMP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
} 