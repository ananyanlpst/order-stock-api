<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:confirmed,cancelled'],
        ]);

        $query = Order::query();

        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $orders = $query
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($request, $validated) {
            $items = collect($validated['items'])
                ->sortBy('product_id');

            $products = Product::whereIn(
                'id',
                $items->pluck('product_id')
            )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalCents = 0;
            $orderItems = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];

                if (! $product || $product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => ['A product is unavailable or has insufficient stock.'],
                    ]);
                }

                // คำนวณเงินเป็นจำนวนเต็มหน่วยสตางค์
                [$baht, $satang] = explode('.', $product->price);
                $priceCents = ((int) $baht * 100) + (int) $satang;
                $subtotalCents = $priceCents * $quantity;
                $totalCents += $subtotalCents;

                if ($totalCents > 99999999999999) {
                    throw ValidationException::withMessages([
                        'items' => ['The order total exceeds the allowed limit.'],
                    ]);
                }

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $this->formatMoney($subtotalCents),
                ];

                $product->decrement('stock_quantity', $quantity);
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => 'confirmed',
                'total_amount' => $this->formatMoney($totalCents),
            ]);

            $order->items()->createMany($orderItems);

            return $order->load('items');
        });

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
    }

    private function formatMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json([
            'data' => $order->load('items'),
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('cancel', $order);

        $cancelledOrder = DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'order' => ['Only confirmed orders can be cancelled.'],
                ]);
            }

            $items = $lockedOrder->items()
                ->orderBy('product_id')
                ->get();

            $products = Product::whereIn(
                'id',
                $items->pluck('product_id')
            )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item->product_id);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'order' => ['An order product is unavailable.'],
                    ]);
                }

                $newStock = $product->stock_quantity + $item->quantity;

                if ($newStock > 4294967295) {
                    throw ValidationException::withMessages([
                        'order' => ['Restoring stock exceeds the allowed limit.'],
                    ]);
                }

                $product->increment('stock_quantity', $item->quantity);
            }

            $lockedOrder->update([
                'status' => 'cancelled',
            ]);

            return $lockedOrder->load('items');
        });

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'data' => $cancelledOrder,
        ]);
    }
}