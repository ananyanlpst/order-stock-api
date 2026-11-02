<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use App\Models\Order;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create([
        'role' => 'admin',
    ]);

    Sanctum::actingAs($user);
});

test('can create a product', function () {
    $response = $this->postJson('/api/products', [
        'sku' => 'TEST-001',
        'name' => 'Test Pen',
        'price' => 15,
        'stock_quantity' => 50,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.sku', 'TEST-001')
        ->assertJsonPath('data.price', '15.00');

    $this->assertDatabaseHas('products', [
        'sku' => 'TEST-001',
        'name' => 'Test Pen',
        'price' => 15,
        'stock_quantity' => 50,
    ]);
});

test('cannot create a product with a negative price', function () {
    $response = $this->postJson('/api/products', [
        'sku' => 'TEST-002',
        'name' => 'Invalid Pen',
        'price' => -5,
        'stock_quantity' => 10,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price']);

    $this->assertDatabaseMissing('products', [
        'sku' => 'TEST-002',
    ]);
});

test('can update price without changing other product fields', function () {
    $product = Product::create([
        'sku' => 'TEST-003',
        'name' => 'Pen',
        'price' => 15,
        'stock_quantity' => 50,
    ]);

    $response = $this->patchJson("/api/products/{$product->id}", [
        'price' => 20,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.price', '20.00')
        ->assertJsonPath('data.name', 'Pen')
        ->assertJsonPath('data.stock_quantity', 50);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'sku' => 'TEST-003',
        'name' => 'Pen',
        'price' => 20,
        'stock_quantity' => 50,
    ]);
});

test('cannot create a product with a duplicate sku', function(){
    Product::create([
        'sku' => 'TEST-004',
        'name' => 'Original Pen',
        'price' => 15,
        'stock_quantity' => 50,
    ]);

    $response = $this->postJson('/api/products', [
        'sku' => 'TEST-004',
        'name' => 'Duplicate Pen',
        'price' => 20,
        'stock_quantity' => 10
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
    
    $this->assertDatabaseCount('products', 1);

    $this->assertDatabaseHas('products', [
        'sku' => 'TEST-004',
        'name' => 'Original Pen',
        'price' => 15,
        'stock_quantity' => 50,
    ]);
});

test('admin can delete a product', function () {
    $product = Product::create([
        'sku' => 'DELETE-001',
        'name' => 'Pen',
        'price' => 15,
        'stock_quantity' => 10,
    ]);

    $this->deleteJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Product deleted successfully.');

    $this->assertDatabaseMissing('products', [
        'id' => $product->id,
    ]);

    $this->getJson("/api/products/{$product->id}")
        ->assertNotFound();
});

test('cannot delete a product with order history', function(){
    $product = Product::create([
        'sku' => 'HISTORY-001',
        'name' => 'Pen',
        'price' => '15.00',
        'stock_quantity' => 10,
    ]);

    $order = Order::create([
        'user_id' => auth()->id(),
        'status' => 'cancelled',
        'total_amount' => '30.00',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'product_sku' => $product->sku,
        'product_name' => $product->name,
        'quantity' => 2,
        'unit_price' => '15.00',
        'subtotal' => '30.00',
    ]);

    $this->deleteJson("/api/products/{$product->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product']);

    $this->assertDatabaseHas('products', [
        'id' => $product->id
    ]);

    $this->assertDatabaseHas('order_items', [
        'order_id' => $order->id,
        'product_id' => $product->id,
    ]);
});