<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

class VisitController extends Controller
{
    public function index()
    {
        return view('laravel-crm::visits.index');
    }

    public function create()
    {
        return view('laravel-crm::visits.create');
    }
}
