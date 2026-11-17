<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class PageController extends Controller
{
    public function howItWorks()
    {
        return Inertia::render('Shop/HowItWorks');
    }

    public function support()
    {
        return Inertia::render('Shop/Support');
    }

    public function terms()
    {
        return Inertia::render('Shop/Terms');
    }

    public function privacy()
    {
        return Inertia::render('Shop/Privacy');
    }

    public function legal()
    {
        return Inertia::render('Shop/Legal');
    }
}
