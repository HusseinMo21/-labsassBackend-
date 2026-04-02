<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lab extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function labRequests()
    {
        return $this->hasMany(LabRequest::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function testOfferings()
    {
        return $this->hasMany(LabTestOffering::class);
    }

    public function labPackages()
    {
        return $this->hasMany(LabPackage::class);
    }

    /**
     * Receipt text branding from lab.settings (optional keys):
     * report_header, receipt_tagline, receipt_subtitle, receipt_address, address,
     * receipt_phone, phone, receipt_email, email, vat_number, receipt_vat, website,
     * receipt_doc_label, receipt_currency_label.
     */
    public function receiptBranding(): array
    {
        $s = is_array($this->settings) ? $this->settings : [];

        $display = isset($s['report_header']) && trim((string) $s['report_header']) !== ''
            ? trim((string) $s['report_header'])
            : (string) $this->name;

        $tagline = trim((string) ($s['receipt_tagline'] ?? $s['receipt_subtitle'] ?? ''));
        $address = trim((string) ($s['receipt_address'] ?? $s['address'] ?? ''));
        $phone = trim((string) ($s['receipt_phone'] ?? $s['phone'] ?? ''));
        $email = trim((string) ($s['receipt_email'] ?? $s['email'] ?? ''));
        $vat = trim((string) ($s['vat_number'] ?? $s['receipt_vat'] ?? ''));
        $website = trim((string) ($s['website'] ?? ''));

        $docLabel = trim((string) ($s['receipt_doc_label'] ?? ''));
        if ($docLabel === '') {
            $docLabel = 'إيصال تسجيل / دفع';
        }

        $currency = trim((string) ($s['receipt_currency_label'] ?? ''));
        if ($currency === '') {
            $currency = 'جنيه';
        }

        return [
            'display_name' => $display,
            'tagline' => $tagline,
            'address' => $address,
            'phone' => $phone,
            'email' => $email,
            'vat' => $vat,
            'website' => $website,
            'doc_label' => $docLabel,
            'currency_label' => $currency,
        ];
    }

    public static function fallbackReceiptBranding(): array
    {
        return [
            'display_name' => 'Laboratory',
            'tagline' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'vat' => '',
            'website' => '',
            'doc_label' => 'إيصال تسجيل / دفع',
            'currency_label' => 'جنيه',
        ];
    }
}
