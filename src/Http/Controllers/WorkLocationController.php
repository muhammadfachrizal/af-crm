<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

class WorkLocationController extends Controller
{
    public function index()
    {
        return view('laravel-crm::work-locations.index');
    }

    public function create()
    {
        return view('laravel-crm::work-locations.form', ['id' => null]);
    }

    public function edit(int $id)
    {
        return view('laravel-crm::work-locations.form', ['id' => $id]);
    }
}
