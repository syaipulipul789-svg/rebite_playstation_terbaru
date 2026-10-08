<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index');
    }
}
