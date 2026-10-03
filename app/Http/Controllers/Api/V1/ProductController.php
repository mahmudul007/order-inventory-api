<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\InventoryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Inventory;
use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        private ProductRepository $products,
        private InventoryServiceInterface $inventory,
    ) {}

    public function index(Request $request): ProductCollection
    {
        $filters = $request->only(['search', 'category_id', 'is_active', 'min_price', 'max_price', 'sort']);

        return new ProductCollection(
            $this->products->paginate($filters, $this->perPage($request), max(1, $request->integer('page', 1)))
        );
    }

    public function show(int $product): ProductResource
    {
        $model = $this->products->find($product) ?? abort(404);

        return new ProductResource($model);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = DB::transaction(function () use ($request): Product {
            $product = Product::create($request->safe()->except('initial_stock'));
            Inventory::create(['product_id' => $product->id]);

            if ($request->integer('initial_stock') > 0) {
                $this->inventory->adjust($product->id, $request->integer('initial_stock'), 'Initial stock');
            }

            return $product;
        });

        $this->products->invalidateProducts();

        return (new ProductResource($product->load(['category', 'inventory'])))->response()->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $product->update($request->validated());
        $this->products->invalidateProducts();

        return new ProductResource($product->load(['category', 'inventory']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();
        $this->products->invalidateProducts();

        return response()->json(null, 204);
    }
}
