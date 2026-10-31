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
}
