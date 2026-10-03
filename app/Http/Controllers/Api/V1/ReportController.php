<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    //
    /**
     * Revenue and order count grouped by day (excludes cancelled orders).
     */
    public function sales(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $rows = DB::table('orders')
            ->selectRaw('DATE(placed_at) as day, COUNT(*) as orders, SUM(total) as revenue')
            ->whereBetween('placed_at', [$from, $to])
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'orders' => (int) $r->orders, 'revenue' => (int) $r->revenue]);

        return response()->json([
            'data' => $rows,
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $limit = max(1, min($request->integer('limit', 10), 100));

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('products.id, products.sku, products.name, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->orderByDesc('units')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['product_id' => (int) $r->id, 'sku' => $r->sku, 'name' => $r->name, 'units' => (int) $r->units, 'revenue' => (int) $r->revenue]);

        return response()->json([
            'data' => $rows,
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'limit' => $limit],
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $to->copy()->subDays(30)->startOfDay();

        return [$from, $to];
    }
}
