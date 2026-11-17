<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::orderBy('created_at', 'desc')->paginate(30);

        return view('admin.journal.index', compact('logs'));
    }
}
