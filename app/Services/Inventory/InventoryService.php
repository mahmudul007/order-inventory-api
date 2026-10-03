<?php

namespace App\Services\Inventory;

use App\Contracts\InventoryServiceInterface;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pessimistic-locking inventory service.
 *
 * Stock is only ever checked AFTER the row lock is acquired, never before the
 * transaction. Rows are always locked in ascending product_id order so two
 * concurrent multi-item orders cannot deadlock on each other.
 */
class InventoryService implements InventoryServiceInterface
{
    public function reserve(array $quantities, Order $order): void
    {
        $inventories = $this->lockRows(array_keys($quantities));

        foreach ($quantities as $productId => $qty) {
            $inventory = $inventories->get($productId);
            $available = $inventory ? $inventory->available() : 0;

            if ($inventory === null || $available < $qty) {
                throw InsufficientStockException::forProduct($productId, $qty, $available);
            }
        }

        foreach ($quantities as $productId => $qty) {
            $inventory = $inventories->get($productId);
            $inventory->quantity_reserved += $qty;
            $inventory->version++;
            $inventory->save();

            $this->record($productId, StockMovementType::Reserve, -$qty, $order);
        }
    }

    public function release(Order $order): void
    {
        $quantities = $this->orderQuantities($order);
        $inventories = $this->lockRows(array_keys($quantities));

        foreach ($quantities as $productId => $qty) {
            $inventory = $inventories->get($productId);

            if ($inventory === null) {
                continue;
            }

            $inventory->quantity_reserved = max(0, $inventory->quantity_reserved - $qty);
            $inventory->version++;
            $inventory->save();

            $this->record($productId, StockMovementType::Release, $qty, $order);
        }
    }

    public function commit(Order $order): void
    {
        $quantities = $this->orderQuantities($order);
        $inventories = $this->lockRows(array_keys($quantities));

        foreach ($quantities as $productId => $qty) {
            $inventory = $inventories->get($productId);

            if ($inventory === null) {
                continue;
            }

            $inventory->quantity_reserved -= $qty;
            $inventory->quantity_on_hand -= $qty;
            $inventory->version++;
            $inventory->save();

            $this->record($productId, StockMovementType::Commit, -$qty, $order);
        }
    }

    public function adjust(int $productId, int $delta, ?string $note = null): Inventory
    {
        return DB::transaction(function () use ($productId, $delta, $note): Inventory {
            $inventory = Inventory::query()->where('product_id', $productId)->lockForUpdate()->first()
                ?? Inventory::create(['product_id' => $productId]);

            $newOnHand = $inventory->quantity_on_hand + $delta;

            if ($newOnHand < $inventory->quantity_reserved) {
                throw new InvalidArgumentException('Adjustment would drop on-hand stock below reserved quantity.');
            }

            $inventory->quantity_on_hand = $newOnHand;
            $inventory->version++;
            $inventory->save();

            $this->record($productId, $delta >= 0 ? StockMovementType::In : StockMovementType::Out, $delta, null, $note);

            return $inventory;
        }, 3);
    }

    /**
     * @param  array<int, int>  $productIds
     * @return Collection<int, Inventory>
     */
    private function lockRows(array $productIds): Collection
    {
        sort($productIds);

        return Inventory::query()
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');
    }

    /**
     * @return array<int, int>
     */
    private function orderQuantities(Order $order): array
    {
        return $order->items()
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->map(fn ($q) => (int) $q)
            ->all();
    }

    private function record(int $productId, StockMovementType $type, int $quantity, ?Order $order, ?string $note = null): void
    {
        StockMovement::create([
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $order ? $order->getMorphClass() : null,
            'reference_id' => $order?->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
