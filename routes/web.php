<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TrackOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefront
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/search/suggest', [SearchController::class, 'suggest'])
    ->middleware('throttle:60,1')->name('search.suggest');

// Smaller copies of pictures for phones (see ThumbController).
Route::get('/thumbs/{width}/{path}', [\App\Http\Controllers\ThumbController::class, 'show'])
    ->whereNumber('width')
    ->where('path', 'images/.+');

Route::get('/recently-viewed', [ProductController::class, 'recent'])
    ->middleware('throttle:60,1')->name('products.recent');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

// Help & policy pages linked from the footer.
foreach (array_keys(PageController::PAGES) as $slug) {
    Route::get('/'.$slug, [PageController::class, 'show'])->defaults('slug', $slug)->name('pages.'.$slug);
}

// "Where is my order?" -- order number + phone, no account needed.
Route::get('/track-order', [TrackOrderController::class, 'show'])
    ->middleware('throttle:20,1')->name('track.show');

/*
|--------------------------------------------------------------------------
| Authentication (guests only) -- email + password
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    // Admins sign in here (not on /login); customers cannot use this page.
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])
        ->middleware('throttle:10,1')->name('admin.login.store');

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1')->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'edit'])->name('password.code');
    Route::post('/reset-password/check', [PasswordResetController::class, 'check'])
        ->middleware('throttle:15,1')->name('password.check');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:10,1')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated customer area
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::post('/cart/bundle', [CartController::class, 'storeBundle'])->name('cart.bundle');
    Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('/account/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/account/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('/account/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::patch('/account/addresses/{address}/default', [AddressController::class, 'default'])->name('addresses.default');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product:id}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::post('/coupon', [CouponController::class, 'apply'])
        ->middleware('throttle:30,1')->name('coupon.apply');
    Route::delete('/coupon', [CouponController::class, 'remove'])->name('coupon.remove');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:15,1')->name('checkout.store');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/success', [OrderController::class, 'success'])->name('orders.success');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');

    Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:10,1')->name('reviews.store');
    Route::post('/products/{product}/questions', [QuestionController::class, 'store'])
        ->middleware('throttle:20,1')->name('questions.store');
});

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('products/import-template', [Admin\ProductController::class, 'importTemplate'])->name('products.import-template');
    Route::post('products/import', [Admin\ProductController::class, 'import'])->name('products.import');
    Route::resource('products', Admin\ProductController::class)->except('show')->scoped(['product' => 'id']);

    foreach ([
        'categories' => Admin\CategoryController::class,
        'brands' => Admin\BrandController::class,
        'sliders' => Admin\SliderController::class,
        'coupons' => Admin\CouponController::class,
    ] as $uri => $controller) {
        Route::resource($uri, $controller)->only(['index', 'store', 'update', 'destroy']);
    }

    Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/print', [Admin\OrderController::class, 'print'])->name('orders.print');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
    Route::patch('orders/{order}/courier', [Admin\OrderController::class, 'courier'])->name('orders.courier');
    Route::patch('orders/{order}/payment', [Admin\OrderController::class, 'updatePayment'])->name('orders.payment');

    Route::get('inventory', [Admin\InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/{product:id}/restock', [Admin\InventoryController::class, 'restock'])->name('inventory.restock');

    Route::get('questions', [Admin\QuestionController::class, 'index'])->name('questions.index');
    Route::patch('questions/{question}', [Admin\QuestionController::class, 'answer'])->name('questions.answer');
    Route::patch('questions/{question}/toggle', [Admin\QuestionController::class, 'toggle'])->name('questions.toggle');
    Route::delete('questions/{question}', [Admin\QuestionController::class, 'destroy'])->name('questions.destroy');

    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::patch('customers/{customer}/block', [Admin\CustomerController::class, 'toggleBlock'])->name('customers.block');

    Route::get('admins', [Admin\AdminUserController::class, 'index'])->name('admins.index');
    Route::post('admins', [Admin\AdminUserController::class, 'store'])->middleware('throttle:10,1')->name('admins.store');
    Route::post('admins/promote', [Admin\AdminUserController::class, 'promote'])->middleware('throttle:10,1')->name('admins.promote');
    Route::delete('admins/{admin}', [Admin\AdminUserController::class, 'demote'])->name('admins.demote');

    // Hidden unless SHOP_SETTINGS_ACCESS=true in .env (see config/shop.php).
    Route::middleware('settings.open')->group(function () {
        Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });

    Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('reviews/{review}', [Admin\ReviewController::class, 'toggle'])->name('reviews.toggle');
    Route::delete('reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
});

/*
|--------------------------------------------------------------------------
| Anything else -> the branded 404 page (runs inside the web middleware so the
| header / footer data is available).
|--------------------------------------------------------------------------
*/
Route::fallback(fn () => abort(404));
