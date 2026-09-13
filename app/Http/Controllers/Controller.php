<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function currentTenant(Request $request): ?Tenant
    {
        return app()->bound('currentTenant') ? app('currentTenant') : $request->user()?->tenant;
    }
}
