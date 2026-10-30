<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('can create an order and deduct stock', function () {
    $user = User::factory()->create([
        'role' => 'staff',
    ]);

    Sanctum::actingAs($user);

    $product = Product::create([
        'sku' => 'ORDER-001',
        'name' => 'Pen',
        'price' => '15.50',
        'stock_quantity' => 10,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.total_amount', '31.00')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.unit_price', '15.50')
        ->assertJsonPath('data.items.0.subtotal', '31.00');

    $orderId = $response->json('data.id');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'user_id' => $user->id,
        'status' => 'confirmed',
        'total_amount' => 31,
    ]);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 15.50,
        'subtotal' => 31,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock_quantity' => 8,
    ]);
});

test('insufficient stock rolls back the entire order', function () {
    Sanctum::actingAs(User::factory()->create());

    $pen = Product::create([
        'sku' => 'ROLLBACK-001',
        'name' => 'Pen',
        'price' => '15.00',
        'stock_quantity' => 10,
    ]);

    $notebook = Product::create([
        'sku' => 'ROLLBACK-002',
        'name' => 'Notebook',
        'price' => '99.00',
        'stock_quantity' => 1,
    ]);

    $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $pen->id,
                'quantity' => 2,
            ],
            [
                'product_id' => $notebook->id,
                'quantity' => 2,
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);

    $this->assertDatabaseHas('products', [
        'id' => $pen->id,
        'stock_quantity' => 10,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $notebook->id,
        'stock_quantity' => 1,
    ]);
});

test('can calculate the total for multiple products', function () {
    Sanctum::actingAs(User::factory()->create());

    $pen = Product::create([
        'sku' => 'TOTAL-001',
        'name' => 'Pen',
        'price' => '15.50',
        'stock_quantity' => 10,
    ]);

    $notebook = Product::create([
        'sku' => 'TOTAL-002',
        'name' => 'Notebook',
        'price' => '99.90',
        'stock_quantity' => 5,
    ]);

    $response = $this->postJson('/api/orders', [
        'items' => [
            ['product_id' => $pen->id, 'quantity' => 2],
            ['product_id' => $notebook->id, 'quantity' => 3],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.total_amount', '330.70')
        ->assertJsonCount(2, 'data.items');

    $orderId = $response->json('data.id');

    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => $pen->id,
        'quantity' => 2,
        'subtotal' => 31,
    ]);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'product_id' => $notebook->id,
        'quantity' => 3,
        'subtotal' => 299.70,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $pen->id,
        'stock_quantity' => 8,
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $notebook->id,
        'stock_quantity' => 2,
    ]);
});