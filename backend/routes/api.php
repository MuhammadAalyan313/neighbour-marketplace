<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckoutBatchController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CheckoutPreviewController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SellerApplicationController;
use App\Http\Controllers\Api\V1\SellerDashboardController;
use App\Http\Controllers\Api\V1\SellerOrderController;
use App\Http\Controllers\Api\V1\SellerMediaController;
use App\Http\Controllers\Api\V1\SellerPaymentController;
use App\Http\Controllers\Api\V1\SellerProductController;
use App\Http\Controllers\Api\V1\SellerStoreController;
use App\Http\Controllers\Api\V1\SubscriptionController;

use App\Http\Controllers\Api\V1\Admin\DashboardController
    as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\UserController
    as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\StoreController
    as AdminStoreController;
use App\Http\Controllers\Api\V1\Admin\OrderController
    as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\SellerApplicationController
    as AdminSellerApplicationController;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {
        Route::post('/register', [
            AuthController::class,
            'register',
        ])->middleware('throttle:5,1');

        Route::post('/login', [AuthController::class,'login',])->middleware('throttle:5,1')
          ->name('login');

        Route::post('/password/forgot', [PasswordResetController::class,'sendOtp',])->middleware('throttle:3,1');

        Route::post('/password/reset', [PasswordResetController::class,'reset',])->middleware('throttle:5,1');
    });

    /*
    |--------------------------------------------------------------------------
    | Public Marketplace
    |--------------------------------------------------------------------------
    */

    Route::prefix('marketplace')->group(function () {
        Route::get('/stores', [
            MarketplaceController::class,
            'stores',
        ]);

        Route::get('/stores/{store:slug}', [
            MarketplaceController::class,
            'store',
        ]);

        Route::get('/categories', [
            MarketplaceController::class,
            'categories',
        ]);

        Route::get('/products', [
            MarketplaceController::class,
            'products',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Public Reviews
    |--------------------------------------------------------------------------
    */

    Route::get('/stores/{store:slug}/reviews', [
        ReviewController::class,
        'index',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */

    Route::post('/checkout/preview', [
        CheckoutPreviewController::class,
        'store',
    ])->middleware('throttle:30,1');

    Route::post('/checkout', [
        CheckoutController::class,
        'store',
    ])->middleware('throttle:10,1');

    /*
    |--------------------------------------------------------------------------
    | Order Tracking
    |--------------------------------------------------------------------------
    */

    Route::get('/orders/{order:order_number}', [
        OrderController::class,
        'show',
    ])->middleware('throttle:30,1');

    Route::get(
        '/checkout-batches/{checkoutBatch:batch_number}',
        [
            CheckoutBatchController::class,
            'show',
        ]
    )->middleware('throttle:30,1');

    /*
    |--------------------------------------------------------------------------
    | Authenticated APIs
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'auth:sanctum',
        'active.user',
    ])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Authenticated User
        |--------------------------------------------------------------------------
        */

        Route::prefix('auth')->group(function () {
            Route::get('/me', [
                AuthController::class,
                'me',
            ]);

            Route::post('/logout', [
                AuthController::class,
                'logout',
            ]);

            Route::post('/logout-all', [
                AuthController::class,
                'logoutAll',
            ])->middleware('throttle:3,1');

            Route::get('/sessions', [
                AuthController::class,
                'sessions',
            ]);

            Route::delete('/sessions/{tokenId}', [
                AuthController::class,
                'revokeSession',
            ])->whereNumber('tokenId')
              ->middleware('throttle:10,1');

            Route::post('/email/send-otp', [
                EmailVerificationController::class,
                'send',
            ])->middleware('throttle:3,1');

            Route::post('/email/verify-otp', [
                EmailVerificationController::class,
                'verify',
            ])->middleware('throttle:10,1');
        });

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        Route::get('/notifications', [
            NotificationController::class,
            'index',
        ]);

        Route::get('/notifications/unread-count', [
            NotificationController::class,
            'unreadCount',
        ]);

        Route::patch('/notifications/read-all', [
            NotificationController::class,
            'markAllAsRead',
        ]);

        Route::patch('/notifications/{id}/read', [
            NotificationController::class,
            'markAsRead',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Push Subscriptions
        |--------------------------------------------------------------------------
        */

        Route::post('/push-subscriptions', [
            PushSubscriptionController::class,
            'store',
        ])->middleware('throttle:10,1');

        Route::delete('/push-subscriptions', [
            PushSubscriptionController::class,
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Buyer Orders
        |--------------------------------------------------------------------------
        */

        Route::get('/orders', [
            OrderController::class,
            'index',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Seller Applications
        |--------------------------------------------------------------------------
        */

        Route::get('/seller-application', [
            SellerApplicationController::class,
            'show',
        ]);

        Route::post('/seller-application', [
            SellerApplicationController::class,
            'store',
        ])->middleware('email.verified');

        Route::delete('/seller-application', [
            SellerApplicationController::class,
            'cancel',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Buyer Reviews
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/orders/{order:order_number}/review',
            [
                ReviewController::class,
                'store',
            ]
        );

        Route::patch('/reviews/{review}', [
            ReviewController::class,
            'update',
        ]);

        Route::delete('/reviews/{review}', [
            ReviewController::class,
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Admin APIs
        |--------------------------------------------------------------------------
        */

        Route::middleware([
            'admin',
            'email.verified',
        ])
            ->prefix('admin')
            ->group(function () {

                Route::get('/dashboard', [
                    AdminDashboardController::class,
                    'index',
                ]);

                Route::get('/users', [
                    AdminUserController::class,
                    'index',
                ]);

                Route::get('/stores', [
                    AdminStoreController::class,
                    'index',
                ]);

                Route::get('/stores/{store}', [
                    AdminStoreController::class,
                    'show',
                ]);

                Route::patch('/stores/{store}/status', [
                    AdminStoreController::class,
                    'updateStatus',
                ]);

                Route::get('/orders', [
                    AdminOrderController::class,
                    'index',
                ]);

                Route::get('/orders/{order:order_number}', [
                    AdminOrderController::class,
                    'show',
                ]);

                Route::get('/seller-applications', [
                    AdminSellerApplicationController::class,
                    'index',
                ]);

                Route::patch(
                    '/seller-applications/{application}/approve',
                    [
                        AdminSellerApplicationController::class,
                        'approve',
                    ]
                );

                Route::patch(
                    '/seller-applications/{application}/reject',
                    [
                        AdminSellerApplicationController::class,
                        'reject',
                    ]
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Seller APIs
        |--------------------------------------------------------------------------
        */

        Route::prefix('seller')
            ->middleware(['seller', 'email.verified'])
            ->group(function () {

                /*
                | Seller Dashboard
                */

                Route::get('/dashboard', [
                    SellerDashboardController::class,
                    'index',
                ]);

                /*
                | Seller Store
                */

                Route::get('/store', [
                    SellerStoreController::class,
                    'show',
                ]);

                Route::post('/store', [
                    SellerStoreController::class,
                    'store',
                ]);

                Route::patch('/store', [
                    SellerStoreController::class,
                    'update',
                ]);

                Route::patch('/store/payment-methods', [
                    SellerStoreController::class,
                    'updatePaymentMethods',
                ])->middleware('throttle:10,1');

                /*
                | Seller Image Upload (public storage)
                */

                Route::post('/media', [
                    SellerMediaController::class,
                    'store',
                ])->middleware('throttle:10,1');

                /*
                | Seller Products
                */

                Route::get('/products', [
                    SellerProductController::class,
                    'index',
                ]);

                Route::post('/products', [
                    SellerProductController::class,
                    'store',
                ]);

                Route::get('/products/{product}', [
                    SellerProductController::class,
                    'show',
                ]);

                Route::patch('/products/{product}', [
                    SellerProductController::class,
                    'update',
                ]);

                Route::delete('/products/{product}', [
                    SellerProductController::class,
                    'destroy',
                ]);

                /*
                | Seller Orders
                */

                Route::get('/orders', [
                    SellerOrderController::class,
                    'index',
                ]);

                Route::get('/orders/{order:order_number}', [
                    SellerOrderController::class,
                    'show',
                ]);

                Route::patch(
                    '/orders/{order:order_number}/status',
                    [
                        SellerOrderController::class,
                        'updateStatus',
                    ]
                );

                Route::patch(
                    '/orders/{order:order_number}/payment/confirm',
                    [
                        SellerPaymentController::class,
                        'confirm',
                    ]
                )->middleware('throttle:10,1');

                /*
                | Subscriptions
                */

                Route::get('/subscription/plans', [
                    SubscriptionController::class,
                    'plans',
                ]);

                Route::get('/subscription', [
                    SubscriptionController::class,
                    'show',
                ]);

                Route::patch('/subscription/cancel', [
                    SubscriptionController::class,
                    'cancel',
                ]);

                Route::post('/subscription/activate-free', [
                    SubscriptionController::class,
                    'activateFree',
                ])->middleware('throttle:5,1');
            });
    });
});
