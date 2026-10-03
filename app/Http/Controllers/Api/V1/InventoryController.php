<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\InventoryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdjustInventoryRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InventoryController extends Controller
{
    //
    public function __construct(
        private InventoryServiceInterface $inventory,
        private ProductRepository $products,
    ) {}

    public function show(Product $product): JsonResponse
    {
        $inventory = $product->inventory()->firstOrFail();

        return response()->json(['data' => $this->payload($inventory)]);
    }

    public function adjust(AdjustInventoryRequest $request, Product $product): JsonResponse
    {
        $this->authorize('adjustStock', $product);

        try {
            $inventory = $this->inventory->adjust($product->id, $request->integer('delta'), $request->validated('note'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => [
                'code' => 'INVALID_ADJUSTMENT',
                'message' => $e->getMessage(),
                'details' => (object) [],
            ]], 422);
        }

        $this->products->invalidateProducts();

        return response()->json(['data' => $this->payload($inventory)]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $threshold = max(0, $request->integer('threshold', 10));

        $page = Inventory::query()
            ->with('product:id,sku,name')
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= ?', [$threshold])
            ->orderByRaw('(quantity_on_hand - quantity_reserved) asc')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Inventory $i) => $this->payload($i) + [
                'sku' => $i->product?->sku,
                'name' => $i->product?->name,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'threshold' => $threshold],
        ]);
    }

    /**
     * @return array{product_id: int, on_hand: int, reserved: int, available: int, version: int}
     */
    private function payload(Inventory $inventory): array
    {
        return [
            'product_id' => $inventory->product_id,
            'on_hand' => $inventory->quantity_on_hand,
            'reserved' => $inventory->quantity_reserved,
            'available' => $inventory->available(),
            'version' => $inventory->version,
        ];
    }
}
