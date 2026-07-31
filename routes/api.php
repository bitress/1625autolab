<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\CampaignController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\CronController;
use App\Http\Controllers\Api\Admin\InquiryLinkController;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\MediaController;
use App\Http\Controllers\Api\Admin\MigrationController;
use App\Http\Controllers\Api\Admin\NotificationQueueController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SecurityController;
use App\Http\Controllers\Api\Admin\SemaphoreController;
use App\Http\Controllers\Api\Admin\StatsController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VehicleCatalogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BeforeAfterController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingPartRequirementController;
use App\Http\Controllers\Api\BuildUpdateController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\ManyChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PortfolioCategoryController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ShopHoursController;
use App\Http\Controllers\Api\SiteSettingsController;
use App\Http\Controllers\Api\TeamMemberController;
use App\Http\Controllers\Api\TestimonialController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleGarageController;
use App\Http\Controllers\Api\WaitlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//
// Public/Unauthenticated Routes
//
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);

Route::post('/booking/create', [BookingController::class, 'create']);
Route::get('/booking/availability', [BookingController::class, 'availability']);

Route::post('/inquiry/create', [InquiryController::class, 'create']);

Route::get('/build-updates/list', [BuildUpdateController::class, 'list']);

Route::get('/reviews/published', [ReviewController::class, 'publishedList']);
Route::get('/reviews/booking/{bookingId}', [ReviewController::class, 'getForBooking']);

Route::post('/contact/send', [ContactController::class, 'send']);

Route::get('/vehicles/makes', [VehicleController::class, 'makes']);
Route::get('/vehicles/models', [VehicleController::class, 'models']);
Route::get('/vehicles/trims', [VehicleController::class, 'trims']);

Route::post('/waitlist/join', [WaitlistController::class, 'join']);
Route::get('/waitlist/claim', [WaitlistController::class, 'claimGet']);

Route::get('/manychat/menu', [ManyChatController::class, 'menu']);
Route::get('/manychat/drill-down', [ManyChatController::class, 'drillDown']);

Route::get('/fb/posts', [PostController::class, 'index']);

// Public read-only for settings/misc
Route::get('/shop-hours', [ShopHoursController::class, 'get']);
Route::get('/shop-hours/closed-dates', [ShopHoursController::class, 'closedDatesGet']);
Route::get('/settings', [SiteSettingsController::class, 'get']);
Route::get('/portfolio/categories', [PortfolioCategoryController::class, 'list']);
Route::get('/portfolio/categories/{id}', [PortfolioCategoryController::class, 'get']);

// Admin backdoor (should ideally be protected, keeping public for dev/migration parity)
Route::post('/admin/cron/daily', [CronController::class, 'daily']);
Route::post('/admin/cron/process-queue', [CronController::class, 'processQueue']);
Route::post('/admin/migration/up', [MigrationController::class, 'up']);

//
// Routes that can optionally use auth payload (e.g. to show drafts if admin)
//
Route::middleware(['optional_auth'])->group(function () {
    Route::get('/booking/list', [BookingController::class, 'list']);
    Route::get('/booking/get/{id}', [BookingController::class, 'get']);

    Route::get('/services/list', [ServiceController::class, 'list']);
    Route::get('/services/get/{id}', [ServiceController::class, 'get']);
    Route::get('/services/slug/{slug}', [ServiceController::class, 'getBySlug']);

    Route::get('/products/list', [ProductController::class, 'list']);
    Route::get('/products/get/{id}', [ProductController::class, 'get']);

    Route::post('/orders/create', [OrderController::class, 'create']);
    Route::get('/orders/get/{id}', [OrderController::class, 'get']);

    Route::get('/posts/list', [BlogController::class, 'list']);
    Route::get('/posts/get/{id}', [BlogController::class, 'get']);

    Route::get('/portfolio/list', [PortfolioController::class, 'list']);
    Route::get('/portfolio/get/{id}', [PortfolioController::class, 'get']);
    Route::get('/portfolio/slug/{slug}', [PortfolioController::class, 'getBySlug']);

    Route::get('/team/list', [TeamMemberController::class, 'list']);
    Route::get('/testimonials/list', [TestimonialController::class, 'list']);
    Route::get('/faqs/list', [FaqController::class, 'list']);

    Route::get('/offers/list', [OfferController::class, 'list']);
    Route::get('/offers/get/{id}', [OfferController::class, 'get']);

    Route::get('/before-after/list', [BeforeAfterController::class, 'list']);
    Route::get('/before-after/get/{id}', [BeforeAfterController::class, 'get']);
});

//
// Authenticated Routes
//
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth Profile
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::put('/auth/profile', [AuthController::class, 'profile']);
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
    Route::get('/auth/data-export', [AuthController::class, 'dataExport']);
    Route::delete('/auth/account', [AuthController::class, 'accountDelete']);
    Route::get('/auth/notification-prefs', [AuthController::class, 'notificationPrefsGet']);
    Route::put('/auth/notification-prefs', [AuthController::class, 'notificationPrefsSave']);

    // Customer specific stats
    Route::get('/customer/stats', [CustomerController::class, 'stats']);

    // Bookings
    Route::get('/booking/mine', [BookingController::class, 'mine']);
    Route::put('/booking/status/{id}', [BookingController::class, 'updateStatus']);
    Route::put('/booking/assign-tech/{id}', [BookingController::class, 'assignTech']);
    Route::put('/booking/reschedule/{id}', [BookingController::class, 'reschedule']);
    Route::put('/booking/cancel/{id}', [BookingController::class, 'cancel']);
    Route::put('/booking/internal-notes/{id}', [BookingController::class, 'internalNotes']);
    Route::get('/booking/activity/{id}', [BookingController::class, 'activityList']);

    // Inquiries
    Route::get('/inquiry/list', [InquiryController::class, 'list']);
    Route::get('/inquiry/get/{id}', [InquiryController::class, 'get']);
    Route::put('/inquiry/update/{id}', [InquiryController::class, 'update']);
    Route::delete('/inquiry/delete/{id}', [InquiryController::class, 'delete']);
    Route::get('/inquiry/activity/{id}', [InquiryController::class, 'activity']);

    // Build Updates
    Route::post('/build-updates/create', [BuildUpdateController::class, 'create']);

    // Parts
    Route::get('/booking/{bookingId}/parts', [BookingPartRequirementController::class, 'list']);
    Route::post('/booking/{bookingId}/parts', [BookingPartRequirementController::class, 'create']);
    Route::put('/booking/{bookingId}/parts/{reqId}', [BookingPartRequirementController::class, 'update']);

    // Reviews
    Route::post('/reviews/booking/{bookingId}', [ReviewController::class, 'create']);
    Route::get('/reviews/list', [ReviewController::class, 'list']);
    Route::put('/reviews/approve/{id}', [ReviewController::class, 'approve']);
    Route::put('/reviews/reject/{id}', [ReviewController::class, 'reject']);
    Route::delete('/reviews/delete/{id}', [ReviewController::class, 'delete']);

    // Notifications
    Route::get('/notifications/list', [NotificationController::class, 'list']);
    Route::put('/notifications/read/{id}', [NotificationController::class, 'read']);
    Route::put('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::delete('/notifications/delete/{id}', [NotificationController::class, 'delete']);

    // Garage
    Route::get('/garage/list', [VehicleGarageController::class, 'list']);
    Route::post('/garage/add', [VehicleGarageController::class, 'create']);
    Route::put('/garage/update/{id}', [VehicleGarageController::class, 'update']);
    Route::delete('/garage/remove/{id}', [VehicleGarageController::class, 'delete']);
    Route::post('/garage/media/{id}', [VehicleGarageController::class, 'mediaUpload']);

    // Orders
    Route::get('/orders/mine', [OrderController::class, 'mine']);
    Route::get('/orders/admin/list', [OrderController::class, 'adminList']);
    Route::put('/orders/admin/status/{id}', [OrderController::class, 'adminStatusUpdate']);
    Route::put('/orders/admin/tracking/{id}', [OrderController::class, 'adminTrackingUpdate']);
    Route::put('/orders/admin/payment/{id}', [OrderController::class, 'adminPaymentUpdate']);

    // Waitlist
    Route::get('/waitlist/list', [WaitlistController::class, 'list']);
    Route::delete('/waitlist/remove/{id}', [WaitlistController::class, 'remove']);

    // Content Mutators
    Route::post('/services/create', [ServiceController::class, 'create']);
    Route::put('/services/update/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/delete/{id}', [ServiceController::class, 'delete']);
    Route::post('/services/{id}/variations', [ServiceController::class, 'variationCreate']);
    Route::put('/services/{id}/variations/{varId}', [ServiceController::class, 'variationUpdate']);
    Route::delete('/services/{id}/variations/{varId}', [ServiceController::class, 'variationDelete']);

    Route::post('/products/create', [ProductController::class, 'create']);
    Route::put('/products/update/{id}', [ProductController::class, 'update']);
    Route::delete('/products/delete/{id}', [ProductController::class, 'delete']);
    Route::post('/products/{id}/variations', [ProductController::class, 'variationCreate']);
    Route::put('/products/{id}/variations/{varId}', [ProductController::class, 'variationUpdate']);
    Route::delete('/products/{id}/variations/{varId}', [ProductController::class, 'variationDelete']);

    Route::post('/posts/create', [BlogController::class, 'create']);
    Route::put('/posts/update/{id}', [BlogController::class, 'update']);
    Route::delete('/posts/delete/{id}', [BlogController::class, 'delete']);

    Route::post('/portfolio/create', [PortfolioController::class, 'create']);
    Route::put('/portfolio/update/{id}', [PortfolioController::class, 'update']);
    Route::delete('/portfolio/delete/{id}', [PortfolioController::class, 'delete']);

    Route::post('/portfolio/categories', [PortfolioCategoryController::class, 'create']);
    Route::put('/portfolio/categories/{id}', [PortfolioCategoryController::class, 'update']);
    Route::delete('/portfolio/categories/{id}', [PortfolioCategoryController::class, 'delete']);

    Route::put('/shop-hours', [ShopHoursController::class, 'update']);
    Route::post('/shop-hours/closed-dates', [ShopHoursController::class, 'closedDatesAdd']);
    Route::delete('/shop-hours/closed-dates/{date}', [ShopHoursController::class, 'closedDatesRemove']);

    Route::put('/settings', [SiteSettingsController::class, 'update']);

    Route::post('/team/create', [TeamMemberController::class, 'create']);
    Route::put('/team/update/{id}', [TeamMemberController::class, 'update']);
    Route::delete('/team/delete/{id}', [TeamMemberController::class, 'delete']);

    Route::post('/testimonials/create', [TestimonialController::class, 'create']);
    Route::put('/testimonials/update/{id}', [TestimonialController::class, 'update']);
    Route::delete('/testimonials/delete/{id}', [TestimonialController::class, 'delete']);

    Route::post('/faqs/create', [FaqController::class, 'create']);
    Route::put('/faqs/update/{id}', [FaqController::class, 'update']);
    Route::delete('/faqs/delete/{id}', [FaqController::class, 'delete']);

    Route::post('/offers/create', [OfferController::class, 'create']);
    Route::put('/offers/update/{id}', [OfferController::class, 'update']);
    Route::delete('/offers/delete/{id}', [OfferController::class, 'delete']);

    Route::post('/before-after/create', [BeforeAfterController::class, 'create']);
    Route::put('/before-after/update/{id}', [BeforeAfterController::class, 'update']);
    Route::delete('/before-after/delete/{id}', [BeforeAfterController::class, 'delete']);

    // Admin Users
    Route::get('/admin/users/list', [UserController::class, 'list']);
    Route::get('/admin/users/assignable', [UserController::class, 'assignable']);
    Route::post('/admin/users/create', [UserController::class, 'create']);
    Route::put('/admin/users/role/{id}', [UserController::class, 'roleUpdate']);
    Route::put('/admin/users/status/{id}', [UserController::class, 'statusUpdate']);
    Route::put('/admin/users/info/{id}', [UserController::class, 'infoUpdate']);
    Route::delete('/admin/users/delete/{id}', [UserController::class, 'delete']);

    // Admin Clients
    Route::get('/admin/clients/list', [ClientController::class, 'list']);
    Route::get('/admin/clients/bookings/{id}', [ClientController::class, 'bookings']);
    Route::get('/admin/clients/vehicles/{id}', [ClientController::class, 'vehicles']);
    Route::get('/admin/clients/customer360/{id}', [ClientController::class, 'customer360']);

    // Admin Roles
    Route::get('/admin/roles/list', [RoleController::class, 'list']);
    Route::get('/admin/roles/audit', [RoleController::class, 'auditList']);
    Route::post('/admin/roles/create', [RoleController::class, 'create']);
    Route::put('/admin/roles/update/{id}', [RoleController::class, 'update']);
    Route::delete('/admin/roles/delete/{id}', [RoleController::class, 'delete']);

    // Admin Security
    Route::get('/admin/security/audit', [SecurityController::class, 'auditList']);
    Route::get('/admin/security/export', [SecurityController::class, 'auditExport']);

    // Admin Activity Log
    Route::get('/admin/activity/users', [ActivityLogController::class, 'users']);
    Route::get('/admin/activity/list', [ActivityLogController::class, 'list']);

    // Admin Semaphore
    Route::get('/admin/semaphore/account', [SemaphoreController::class, 'account']);
    Route::get('/admin/semaphore/messages', [SemaphoreController::class, 'messages']);

    // Admin Notification Queue
    Route::get('/admin/notification-queue/list', [NotificationQueueController::class, 'list']);
    Route::get('/admin/notification-queue/health', [NotificationQueueController::class, 'health']);
    Route::post('/admin/notification-queue/replay', [NotificationQueueController::class, 'replayFailed']);
    Route::post('/admin/notification-queue/replay/{id}', [NotificationQueueController::class, 'replayOne']);

    // Admin Campaigns
    Route::get('/admin/campaigns/list', [CampaignController::class, 'list']);
    Route::get('/admin/campaigns/get/{id}', [CampaignController::class, 'get']);
    Route::post('/admin/campaigns/create', [CampaignController::class, 'create']);
    Route::put('/admin/campaigns/update/{id}', [CampaignController::class, 'update']);
    Route::delete('/admin/campaigns/delete/{id}', [CampaignController::class, 'delete']);
    Route::post('/admin/campaigns/run/{id}', [CampaignController::class, 'run']);
    Route::post('/admin/campaigns/dry-run/{id}', [CampaignController::class, 'dryRun']);
    Route::get('/admin/campaigns/analytics/{id}', [CampaignController::class, 'analytics']);
    Route::get('/admin/campaigns/audience', [CampaignController::class, 'audience']);

    // Admin Inventory
    Route::get('/admin/inventory/items', [InventoryController::class, 'itemList']);
    Route::post('/admin/inventory/items', [InventoryController::class, 'itemCreate']);
    Route::put('/admin/inventory/items/{id}', [InventoryController::class, 'itemUpdate']);
    Route::get('/admin/inventory/movements', [InventoryController::class, 'movementList']);
    Route::post('/admin/inventory/adjust', [InventoryController::class, 'adjust']);
    Route::get('/admin/inventory/alerts', [InventoryController::class, 'alertList']);
    Route::get('/admin/inventory/suppliers', [InventoryController::class, 'supplierList']);
    Route::post('/admin/inventory/suppliers', [InventoryController::class, 'supplierCreate']);
    Route::get('/admin/inventory/purchase-orders', [InventoryController::class, 'purchaseOrderList']);
    Route::post('/admin/inventory/purchase-orders', [InventoryController::class, 'purchaseOrderCreate']);
    Route::put('/admin/inventory/purchase-orders/{id}', [InventoryController::class, 'purchaseOrderStatus']);

    // Admin Vehicle Catalog
    Route::get('/admin/vehicle-catalog/makes', [VehicleCatalogController::class, 'makesList']);
    Route::post('/admin/vehicle-catalog/makes', [VehicleCatalogController::class, 'makesCreate']);
    Route::put('/admin/vehicle-catalog/makes/{id}', [VehicleCatalogController::class, 'makesUpdate']);
    Route::delete('/admin/vehicle-catalog/makes/{id}', [VehicleCatalogController::class, 'makesDelete']);
    Route::get('/admin/vehicle-catalog/models', [VehicleCatalogController::class, 'modelsList']);
    Route::post('/admin/vehicle-catalog/models', [VehicleCatalogController::class, 'modelsCreate']);
    Route::put('/admin/vehicle-catalog/models/{id}', [VehicleCatalogController::class, 'modelsUpdate']);
    Route::delete('/admin/vehicle-catalog/models/{id}', [VehicleCatalogController::class, 'modelsDelete']);

    // Admin Stats
    Route::get('/admin/stats/dashboard', [StatsController::class, 'dashboard']);

    // Admin Media
    Route::post('/admin/media/upload', [MediaController::class, 'upload']);

    // Admin Inquiry Link
    Route::post('/admin/inquiry-link/{id}', [InquiryLinkController::class, 'link']);
});
