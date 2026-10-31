<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\Response;

class DashboardController extends Controller
{
    /**
     * Display the CRM dashboard.
     *
     * @return Response
     */
    public function index()
    {
        if (\VentureDrake\LaravelCrm\Support\CurrentTenant::isSuperAdmin()) {
            return redirect()->route('laravel-crm.tenants.dashboard');
        }

        return view('laravel-crm::index');
    }
}
