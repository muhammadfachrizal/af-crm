<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendances.
     */
    public function index()
    {
        return view('laravel-crm::attendances.index');
    }
}
