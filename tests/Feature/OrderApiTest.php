<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Order;

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

test('staff can only list their own orders', function () {
    $staff = User::factory()->create([
        'role' => 'staff',
    ]);

    $otherUser = User::factory()->create();

    $ownOrder = Order::create([
        'user_id' => $staff->id,
        'status' => 'confirmed',
        'total_amount' => '100.00',
    ]);

    $otherOrder = Order::create([
        'user_id' => $otherUser->id,
        'status' => 'confirmed',
        'total_amount' => '200.00',
    ]);

    Sanctum::actingAs($staff);

    $this->getJson('/api/orders')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownOrder->id)
        ->assertJsonMissing([
            'id' => $otherOrder->id,
            'user_id' => $otherUser->id,
        ]);
});

test('admin can list orders from all users', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $staff = User::factory()->create([
        'role' => 'staff',
    ]);

    $adminOrder = Order::create([
        'user_id' => $admin->id,
        'status' => 'confirmed',
        'total_amount' => '100.00',
    ]);

    $staffOrder = Order::create([
        'user_id' => $staff->id,
        'status' => 'confirmed',
        'total_amount' => '200.00',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/orders')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $staffOrder->id)
        ->assertJsonPath('data.1.id', $adminOrder->id);
});


test('staff cannot view another users order', function () {
    $owner = User::factory()->create();

    $otherStaff = User::factory()->create([
        'role' => 'staff',
    ]);

    $order = Order::create([
        'user_id' => $owner->id,
        'status' => 'confirmed',
        'total_amount' => '100.00',
    ]);

    Sanctum::actingAs($otherStaff);

    $this->getJson("/api/orders/{$order->id}")
        ->assertForbidden()
        ->assertJsonMissingPath('data');
});

test('staff can view their own order with items', function () {
    $staff = User::factory()->create([
        'role' => 'staff',
    ]);

    Sanctum::actingAs($staff);

    $product = Product::create([
        'sku' => 'DETAIL-001',
        'name' => 'Pen',
        'price' => '15.50',
        'stock_quantity' => 10,
    ]);

    $created = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated();

    $orderId = $created->json('data.id');

    $this->getJson("/api/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.id', $orderId)
        ->assertJsonPath('data.user_id', $staff->id)
        ->assertJsonPath('data.total_amount', '31.00')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product_id', $product->id)
        ->assertJsonPath('data.items.0.quantity', 2);
});

test('admin can view another users order', function () {
    $staff = User::factory()->create([
        'role' => 'staff',
    ]);

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $order = Order::create([
        'user_id' => $staff->id,
        'status' => 'confirmed',
        'total_amount' => '100.00',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.user_id', $staff->id);
});

test('cancelling an order restores stock only once', function () {
    $staff = User::factory()->create([
        'role' => 'staff',
    ]);

    Sanctum::actingAs($staff);

    $product = Product::create([
        'sku' => 'CANCEL-001',
        'name' => 'Pen',
        'price' => '15.00',
        'stock_quantity' => 10,
    ]);

    $created = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated();

    $orderId = $created->json('data.id');

    // หลังสั่งซื้อ สต็อกเหลือ 8
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock_quantity' => 8,
    ]);

    // ยกเลิกครั้งแรกสำเร็จ
    $this->postJson("/api/orders/{$orderId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'cancelled',
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock_quantity' => 10,
    ]);

    // ยกเลิกซ้ำต้องถูกปฏิเสธ เพราะสถานะถูกเปลี่ยนเป็น cancelled
    $this->postJson("/api/orders/{$orderId}/cancel")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order']);

    // สต็อกต้องยังเป็น 10 ไม่เพิ่มเป็น 12
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock_quantity' => 10,
    ]);
});

test('staff cannot cancel another users order', function () {
    $owner = User::factory()->create();

    $otherStaff = User::factory()->create([
        'role' => 'staff',
    ]);

    $product = Product::create([
        'sku' => 'CANCEL-002',
        'name' => 'Pen',
        'price' => '15.00',
        'stock_quantity' => 10,
    ]);

    Sanctum::actingAs($owner);

    $created = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
        ],
    ])->assertCreated();

    $orderId = $created->json('data.id');

    // เปลี่ยนเป็นผู้ใช้ Staff
    Sanctum::actingAs($otherStaff);

    $this->postJson("/api/orders/{$orderId}/cancel")
        ->assertForbidden();

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'status' => 'confirmed',
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock_quantity' => 8,
    ]);
});

test('admin can cancel another users order', function(){
    $staff = User::factory()->create([
        'role' => 'staff'
    ]);

    $admin = User::factory()->create([
        'role' => 'admin'
    ]);

    $product = Product::create([
        'sku' => 'CANCEL-003',
        'name' => 'Pen',
        'price' => '15.00',
        'stock_quantity' => 10,
    ]);

    Sanctum::actingAs($staff);

    $created = $this->postJson('/api/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2
            ],
        ],
    ])->assertCreated();

    $orderId = $created->json('data.id');

    Sanctum::actingAs($admin);

    $this->postJson("/api/orders/{$orderId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'user_id' => $staff->id,
        'status' => 'cancelled'
    ]);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock_quantity' => 10
    ]);
});

test('cannot create an order with duplicate products', function(){
    Sanctum::actingAs(User::factory()->create());

    $product = Product::create([
        'sku' => 'DUPLICATE-001',
        'name' => 'Pen',
        'price' => '15.00',
        'stock_quantity' => 10,
    ]);

    // สร้าง product_id เดียวกัน
    $this->postJson('/api/orders/', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 3]
        ]
    ])
    ->assertUnprocessable()
    ->assertJsonValidationErrors([
        'items.0.product_id',
        'items.1.product_id',
    ]);
});

test('guests cannot create orders', function(){
    $product = Product::create([
        'sku' => 'GUEST-001',
        'name' => 'Pen',
        'price' => '15.00',
        'stock_quantity' => 10,
    ]);

    $this->postJson('/api/orders', [
        'items' => [
            'product_id' => $product->id,
            'quantity' => 2
        ]
    ])->assertUnauthorized();

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_items', 0);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'stock_quantity' => 10
    ]);
});

test('staff can filter their own orders by status', function() {
    $staff = User::factory()->create([
        'role' => 'staff'
    ]);

    $otherUser = User::factory()->create();

    $confirmedOrder = Order::create([
        'user_id' => $staff->id,
        'status' => 'confirmed',
        'total_amount' => '100.00',
    ]);

    $cancelledOrder = Order::create([
        'user_id' => $staff->id,
        'status' => 'cancelled',
        'total_amount' => '200.00',
    ]);    

    Order::create([
        'user_id' => $otherUser->id,
        'status' => 'confirmed',
        'total_amount' => '300.00',
    ]);

    Sanctum::actingAs($staff);

    $this->getJson('/api/orders?status=confirmed')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $confirmedOrder->id);

    $this->getJson('/api/orders?status=cancelled')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $cancelledOrder->id);
});

test('cannot filter orders by an invalid status', function() {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/orders?status=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('order items preserve the purchase price', function() {
    Sanctum::actingAs(User::factory()->create());

    $product = Product::create([
        'sku' => 'SNAPSHOT-001',
        'name' => 'Pen',
        'price' => '15.00',
        'stock_quantity' => 10,
    ]);

    $created = $this->postJson('/api/orders/', [
        'items' => [
            [        
                'product_id' => $product->id,
                'quantity' =>2
            ]
        ]
    ])->assertCreated();

    $orderId = $created->json('data.id');

    $product->update([
        'price' => 20
    ]);

    $this->getJson("/api/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.total_amount', '30.00')
        ->assertJsonPath('data.items.0.unit_price', '15.00')
        ->assertJsonPath('data.items.0.subtotal', '30.00');
    
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'price' => 20
    ]);

});