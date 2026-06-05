<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;


class HomeController extends Controller
{
    public function index()
    {
        return view('layouts.master');
    }
    public function dashboard()
    {
        return view('layouts.master');
    }


    public function ochranaOsobnychUdajov()
    {
        return view('public.ochrana-osobnych-udajov');
    }

    public function contactUs()
    {
        return view('public.contactUs');
    }
}
