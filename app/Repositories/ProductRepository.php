<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ProductRepository
{
    private const CATEGORY_TREE_KEY = 'categories:tree';

    private const CATEGORY_TREE_TTL = 3600;

    private const PRODUCT_VERSION_KEY = 'products:version';

    private const PRODUCT_DETAIL_TTL = 60;

    private const PRODUCT_LIST_TTL = 30;

    /**
     * Retrieve the hierarchical category tree from cache or database.
     */
    public function categoryTree(): Collection
    {
        return Cache::remember(self::CATEGORY_TREE_KEY, self::CATEGORY_TREE_TTL, function (): Collection {
            return Category::whereNull('parent_id')
                ->with('children')
                ->get();
        });
    }

    /**
     * Invalidate the cached category tree.
     */
    public function invalidateCategories(): void
    {
        Cache::forget(self::CATEGORY_TREE_KEY);
    }

    /**
     * Get the current product cache namespace version.
     */
    public function getProductVersion(): int
    {
        return (int) Cache::get(self::PRODUCT_VERSION_KEY, 1);
    }

    /**
     * Invalidate all cached product lists and details via version increment.
     */
    public function invalidateProducts(): void
    {
        if (! Cache::has(self::PRODUCT_VERSION_KEY)) {
            Cache::forever(self::PRODUCT_VERSION_KEY, 1);
        }

        Cache::increment(self::PRODUCT_VERSION_KEY);
    }

    /**
     * Find a product by ID with category and inventory loaded.
     */
    public function find(int $id): ?Product
    {
        $version = $this->getProductVersion();
        $key = "products:v{$version}:detail:{$id}";

        return Cache::remember($key, self::PRODUCT_DETAIL_TTL, function () use ($id): ?Product {
            return Product::with(['category', 'inventory'])->find($id);
        });
    }

    /**
     * Paginate products with search and filtering.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $version = $this->getProductVersion();
        $filterHash = md5(serialize($filters)."_pp{$perPage}_p{$page}");
        $key = "products:v{$version}:list:{$filterHash}";

        return Cache::remember($key, self::PRODUCT_LIST_TTL, function () use ($filters, $perPage, $page): LengthAwarePaginator {
            $query = Product::query()->with(['category', 'inventory']);

            if (! empty($filters['search'])) {
                $query->where('name', 'like', '%'.$filters['search'].'%');
            }

            if (! empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }

            if (isset($filters['is_active'])) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            }

            if (! empty($filters['min_price'])) {
                $query->where('price', '>=', (int) $filters['min_price']);
            }

            if (! empty($filters['max_price'])) {
                $query->where('price', '<=', (int) $filters['max_price']);
            }

            if (! empty($filters['sort'])) {
                $direction = str_starts_with($filters['sort'], '-') ? 'desc' : 'asc';
                $field = ltrim($filters['sort'], '-');
                if (in_array($field, ['price', 'created_at', 'name'])) {
                    $query->orderBy($field, $direction);
                }
            } else {
                $query->latest();
            }

            return $query->paginate($perPage, ['*'], 'page', $page);
        });
    }
}
