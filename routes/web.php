<?php

use App\Livewire\Auth\Login;
use App\Livewire\HomePage;
use Illuminate\Support\Facades\Route;

// ── Home ──────────────────────────────────────────────────────────────────────
Route::get('/', HomePage::class)->name('home');

// ── Services ──────────────────────────────────────────────────────────────────
Route::get('/services', fn () => 'Services Page — coming soon')->name('services');
Route::get('/services/{slug}', fn (string $slug) => "Service Detail: {$slug}")->name('services.show');

// ── Products ──────────────────────────────────────────────────────────────────
Route::get('/products', fn () => 'Products Page — coming soon')->name('products');
Route::get('/products/{id}', fn (string $id) => "Product Detail: {$id}")->name('products.show');

// ── Cart & Checkout ───────────────────────────────────────────────────────────
Route::get('/cart', fn () => 'Cart Page — coming soon')->name('cart');
Route::get('/checkout', fn () => 'Checkout Page — coming soon')->name('checkout');

// ── Portfolio / Builds ────────────────────────────────────────────────────────
Route::get('/portfolio', fn () => 'Portfolio Page — coming soon')->name('portfolio');
Route::get('/builds/{slug}', fn (string $slug) => "Build Showcase: {$slug}")->name('builds.show');

// ── Blog ──────────────────────────────────────────────────────────────────────
Route::get('/blog', fn () => 'Blog Page — coming soon')->name('blog');
Route::get('/blog/{id}', fn (string $id) => "Blog Post: {$id}")->name('blog.show');

// ── Misc Pages ────────────────────────────────────────────────────────────────
Route::get('/faq', fn () => 'FAQ Page — coming soon')->name('faq');
Route::get('/contact', fn () => 'Contact Page — coming soon')->name('contact');
Route::get('/about', fn () => 'About Page — coming soon')->name('about');

// ── Booking / Order ───────────────────────────────────────────────────────────
Route::redirect('/booking', '/order')->name('booking');
Route::get('/order', fn () => 'Order / Customer Form — coming soon')->name('order');

// ── Calendar ──────────────────────────────────────────────────────────────────
Route::redirect('/calender', '/calendar')->name('calender'); // typo alias
Route::get('/calendar', fn () => 'Calendar Page — coming soon')->name('calendar');

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', fn () => 'Register Page — coming soon')->name('register');
    Route::get('/forgot-password', fn () => 'Forgot Password — coming soon')->name('password.request');
    Route::get('/reset-password', fn () => 'Reset Password — coming soon')->name('password.reset');
});
