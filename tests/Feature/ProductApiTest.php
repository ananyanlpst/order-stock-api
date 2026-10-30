<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
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