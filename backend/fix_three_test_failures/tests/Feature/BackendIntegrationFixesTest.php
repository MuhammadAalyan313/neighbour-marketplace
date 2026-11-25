<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makePatchUser(UserRole $role, string $key): User
{
    $user = User::query()->create([
        'name' => 'Patch '.$key,
        'email' => 'patch-'.$key.'@example.test',
        'phone' => '0300'.str_pad((string) crc32($key), 7, '0', STR_PAD_LEFT),
        'password' => 'Password!123',
        'role' => $role,
        'is_active' => true,
    ]);

    // User::fillable deliberately excludes email_verified_at to protect
    // normal mass assignment. Explicitly verify this test fixture instead.
    $user->forceFill([
        'email_verified_at' => now(),
    ])->save();

    return $user;
}

it('returns available products from an open active store and filters zero stock', function () {
    $seller = makePatchUser(UserRole::SELLER, 'catalog');
    $store = Store::query()->create([
        'user_id' => $seller->id, 'store_name' => 'Sample Foods',
        'slug' => 'sample-foods', 'house_number' => 'A-11',
        'is_active' => true, 'is_open' => true,
    ]);
    $category = Category::query()->create([
        'name' => 'Food', 'slug' => 'food', 'is_active' => true,
    ]);

    foreach ([
        ['Unlimited', null, true],
        ['Available', 3, true],
        ['Out of stock', 0, true],
        ['Disabled', 10, false],
    ] as [$title, $stock, $available]) {
        Product::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'title' => $title,
            'price' => '150.00',
            'stock_quantity' => $stock,
            'is_available' => $available,
        ]);
    }

    $this->getJson('/api/v1/marketplace/stores/sample-foods')
        ->assertOk()
        ->assertJsonPath('data.store_name', 'Sample Foods')
        ->assertJsonCount(2, 'data.products')
        ->assertJsonFragment(['title' => 'Unlimited'])
        ->assertJsonFragment(['title' => 'Available'])
        ->assertJsonMissing(['title' => 'Out of stock'])
        ->assertJsonMissing(['title' => 'Disabled']);

    $store->update(['is_open' => false]);
    $this->getJson('/api/v1/marketplace/stores/sample-foods')->assertNotFound();

    $store->update(['is_open' => true, 'is_active' => false]);
    $this->getJson('/api/v1/marketplace/stores/sample-foods')->assertNotFound();
});

it('limits store listing pagination to a safe 1 to 50', function () {
    $this->getJson('/api/v1/marketplace/stores?per_page=9999')
        ->assertOk()->assertJsonPath('meta.per_page', 50);

    $this->getJson('/api/v1/marketplace/stores?per_page=-10')
        ->assertOk()->assertJsonPath('meta.per_page', 1);
});

it('serializes review users without exposing email or phone', function () {
    $user = new User([
        'name' => 'Sample Buyer',
        'email' => 'private@example.test',
        'phone' => '03001112222',
    ]);
    $user->id = 10;

    $review = new Review([
        'rating' => 5,
        'comment' => 'Great',
        'is_published' => true,
    ]);
    $review->setRelation('user', $user);

    $data = (new \App\Http\Resources\ReviewResource($review))
        ->resolve(request());

    $publicUser = $data['user']->resolve(request());
    expect($publicUser)->toHaveKey('name', 'Sample Buyer')
        ->not->toHaveKey('email')
        ->not->toHaveKey('phone');
});

it('prevents buyers and admins from entering seller-only endpoints', function () {
    $buyer = makePatchUser(UserRole::BUYER, 'gate-buyer');
    $admin = makePatchUser(UserRole::ADMIN, 'gate-admin');
    $seller = makePatchUser(UserRole::SELLER, 'gate-seller');

    $this->actingAs($buyer, 'sanctum')
        ->getJson('/api/v1/seller/dashboard')->assertForbidden();
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/seller/store')->assertForbidden();

    // A verified, authorized seller with no store should receive 404,
    // not the EMAIL_NOT_VERIFIED authorization rejection (403).
    $this->actingAs($seller, 'sanctum')
        ->getJson('/api/v1/seller/store')->assertNotFound();
});

it('supports authenticated seller image uploads without exposing it to buyers', function () {
    Storage::fake('public');
    $buyer = makePatchUser(UserRole::BUYER, 'upload-buyer');
    $seller = makePatchUser(UserRole::SELLER, 'upload-seller');

    // A real 1x1 PNG, created without GD (the PHP GD extension is optional
    // in local testing; don't use UploadedFile::fake()->image()).
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNI6VkFAALyAZv8/XvfAAAAAElFTkSuQmCC',
        true
    );

    $this->actingAs($buyer, 'sanctum')
        ->post('/api/v1/seller/media', [
            'type' => 'product',
            'image' => UploadedFile::fake()->createWithContent('product.png', $png),
        ], ['Accept' => 'application/json'])->assertForbidden();

    $response = $this->actingAs($seller, 'sanctum')
        ->post('/api/v1/seller/media', [
            'type' => 'product',
            'image' => UploadedFile::fake()->createWithContent('product.png', $png),
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['path', 'url']]);

    Storage::disk('public')->assertExists($response->json('data.path'));
});

it('renders the branded HTML receipt and retains a text fallback view', function () {
    $receipt = [
        'order_number' => 'ORD-TEST',
        'store_name' => 'Sample Foods',
        'buyer_name' => 'Buyer',
        'items' => [[
            'product_title' => 'Karahi', 'quantity' => 1,
            'unit_price' => '850.00', 'subtotal' => '850.00',
        ]],
        'total_amount' => '850.00',
        'payment_status' => 'unpaid',
    ];

    $html = view('emails.order-notification', [
        'receipt' => $receipt,
        'notificationType' => 'order_created',
        'body' => 'Order received.',
    ])->render();

    expect($html)->toContain('ORD-TEST', 'Sample Foods', 'Karahi', 'not proof of payment');

    $plain = view('emails.order-notification-text', [
        'receipt' => $receipt,
        'notificationType' => 'order_created',
        'body' => 'Order received.',
    ])->render();

    expect($plain)->toContain('Order received.');
});
