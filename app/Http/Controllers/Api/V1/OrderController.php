<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\CreateOrderAction;
use App\Actions\Orders\UpdateOrderStatusAction;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    //
    /**
     * Cursor-paginated order history. Customers see only their own orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'customer_id' => ['sometimes', 'integer'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $user = $request->user();

        $orders = Order::query()
            ->with(['customer', 'items.product'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('customer_id', $user->customer?->id ?? 0))
            ->when($user->isAdmin() && $request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        $order->load('customer');
        $this->authorize('view', $order);

        return new OrderResource($order->load('items.product'));
    }

    public function store(StoreOrderRequest $request, CreateOrderAction $action): JsonResponse
    {
        $order = $action->execute(
            $request->user(),
            $request->validated('items'),
            (string) $request->header('Idempotency-Key'),
        );

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function cancel(Order $order, CancelOrderAction $action): OrderResource
    {
        $order->load('customer');
        $this->authorize('cancel', $order);

        return new OrderResource($action->execute($order));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, UpdateOrderStatusAction $action): OrderResource
    {
        $this->authorize('updateStatus', $order);

        return new OrderResource($action->execute($order, $request->status()));
    }
}
