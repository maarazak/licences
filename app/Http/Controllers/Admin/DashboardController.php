<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Setting;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $since = fn(int $days) => now()->subDays($days);

        $calcRevenue = function ($from) {
            $orders = Order::where('status', 'delivered')->where('paid_at', '>=', $from)->get();
            $total = (int) $orders->sum('total_xof');
            $cost = (int) $orders->sum(function ($o) {
                return (int) ceil(($o->unit_cost_usd_cents * $o->quantity * $o->usd_rate) / 100);
            });
            return [
                'orders' => $orders->count(),
                'total' => $total,
                'margin' => $total - $cost,
            ];
        };

        $today = $calcRevenue($since(1));
        $week = $calcRevenue($since(7));
        $month = $calcRevenue($since(30));

        $counts = [
            'pending_payment' => Order::where('status', 'pending_payment')->count(),
            'paid' => Order::where('status', 'paid')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'activation_awaiting' => Order::where('status', 'activation_awaiting')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'failed' => Order::where('status', 'failed')->count(),
            'payment_failed' => Order::where('status', 'payment_failed')->count(),
            'expired' => Order::where('status', 'expired')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'refunded' => Order::where('status', 'refunded')->count(),
        ];

        $openTickets = Ticket::where('status', 'open')->count();
        $customers = User::where('role', 'client')->count();

        $catalogTotal = Product::count();
        $catalogPublished = Product::where('is_published', true)->where('supplier_active', true)->count();
        $catalogSellable = Product::where('supplier_active', true)->where('supplier_purchasable', true)->count();

        $settings = [
            'usdRate' => (int) Setting::get('usdRate', Setting::get('usd_rate', 669)),
            'supplierBalanceCents' => (int) Setting::get('supplierBalanceCents', Setting::get('supplier_balance_usd_cents', 0)),
            'supplierBalanceAt' => Setting::get('supplierBalanceAt', Setting::get('supplier_balance_at')),
            'supplierBalanceError' => Setting::get('supplierBalanceError'),
            'lastCatalogSyncAt' => Setting::get('lastCatalogSyncAt', Setting::get('last_catalog_sync_at')),
            'catalogSyncError' => Setting::get('catalogSyncError'),
        ];

        $inProgress = $counts['paid'] + $counts['processing'] + $counts['activation_awaiting'];
        $totalOrders = Order::count();
        $totalRevenueXof = (int) Order::whereNotNull('paid_at')->sum('total_xof');

        return view('admin.dashboard', compact(
            'today',
            'week',
            'month',
            'counts',
            'openTickets',
            'customers',
            'catalogTotal',
            'catalogPublished',
            'catalogSellable',
            'settings',
            'inProgress',
            'totalOrders',
            'totalRevenueXof'
        ));
    }
}
