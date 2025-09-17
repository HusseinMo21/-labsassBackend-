<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    protected $fillable = [
        'paid',
        'comment',
        'date',
        'author',
        'income',
        'invoice_id',
    ];

    protected $casts = [
        'paid' => 'integer',
        'date' => 'date',
        'author' => 'integer',
        'income' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
} 