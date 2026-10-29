<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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