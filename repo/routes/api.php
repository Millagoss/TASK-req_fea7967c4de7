<?php

use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BehaviorEventController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationTemplateController;
use App\Http\Controllers\Api\V1\PlaylistController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\ServiceAccountController;
use App\Http\Controllers\Api\V1\SongController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserProfileController;
use App\Http\Controllers\Api\V1\EvaluationCycleController;
use App\Http\Controllers\Api\V1\LeaderProfileController;
use App\Http\Controllers\Api\V1\RewardPenaltyTypeController;
use App\Http\Controllers\Api\V1\DisciplinaryRecordController;
use App\Http\Controllers\Api\V1\MeasurementCodeController;
use App\Http\Controllers\Api\V1\UnitConversionController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\ResultController;
use App\Http\Middleware\ApplyDataScopes;
use App\Http\Middleware\AuditAdminAction;
use App\Http\Middleware\ThrottleServiceAccount;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbStatus = 'ok';
    } catch (\Exception $e) {
        $dbStatus = 'unavailable';
    }

    $cacheStatus = (function () {
        try {
            \Illuminate\Support\Facades\Cache::put('health_check', true, 10);
            return \Illuminate\Support\Facades\Cache::get('health_check') ? 'ok' : 'unavailable';
        } catch (\Exception $e) {
            return 'unavailable';
        }
    })();

    $status = ($dbStatus === 'ok' && $cacheStatus === 'ok') ? 'ok' : 'degraded';

    return response()->json([
        'status'    => $status,
        'timestamp' => now()->toIso8601String(),
        'services'  => [
            'database' => $dbStatus,
            'cache'    => $cacheStatus,
        ],
        'version'   => config('app.version', '1.0.0'),
    ], $status === 'ok' ? 200 : 503);
});

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Auth routes — public
    // -------------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', ThrottleServiceAccount::class])->group(function () {
            Route::post('/logout',     [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::get('/me',          [AuthController::class, 'me']);
        });
    });

    // -------------------------------------------------------------------------
    // Admin routes — require authentication + data scopes + audit logging
    // -------------------------------------------------------------------------
    Route::prefix('admin')
        ->middleware([
            'auth:sanctum',
            ThrottleServiceAccount::class,
            ApplyDataScopes::class,
            AuditAdminAction::class,
        ])
        ->group(function () {

            // Users
            Route::get('/users',                  [UserController::class, 'index'])->middleware('permission:users.list');
            Route::post('/users',                  [UserController::class, 'store'])->middleware('permission:users.create');
            Route::put('/users/{id}',              [UserController::class, 'update'])->middleware('permission:users.update');
            Route::post('/users/{id}/roles',       [UserController::class, 'assignRoles'])->middleware('permission:users.update');
            Route::delete('/users/{id}/roles/{roleId}', [UserController::class, 'removeRole'])->middleware('permission:users.update');

            // Service accounts
            Route::post('/service-accounts',          [ServiceAccountController::class, 'store'])->middleware('permission:service_accounts.create');
            Route::post('/service-accounts/{id}/rotate', [ServiceAccountController::class, 'rotate'])->middleware('permission:service_accounts.create');

            // Roles
            Route::get('/roles',                       [RoleController::class, 'index'])->middleware('permission:roles.list');
            Route::post('/roles',                      [RoleController::class, 'store'])->middleware('permission:roles.create');
            Route::put('/roles/{id}',                  [RoleController::class, 'update'])->middleware('permission:roles.update');
            Route::post('/roles/{id}/permissions',     [RoleController::class, 'assignPermissions'])->middleware('permission:roles.update');

            // Permissions
            Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.list');
        });

    // -------------------------------------------------------------------------
    // Phase 2: Music Library routes — require authentication + permissions
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', AuditAdminAction::class])->group(function () {

        // Songs
        Route::get('/songs',                     [SongController::class, 'index'])->middleware('permission:music.read');
        Route::post('/songs',                    [SongController::class, 'store'])->middleware('permission:music.create');
        Route::get('/songs/{id}',                [SongController::class, 'show'])->middleware('permission:music.read');
        Route::put('/songs/{id}',                [SongController::class, 'update'])->middleware('permission:music.update');
        Route::delete('/songs/{id}',             [SongController::class, 'destroy'])->middleware('permission:music.delete');
        Route::post('/songs/{id}/publish',       [SongController::class, 'publish'])->middleware('permission:music.publish');
        Route::post('/songs/{id}/unpublish',     [SongController::class, 'unpublish'])->middleware('permission:music.publish');
        Route::post('/songs/{id}/version',       [SongController::class, 'bumpVersion'])->middleware('permission:music.update');
        Route::post('/songs/{id}/cover-art',     [SongController::class, 'uploadCoverArt'])->middleware('permission:music.update');

        // Albums
        Route::get('/albums',                    [AlbumController::class, 'index'])->middleware('permission:music.read');
        Route::post('/albums',                   [AlbumController::class, 'store'])->middleware('permission:music.create');
        Route::get('/albums/{id}',               [AlbumController::class, 'show'])->middleware('permission:music.read');
        Route::put('/albums/{id}',               [AlbumController::class, 'update'])->middleware('permission:music.update');
        Route::delete('/albums/{id}',            [AlbumController::class, 'destroy'])->middleware('permission:music.delete');
        Route::post('/albums/{id}/publish',      [AlbumController::class, 'publish'])->middleware('permission:music.publish');
        Route::post('/albums/{id}/unpublish',    [AlbumController::class, 'unpublish'])->middleware('permission:music.publish');
        Route::post('/albums/{id}/version',      [AlbumController::class, 'bumpVersion'])->middleware('permission:music.update');
        Route::post('/albums/{id}/cover-art',    [AlbumController::class, 'uploadCoverArt'])->middleware('permission:music.update');

        // Album songs
        Route::get('/albums/{id}/songs',         [AlbumController::class, 'showSongs'])->middleware('permission:music.read');
        Route::post('/albums/{id}/songs',        [AlbumController::class, 'addSong'])->middleware('permission:music.update');
        Route::delete('/albums/{id}/songs/{songId}', [AlbumController::class, 'removeSong'])->middleware('permission:music.update');

        // Playlists
        Route::get('/playlists',                 [PlaylistController::class, 'index'])->middleware('permission:music.read');
        Route::post('/playlists',                [PlaylistController::class, 'store'])->middleware('permission:music.create');
        Route::get('/playlists/{id}',            [PlaylistController::class, 'show'])->middleware('permission:music.read');
        Route::put('/playlists/{id}',            [PlaylistController::class, 'update'])->middleware('permission:music.update');
        Route::delete('/playlists/{id}',         [PlaylistController::class, 'destroy'])->middleware('permission:music.delete');
        Route::post('/playlists/{id}/publish',   [PlaylistController::class, 'publish'])->middleware('permission:music.publish');
        Route::post('/playlists/{id}/unpublish', [PlaylistController::class, 'unpublish'])->middleware('permission:music.publish');
        Route::post('/playlists/{id}/version',   [PlaylistController::class, 'bumpVersion'])->middleware('permission:music.update');

        // Playlist songs
        Route::get('/playlists/{id}/songs',      [PlaylistController::class, 'showSongs'])->middleware('permission:music.read');
        Route::post('/playlists/{id}/songs',     [PlaylistController::class, 'addSong'])->middleware('permission:music.update');
        Route::delete('/playlists/{id}/songs/{songId}', [PlaylistController::class, 'removeSong'])->middleware('permission:music.update');
    });

    // -------------------------------------------------------------------------
    // Phase 3: Behavior & Analytics routes
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', AuditAdminAction::class])->group(function () {
        Route::post('/behavior/events', [BehaviorEventController::class, 'store']);
        Route::get('/behavior/events', [BehaviorEventController::class, 'index'])->middleware('permission:users.list');

        Route::get('/users/{id}/profile', [UserProfileController::class, 'show']);
        Route::post('/users/{id}/profile/recompute', [UserProfileController::class, 'recompute'])->middleware('permission:users.list');

        Route::get('/recommendations/{userId}', [RecommendationController::class, 'show']);
    });

    // -------------------------------------------------------------------------
    // Phase 4: Notifications & Task Center
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', AuditAdminAction::class])->group(function () {
        // Notification Templates (admin)
        Route::get('/notification-templates', [NotificationTemplateController::class, 'index']);
        Route::post('/notification-templates', [NotificationTemplateController::class, 'store'])->middleware('permission:roles.create');
        Route::put('/notification-templates/{id}', [NotificationTemplateController::class, 'update'])->middleware('permission:roles.update');
        Route::delete('/notification-templates/{id}', [NotificationTemplateController::class, 'destroy'])->middleware('permission:roles.update');

        // Send notifications
        Route::post('/notifications/send', [NotificationController::class, 'send'])->middleware('permission:users.list');
        Route::post('/notifications/send-bulk', [NotificationController::class, 'sendBulk'])->middleware('permission:users.list');

        // User notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

        // Subscriptions
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::put('/subscriptions', [SubscriptionController::class, 'update']);
    });

    // -------------------------------------------------------------------------
    // Phase 5: Rewards / Penalties
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', ApplyDataScopes::class, AuditAdminAction::class])->group(function () {
        // Evaluation cycles
        Route::get('/evaluation-cycles', [EvaluationCycleController::class, 'index'])->middleware('permission:users.list');
        Route::post('/evaluation-cycles', [EvaluationCycleController::class, 'store'])->middleware('permission:roles.create');
        Route::get('/evaluation-cycles/{id}', [EvaluationCycleController::class, 'show'])->middleware('permission:users.list');
        Route::put('/evaluation-cycles/{id}', [EvaluationCycleController::class, 'update'])->middleware('permission:roles.update');
        Route::post('/evaluation-cycles/{id}/activate', [EvaluationCycleController::class, 'activate'])->middleware('permission:roles.update');
        Route::post('/evaluation-cycles/{id}/close', [EvaluationCycleController::class, 'close'])->middleware('permission:roles.update');

        // Leader profiles
        Route::get('/leader-profiles', [LeaderProfileController::class, 'index'])->middleware('permission:users.list');
        Route::post('/leader-profiles', [LeaderProfileController::class, 'store'])->middleware('permission:users.create');
        Route::get('/leader-profiles/{id}', [LeaderProfileController::class, 'show'])->middleware('permission:users.list');
        Route::put('/leader-profiles/{id}', [LeaderProfileController::class, 'update'])->middleware('permission:users.update');

        // Reward/penalty types
        Route::get('/reward-penalty-types', [RewardPenaltyTypeController::class, 'index']);
        Route::post('/reward-penalty-types', [RewardPenaltyTypeController::class, 'store'])->middleware('permission:roles.create');
        Route::put('/reward-penalty-types/{id}', [RewardPenaltyTypeController::class, 'update'])->middleware('permission:roles.update');

        // Disciplinary records — stats BEFORE {id} to avoid route conflict
        Route::get('/disciplinary-records/stats', [DisciplinaryRecordController::class, 'stats'])->middleware('permission:users.list');
        Route::get('/disciplinary-records', [DisciplinaryRecordController::class, 'index'])->middleware('permission:users.list');
        Route::post('/disciplinary-records', [DisciplinaryRecordController::class, 'store'])->middleware('permission:users.create');
        Route::get('/disciplinary-records/{id}', [DisciplinaryRecordController::class, 'show'])->middleware('permission:users.list');
        Route::post('/disciplinary-records/{id}/appeal', [DisciplinaryRecordController::class, 'appeal']);
        Route::post('/disciplinary-records/{id}/clear', [DisciplinaryRecordController::class, 'clear'])->middleware('permission:disciplinary.clear');
    });

    // -------------------------------------------------------------------------
    // Phase 6: Result Entry (Lab-style Data Collection)
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', ApplyDataScopes::class, AuditAdminAction::class])->group(function () {
        // Measurement codes
        Route::get('/measurement-codes', [MeasurementCodeController::class, 'index']);
        Route::post('/measurement-codes', [MeasurementCodeController::class, 'store'])->middleware('permission:roles.create');
        Route::get('/measurement-codes/{id}', [MeasurementCodeController::class, 'show']);
        Route::put('/measurement-codes/{id}', [MeasurementCodeController::class, 'update'])->middleware('permission:roles.update');

        // Unit conversions
        Route::get('/unit-conversions', [UnitConversionController::class, 'index']);
        Route::post('/unit-conversions', [UnitConversionController::class, 'store'])->middleware('permission:roles.create');

        // Subjects (PII-aware)
        Route::get('/subjects', [SubjectController::class, 'index']);
        Route::post('/subjects', [SubjectController::class, 'store'])->middleware('permission:users.create');
        Route::get('/subjects/{id}', [SubjectController::class, 'show']);
        Route::put('/subjects/{id}', [SubjectController::class, 'update'])->middleware('permission:users.update');

        // Results — static routes BEFORE {id}
        Route::post('/results/batch', [ResultController::class, 'batch']);
        Route::post('/results/import-csv', [ResultController::class, 'importCsv']);
        Route::get('/results/flagged', [ResultController::class, 'flagged'])->middleware('permission:results.review');
        Route::post('/results/recompute-stats', [ResultController::class, 'recomputeStats'])->middleware('permission:roles.update');
        Route::post('/results', [ResultController::class, 'store']);
        Route::get('/results', [ResultController::class, 'index'])->middleware('permission:results.review');
        Route::get('/results/{id}', [ResultController::class, 'show'])->middleware('permission:results.review');
        Route::post('/results/{id}/review', [ResultController::class, 'review'])->middleware('permission:results.review');
    });
});
