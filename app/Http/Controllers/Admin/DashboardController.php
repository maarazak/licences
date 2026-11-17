<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Ticket;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $totalOrders = Order::count();
        $totalRevenueXof = Order::whereNotNull('paid_at')->sum('total_xof');
        $deliveredCount = Order::where('status', 'delivered')->count();
        $pendingCount = Order::where('status', 'pending_payment')->count();
        $failedCount = Order::where('status', 'failed')->count();
        $activeProductsCount = Product::where('is_active', true)->count();
        $openTicketsCount = Ticket::where('status', 'open')->count();
        $clientsCount = User::where('role', 'client')->count();

        $recentOrders = Order::with('user', 'product')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $recentLogs = AuditLog::orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalOrders',
            'totalRevenueXof',
            'deliveredCount',
            'pendingCount',
            'failedCount',
            'activeProductsCount',
            'openTicketsCount',
            'clientsCount',
            'recentOrders',
            'recentLogs'
        ));
    }
}
