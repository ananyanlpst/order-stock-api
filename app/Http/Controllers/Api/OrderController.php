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

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query();

        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }

        $orders = $query
            ->orderBy('id', 'desc')
            ->paginate(10);

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
        $user = $request->user();

        if ($user->role !== 'admin' && $order->user_id !== $user->id) {
            abort(403, 'You cannot view this order.');
        }

        return response()->json([
            'data' => $order->load('items'),
        ]);
    }
}