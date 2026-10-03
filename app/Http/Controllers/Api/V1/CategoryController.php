<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private ProductRepository $products) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->products->categoryTree());
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());
        $this->products->invalidateCategories();

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('children'));
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());
        $this->products->invalidateCategories();

        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return response()->json(['error' => [
                'code' => 'CATEGORY_IN_USE',
                'message' => 'Category still has products.',
                'details' => (object) [],
            ]], 409);
        }

        $category->delete();
        $this->products->invalidateCategories();

        return response()->json(null, 204);
    }
}
