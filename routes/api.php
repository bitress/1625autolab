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
Route::get('/auth/verify-email', [AuthController::class, 'verifyEmail']);

Route::post('/bookings', [BookingController::class, 'create']);
Route::get('/bookings/availability', [BookingController::class, 'availability']);

Route::post('/inquiries', [InquiryController::class, 'create']);

Route::get('/bookings/{id}/build-updates', [BuildUpdateController::class, 'list']);

Route::get('/reviews/published', [ReviewController::class, 'publishedList']);
Route::get('/bookings/{id}/review', [ReviewController::class, 'getForBooking']);

Route::post('/contact', [ContactController::class, 'send']);

Route::get('/vehicles/makes', [VehicleController::class, 'makes']);
Route::get('/vehicles/models', [VehicleController::class, 'models']);
Route::get('/vehicles/trims', [VehicleController::class, 'trims']);

Route::post('/waitlist', [WaitlistController::class, 'join']);
Route::get('/waitlist/claim/{token}', [WaitlistController::class, 'claimGet']);

Route::get('/manychat/menu', [ManyChatController::class, 'menu']);
Route::post('/manychat/variants', [ManyChatController::class, 'drillDown']); // Matches snippet 'getManyChatDrillDown'

Route::get('/posts', [PostController::class, 'index']); // from '/fb/posts' wait, snippet has /api/posts

// Public read-only for settings/misc
Route::get('/shop/hours', [ShopHoursController::class, 'get']);
Route::get('/shop/closed-dates', [ShopHoursController::class, 'closedDatesGet']);
Route::get('/site-settings', [SiteSettingsController::class, 'get']);
Route::get('/portfolio-categories', [PortfolioCategoryController::class, 'list']);
Route::get('/portfolio-categories/{id}', [PortfolioCategoryController::class, 'get']);

// Admin backdoor (should ideally be protected, keeping public for dev/migration parity)
Route::post('/admin/cron/daily', [CronController::class, 'daily']);
Route::post('/admin/cron/process-queue', [CronController::class, 'processQueue']);
Route::post('/admin/migrate', [MigrationController::class, 'up']);
Route::get('/admin/migrate', [MigrationController::class, 'status']);

//
// Routes that can optionally use auth payload (e.g. to show drafts if admin)
//
Route::middleware(['optional.auth'])->group(function () {
    Route::get('/bookings', [BookingController::class, 'list']);
    Route::get('/bookings/{id}', [BookingController::class, 'get'])->where('id', '\d+');

    Route::get('/services', [ServiceController::class, 'list']);
    Route::get('/services/{id}', [ServiceController::class, 'get'])->where('id', '\d+');
    Route::get('/services/slug/{slug}', [ServiceController::class, 'getBySlug']);

    Route::get('/products', [ProductController::class, 'list']);
    Route::get('/products/{id}', [ProductController::class, 'get']);

    Route::post('/orders', [OrderController::class, 'create']);
    Route::get('/orders/{id}', [OrderController::class, 'get'])->where('id', '\d+');

    Route::get('/blog', [BlogController::class, 'list']);
    Route::get('/blog/{id}', [BlogController::class, 'get'])->where('id', '\d+');

    Route::get('/portfolio', [PortfolioController::class, 'list']);
    Route::get('/portfolio/{id}', [PortfolioController::class, 'get'])->where('id', '\d+');
    Route::get('/portfolio/slug/{slug}', [PortfolioController::class, 'getBySlug']);

    Route::get('/team-members', [TeamMemberController::class, 'list']);
    Route::get('/testimonials', [TestimonialController::class, 'list']);
    Route::get('/faq', [FaqController::class, 'list']);

    Route::get('/offers', [OfferController::class, 'list']);
    Route::get('/offers/{id}', [OfferController::class, 'get'])->where('id', '\d+');

    Route::get('/before-after', [BeforeAfterController::class, 'list']);
    Route::get('/before-after/{id}', [BeforeAfterController::class, 'get'])->where('id', '\d+');
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
    Route::post('/auth/avatar-upload', [AuthController::class, 'avatarUpload']); // Added based on snippet
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
    Route::get('/auth/data-export', [AuthController::class, 'dataExport']);
    Route::delete('/auth/account', [AuthController::class, 'accountDelete']);
    Route::get('/auth/notification-preferences', [AuthController::class, 'notificationPrefsGet']);
    Route::put('/auth/notification-preferences', [AuthController::class, 'notificationPrefsSave']);
    Route::get('/auth/sessions', [AuthController::class, 'sessionList']); // Snippet
    Route::delete('/auth/sessions/revoke-others', [AuthController::class, 'sessionRevokeOthers']); // Snippet
    Route::delete('/auth/sessions/{id}', [AuthController::class, 'sessionRevoke'])->where('id', '\d+'); // Snippet

    // Customer specific stats
    Route::get('/customers/{userId}/stats', [CustomerController::class, 'stats']);

    // Bookings
    Route::get('/bookings/mine', [BookingController::class, 'mine']);
    // Update methods to match PATCH as per snippet
    Route::patch('/bookings/{id}/assign-tech', [BookingController::class, 'assignTech']);
    Route::patch('/bookings/{id}/reschedule', [BookingController::class, 'reschedule']);
    Route::patch('/bookings/{id}/admin-reschedule', [BookingController::class, 'adminReschedule']); // Added based on snippet
    Route::patch('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::patch('/bookings/{id}/notes', [BookingController::class, 'internalNotes']);
    Route::patch('/bookings/{id}/qa-photos', [BookingController::class, 'qaPhotosUpdate']); // Added based on snippet
    Route::patch('/bookings/{id}/calibration', [BookingController::class, 'calibrationUpdate']); // Added based on snippet
    Route::get('/bookings/{id}/activity', [BookingController::class, 'activityList']);
    Route::patch('/bookings/{id}', [BookingController::class, 'update']);
    Route::delete('/bookings/{id}', [BookingController::class, 'delete']);

    // Inquiries
    Route::get('/inquiries', [InquiryController::class, 'list']);
    Route::get('/inquiries/mine', [InquiryController::class, 'mine']);
    Route::get('/inquiries/calendar', [InquiryController::class, 'calendar']);
    Route::get('/inquiries/availability', [InquiryController::class, 'availability']);
    Route::get('/inquiries/{id}', [InquiryController::class, 'get']);
    Route::patch('/inquiries/{id}', [InquiryController::class, 'update']);
    Route::delete('/inquiries/{id}', [InquiryController::class, 'delete']);
    Route::get('/inquiries/{id}/activity', [InquiryController::class, 'activity']);

    // Build Updates
    Route::post('/bookings/{id}/build-updates', [BuildUpdateController::class, 'create']);
    Route::post('/bookings/{id}/build-updates/media', [BuildUpdateController::class, 'mediaUpload']);

    // Parts
    Route::get('/bookings/{id}/parts/requirements', [BookingPartRequirementController::class, 'list']);
    Route::post('/bookings/{id}/parts/requirements', [BookingPartRequirementController::class, 'create']);
    Route::patch('/bookings/{id}/parts/requirements/{rid}', [BookingPartRequirementController::class, 'update']);
    Route::patch('/bookings/{id}/parts', [BookingPartRequirementController::class, 'partsUpdate']); // From handleBookingPartsUpdate

    // Reviews
    Route::post('/bookings/{id}/review', [ReviewController::class, 'create']);
    Route::get('/reviews', [ReviewController::class, 'list']);
    Route::patch('/reviews/{id}/approve', [ReviewController::class, 'approve']);
    Route::patch('/reviews/{id}/reject', [ReviewController::class, 'reject']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'delete']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'list']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'delete']);

    // Garage
    Route::get('/client/vehicles', [VehicleGarageController::class, 'list']);
    Route::post('/client/vehicles', [VehicleGarageController::class, 'create']);
    Route::put('/client/vehicles/{id}', [VehicleGarageController::class, 'update'])->where('id', '\d+');
    Route::delete('/client/vehicles/{id}', [VehicleGarageController::class, 'delete'])->where('id', '\d+');
    Route::post('/client/vehicles/media', [VehicleGarageController::class, 'mediaUpload']);

    // Orders
    Route::get('/orders/mine', [OrderController::class, 'mine']);
    Route::get('/admin/orders', [OrderController::class, 'adminList']);
    Route::patch('/admin/orders/{id}/status', [OrderController::class, 'adminStatusUpdate']);
    Route::patch('/admin/orders/{id}/tracking', [OrderController::class, 'adminTrackingUpdate']);
    Route::patch('/admin/orders/{id}/payment', [OrderController::class, 'adminPaymentUpdate']);

    // Waitlist
    Route::get('/waitlist', [WaitlistController::class, 'list']);
    Route::delete('/waitlist/{id}', [WaitlistController::class, 'remove']);

    // Content Mutators
    Route::post('/services', [ServiceController::class, 'create']);
    Route::put('/services/{id}', [ServiceController::class, 'update'])->where('id', '\d+');
    Route::delete('/services/{id}', [ServiceController::class, 'delete'])->where('id', '\d+');
    Route::get('/services/{id}/variations', [ServiceController::class, 'variationList']); // Missing previously?
    Route::post('/services/{id}/variations', [ServiceController::class, 'variationCreate']);
    Route::put('/services/{id}/variations/{vid}', [ServiceController::class, 'variationUpdate']);
    Route::delete('/services/{id}/variations/{vid}', [ServiceController::class, 'variationDelete']);

    Route::post('/products', [ProductController::class, 'create']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'delete']);
    Route::get('/products/{id}/variations', [ProductController::class, 'variationList']); // Added
    Route::post('/products/{id}/variations', [ProductController::class, 'variationCreate']);
    Route::put('/products/{id}/variations/{vid}', [ProductController::class, 'variationUpdate']);
    Route::delete('/products/{id}/variations/{vid}', [ProductController::class, 'variationDelete']);

    Route::post('/blog', [BlogController::class, 'create']);
    Route::put('/blog/{id}', [BlogController::class, 'update'])->where('id', '\d+');
    Route::delete('/blog/{id}', [BlogController::class, 'delete'])->where('id', '\d+');

    Route::post('/portfolio', [PortfolioController::class, 'create']);
    Route::put('/portfolio/{id}', [PortfolioController::class, 'update'])->where('id', '\d+');
    Route::delete('/portfolio/{id}', [PortfolioController::class, 'delete'])->where('id', '\d+');

    Route::post('/portfolio-categories', [PortfolioCategoryController::class, 'create']);
    Route::put('/portfolio-categories/{id}', [PortfolioCategoryController::class, 'update'])->where('id', '\d+');
    Route::delete('/portfolio-categories/{id}', [PortfolioCategoryController::class, 'delete'])->where('id', '\d+');

    Route::put('/shop/hours', [ShopHoursController::class, 'update']);
    Route::post('/shop/closed-dates', [ShopHoursController::class, 'closedDatesAdd']);
    Route::delete('/shop/closed-dates/{date}', [ShopHoursController::class, 'closedDatesRemove']);

    Route::put('/site-settings', [SiteSettingsController::class, 'update']);

    Route::post('/team-members', [TeamMemberController::class, 'create']);
    Route::put('/team-members/{id}', [TeamMemberController::class, 'update'])->where('id', '\d+');
    Route::delete('/team-members/{id}', [TeamMemberController::class, 'delete'])->where('id', '\d+');

    Route::post('/testimonials', [TestimonialController::class, 'create']);
    Route::put('/testimonials/{id}', [TestimonialController::class, 'update'])->where('id', '\d+');
    Route::delete('/testimonials/{id}', [TestimonialController::class, 'delete'])->where('id', '\d+');

    Route::post('/faq', [FaqController::class, 'create']);
    Route::put('/faq/{id}', [FaqController::class, 'update'])->where('id', '\d+');
    Route::delete('/faq/{id}', [FaqController::class, 'delete'])->where('id', '\d+');

    Route::post('/offers', [OfferController::class, 'create']);
    Route::put('/offers/{id}', [OfferController::class, 'update'])->where('id', '\d+');
    Route::delete('/offers/{id}', [OfferController::class, 'delete'])->where('id', '\d+');

    Route::post('/before-after', [BeforeAfterController::class, 'create']);
    Route::put('/before-after/{id}', [BeforeAfterController::class, 'update'])->where('id', '\d+');
    Route::delete('/before-after/{id}', [BeforeAfterController::class, 'delete'])->where('id', '\d+');

    // Admin Users
    Route::get('/admin/users', [UserController::class, 'list']);
    Route::get('/admin/users/assignable', [UserController::class, 'assignable']);
    Route::post('/admin/users', [UserController::class, 'create']);
    Route::patch('/admin/users/{id}/role', [UserController::class, 'roleUpdate']);
    Route::patch('/admin/users/{id}/status', [UserController::class, 'statusUpdate']);
    Route::patch('/admin/users/{id}/info', [UserController::class, 'infoUpdate']);
    Route::delete('/admin/users/{id}', [UserController::class, 'delete']);

    // Admin Clients
    Route::get('/admin/clients', [ClientController::class, 'list']);
    Route::get('/admin/clients/{id}/bookings', [ClientController::class, 'bookings']);
    Route::get('/admin/clients/{id}/vehicles', [ClientController::class, 'vehicles']);
    Route::get('/admin/customers/{id}/360', [ClientController::class, 'customer360']);

    // Admin Roles
    Route::get('/admin/roles', [RoleController::class, 'list']);
    Route::get('/admin/roles/audit', [RoleController::class, 'auditList']);
    Route::post('/admin/roles', [RoleController::class, 'create']);
    Route::put('/admin/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/admin/roles/{id}', [RoleController::class, 'delete']);

    // Admin Security
    Route::get('/admin/security/audit', [SecurityController::class, 'auditList']);
    Route::get('/admin/security/audit/export', [SecurityController::class, 'auditExport']);

    // Admin Activity Log
    Route::get('/admin/activity-logs/users', [ActivityLogController::class, 'users']);
    Route::get('/admin/activity-logs', [ActivityLogController::class, 'list']);

    // Admin Semaphore
    Route::get('/admin/semaphore/account', [SemaphoreController::class, 'account']);
    Route::get('/admin/semaphore/messages', [SemaphoreController::class, 'messages']);

    // Admin Notification Queue
    Route::get('/admin/notification-queue', [NotificationQueueController::class, 'list']);
    Route::get('/admin/notification-queue/health', [NotificationQueueController::class, 'health']);
    Route::post('/admin/notification-queue/replay-failed', [NotificationQueueController::class, 'replayFailed']);
    Route::post('/admin/notification-queue/{id}/replay', [NotificationQueueController::class, 'replayOne']);

    // Admin Campaigns
    Route::get('/admin/campaigns', [CampaignController::class, 'list']);
    Route::get('/admin/campaigns/{id}', [CampaignController::class, 'get'])->where('id', '\d+');
    Route::post('/admin/campaigns', [CampaignController::class, 'create']);
    Route::patch('/admin/campaigns/{id}', [CampaignController::class, 'update'])->where('id', '\d+');
    Route::delete('/admin/campaigns/{id}', [CampaignController::class, 'delete'])->where('id', '\d+');
    Route::post('/admin/campaigns/{id}/run', [CampaignController::class, 'run']);
    Route::post('/admin/campaigns/{id}/dry-run', [CampaignController::class, 'dryRun']);
    Route::post('/admin/campaigns/run-scheduled', [CampaignController::class, 'runScheduled']);
    Route::get('/admin/campaigns/{id}/analytics', [CampaignController::class, 'analytics']);
    Route::get('/admin/campaign-audiences/{type}', [CampaignController::class, 'audience']);

    // Admin Inventory
    Route::get('/admin/inventory/items', [InventoryController::class, 'itemList']);
    Route::post('/admin/inventory/items', [InventoryController::class, 'itemCreate']);
    Route::patch('/admin/inventory/items/{id}', [InventoryController::class, 'itemUpdate']);
    Route::get('/admin/inventory/movements', [InventoryController::class, 'movementList']);
    Route::post('/admin/inventory/adjust', [InventoryController::class, 'adjust']);
    Route::get('/admin/inventory/alerts', [InventoryController::class, 'alertList']);
    Route::get('/admin/inventory/suppliers', [InventoryController::class, 'supplierList']);
    Route::post('/admin/inventory/suppliers', [InventoryController::class, 'supplierCreate']);
    Route::get('/admin/inventory/purchase-orders', [InventoryController::class, 'purchaseOrderList']);
    Route::post('/admin/inventory/purchase-orders', [InventoryController::class, 'purchaseOrderCreate']);
    Route::patch('/admin/inventory/purchase-orders/{id}/status', [InventoryController::class, 'purchaseOrderStatus']);

    // Admin Stats
    Route::get('/admin/stats', [StatsController::class, 'dashboard']);

    // Admin Media
    Route::post('/admin/upload', [MediaController::class, 'upload']);

    // Admin Inquiry Link
    Route::post('/admin/inquiries/link-guests', [InquiryLinkController::class, 'link']);
});
