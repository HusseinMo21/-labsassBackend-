<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Get the current lab ID for multi-tenant context.
     */
    protected function currentLabId(): ?int
    {
        return auth()->user()?->lab_id ?? (app()->bound('current_lab_id') ? app('current_lab_id') : null);
    }

    /**
     * Get lab-prefixed storage path for multi-tenant file isolation.
     */
    protected function labStoragePath(string $subPath): string
    {
        $labId = $this->currentLabId() ?? 1;
        return "labs/{$labId}/{$subPath}";
    }
} 