<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

class TenantController extends Controller
{
    public function index()
    {
        return view('laravel-crm::tenants.index');
    }

    public function dashboard()
    {
        return view('laravel-crm::tenants.dashboard');
    }

    public function leave()
    {
        session()->forget('acting_tenant_id');
        \VentureDrake\LaravelCrm\Support\TenantAuditLog::record('tenant.leave');

        return redirect()->route('laravel-crm.tenants.index');
    }
}
