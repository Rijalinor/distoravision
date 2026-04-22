<?php

namespace App\Http\Controllers;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index()
    {
        $activities = Activity::with('causer')->latest()->paginate(50);
        return view('settings.activity-logs.index', compact('activities'));
    }
}
