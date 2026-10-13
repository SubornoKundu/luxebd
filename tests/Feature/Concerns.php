<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;

trait Concerns
{
    protected function makeUser(string $email = 'buyer@example.com', string $role = 'customer', string $password = 'Secret@123'): User
    {
        $user = new User(['name' => ucfirst($role).' Person', 'email' => $email, 'password' => $password]);
        $user->forceFill(['role' => $role, 'email_verified_at' => now()])->save();

        return $user;
    }

    protected function makeAdmin(): User
    {
        return $this->makeUser('boss@example.com', 'admin');
    }

    protected function makeProduct(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $brand = Brand::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Test Phone '.uniqid(),
            'slug' => 'test-phone-'.uniqid(),
            'description' => 'A phone',
            'price' => 1000,
            'discount_price' => null,
            'image' => '/images/vireluxe/products/rayhaan-lion.jpg',
            'stock' => 10,
            'is_featured' => false,
            'is_active' => true,
        ], $overrides));
    }

    protected function addToCart(User $user, Product $product, int $qty = 1): CartItem
    {
        return CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => $qty]);
    }

    /** @return array<string, string> */
    protected function shipping(array $extra = []): array
    {
        return array_merge([
            'shipping_name' => 'Buyer One',
            'shipping_phone' => '01711111111',
            'shipping_address' => 'House 1, Road 2',
            'shipping_city' => 'Dhaka',
            'payment_method' => 'cod',
        ], $extra);
    }
}
