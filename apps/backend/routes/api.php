<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\OpsController;
use App\Http\Controllers\Api\Admin\ResourceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\ShowcaseController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\StudioController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

/* REST API Krafio (PRD Bab 33). Autentikasi SPA: cookie HttpOnly Sanctum. */

Route::prefix('public')->middleware('throttle:120,1,r1')->group(function () {
    Route::get('settings', [PublicController::class, 'settings']);
    Route::get('plans', [PublicController::class, 'plans']);
    Route::get('addons', [PublicController::class, 'addons']);
    Route::get('catalog/unlocks', [PublicController::class, 'unlocks']);
    Route::get('templates', [PublicController::class, 'templates']);
    Route::get('faqs', [PublicController::class, 'faqs']);
    Route::get('legal/{type}', [PublicController::class, 'legal'])->whereIn('type', ['terms', 'privacy', 'refund', 'affiliate_terms', 'cookies']);
    Route::get('status', [PublicController::class, 'status']);
    Route::post('ref/{code}', [BillingController::class, 'trackClick'])->middleware('throttle:30,1,r2');
});

Route::post('support/tickets', [PublicController::class, 'ticket']);
Route::get('media/{media}/{name?}', [ShowcaseController::class, 'serve'])->where('name', '.*');
Route::post('webhooks/payment/{gateway}', [BillingController::class, 'webhook'])->middleware('throttle:300,1,r3');

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1,r4');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1,r5');
    Route::post('forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:10,1,r6');
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:10,1,r7');
    Route::post('admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1,r8');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('otp/status', [AuthController::class, 'otpStatus']);
        Route::post('otp/verify', [AuthController::class, 'otpVerify'])->middleware('throttle:20,1,r9');
        Route::post('otp/resend', [AuthController::class, 'otpResend'])->middleware('throttle:10,1,r10');
        Route::post('otp/change-email', [AuthController::class, 'otpChangeEmail'])->middleware('throttle:5,1,r11');
        Route::post('admin/mfa', [AuthController::class, 'adminMfa'])->middleware('throttle:10,1,r12');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me', [MeController::class, 'update']);
    Route::patch('me/preferences', [MeController::class, 'preferences']);
    Route::put('me/password', [MeController::class, 'password']);
    // Akun buatan Admin: verifikasi email ATAU nomor HP
    Route::get('me/contact-verification', [AccountController::class, 'verifyStatus']);
    Route::post('me/contact-verification/send', [AccountController::class, 'verifySend'])->middleware('throttle:10,1,acv1');
    Route::post('me/contact-verification/confirm', [AccountController::class, 'verifyConfirm'])->middleware('throttle:20,1,acv2');
    Route::get('me/sessions', [MeController::class, 'sessions']);
    Route::post('me/sessions/revoke-others', [MeController::class, 'revokeOthers']);
    Route::post('me/consents', [MeController::class, 'consent']);
    Route::post('consents/cookie', [MeController::class, 'cookieConsent']);
    Route::post('me/ai-consent', [MeController::class, 'aiConsent']);
    Route::get('me/notifications', [MeController::class, 'notifications']);
    Route::post('me/notifications/read-all', [MeController::class, 'readAll']);
    Route::post('me/notifications/{id}/read', [MeController::class, 'readNotification']);
    Route::get('me/notification-preferences', [MeController::class, 'notificationPrefs']);
    Route::put('me/notification-preferences', [MeController::class, 'saveNotificationPrefs']);
    Route::post('me/revision', [MeController::class, 'revision']);
    Route::post('me/deactivate', [MeController::class, 'deactivate']);

    /* ---------------- User Panel ---------------- */
    Route::prefix('app')->middleware('active')->group(function () {
        Route::get('dashboard', [WorkspaceController::class, 'dashboard']);
        Route::get('activity', [WorkspaceController::class, 'activity']);

        Route::get('jobs/estimate', [StudioController::class, 'estimate']);
        Route::get('jobs/history', [StudioController::class, 'history']);
        Route::get('jobs', [StudioController::class, 'index']);
        Route::post('jobs', [StudioController::class, 'store'])->middleware('throttle:30,1,r13');
        Route::post('jobs/{job}/cancel', [StudioController::class, 'cancel']);
        Route::post('uploads', [StudioController::class, 'upload'])->middleware('throttle:30,1,r14');
        Route::get('assets', [StudioController::class, 'assets']);
        Route::get('projects', [StudioController::class, 'projects']);
        Route::post('projects', [StudioController::class, 'createProject']);
        Route::get('projects/{project}', [StudioController::class, 'showProject']);
        Route::delete('projects/{project}', [StudioController::class, 'deleteProject']);
        Route::put('projects/{project}', [StudioController::class, 'updateProject']);
        Route::post('projects/{project}/duplicate', [StudioController::class, 'duplicateProject']);
        Route::post('projects/{id}/restore', [StudioController::class, 'restoreProject']);
        Route::get('assistant', [StudioController::class, 'assistant']);
        Route::post('assistant', [StudioController::class, 'ask'])->middleware('throttle:30,1,r15');

        Route::get('websites', [WorkspaceController::class, 'websites']);
        Route::post('websites', [WorkspaceController::class, 'createWebsite']);
        Route::get('websites/{website}', [WorkspaceController::class, 'showWebsite']);
        Route::put('websites/{website}', [WorkspaceController::class, 'updateWebsite']);
        Route::post('websites/{website}/publish', [WorkspaceController::class, 'publishWebsite']);
        Route::get('seo/audits', [WorkspaceController::class, 'seoAudits']);
        Route::post('seo/audits', [WorkspaceController::class, 'runSeo'])->middleware('throttle:10,1,r16');
        Route::patch('seo/findings/{finding}', [WorkspaceController::class, 'updateFinding']);
        Route::get('social', [WorkspaceController::class, 'social']);
        Route::post('social/{platform}/connect', [WorkspaceController::class, 'connectSocial']);
        Route::post('social/{platform}/disconnect', [WorkspaceController::class, 'disconnectSocial']);
        Route::get('tickets', [WorkspaceController::class, 'tickets']);
        Route::get('tickets/{ticket}', [WorkspaceController::class, 'ticket']);
        Route::post('tickets/{ticket}/messages', [WorkspaceController::class, 'replyTicket']);
        // Pusat pesan pengguna (inbox + balas cepat)
        Route::get('inbox', [InboxController::class, 'userIndex']);
        Route::post('inbox', [InboxController::class, 'userCreate'])->middleware('throttle:10,1,inb1');
        Route::get('inbox/feed', [InboxController::class, 'userFeed']);
        Route::get('inbox/{ticket}', [InboxController::class, 'userShow']);
        Route::post('inbox/{ticket}/messages', [InboxController::class, 'userReply'])->middleware('throttle:30,1,inb2');
        Route::post('inbox/{ticket}/typing', [InboxController::class, 'userTyping'])->middleware('throttle:60,1,inb3');
        Route::post('inbox/{ticket}/read', [InboxController::class, 'userRead']);
        Route::post('inbox-attachments', [ShowcaseController::class, 'upload'])->middleware('throttle:20,1,inb5');
        Route::get('hero', [ShowcaseController::class, 'hero']);
        Route::get('wallet/cards', [ShowcaseController::class, 'walletCards']);

        Route::get('subscription', [BillingController::class, 'subscription']);
        Route::post('orders/quote', [BillingController::class, 'quote'])->middleware('throttle:60,1,r17');
        Route::post('orders', [BillingController::class, 'order'])->middleware('throttle:20,1,r18');
        Route::delete('subscription/scheduled-change', [BillingController::class, 'cancelDowngrade']);
        Route::patch('subscription/auto-renew', [BillingController::class, 'autoRenew']);
        Route::post('trial', [BillingController::class, 'trial'])->middleware('throttle:5,1,r19');
        Route::get('credits/history', [BillingController::class, 'creditHistory']);
        Route::get('addons', [BillingController::class, 'myAddons']);
        Route::get('custom-packages', [BillingController::class, 'customPackages']);
        Route::post('custom-packages/request', [BillingController::class, 'requestCustom'])->middleware('throttle:5,1,r20');
        Route::get('wallet/ledger', [BillingController::class, 'ledger']);
        Route::post('wallet/topups', [BillingController::class, 'topup'])->middleware('throttle:10,1,r21');
        Route::post('wallet/topups/{payment}/simulate-paid', [BillingController::class, 'simulatePaid']);
        Route::get('payments/{payment}', [BillingController::class, 'paymentStatus'])->middleware('throttle:60,1,jk1');
        Route::get('wallet/destinations', [BillingController::class, 'destinations']);
        Route::post('wallet/destinations', [BillingController::class, 'addDestination']);
        Route::delete('wallet/destinations/{destination}', [BillingController::class, 'deleteDestination']);
        Route::get('wallet/withdrawals', [BillingController::class, 'withdrawals']);
        Route::post('wallet/withdrawals', [BillingController::class, 'requestWithdrawal'])->middleware('throttle:5,1,r22');
        Route::get('affiliate', [BillingController::class, 'affiliate']);
        Route::get('affiliate/commissions', [BillingController::class, 'commissions']);
    });

    /* ---------------- Admin Panel ---------------- */
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::post('reauth', [AdminController::class, 'reauth'])->middleware('throttle:10,1,r23');
        Route::get('session/ping', [AdminController::class, 'ping']);
        Route::post('session/ping', [AdminController::class, 'ping']);
        Route::get('badges', [AdminController::class, 'badges']);
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('users', [AdminController::class, 'users']);
        Route::post('users', [AccountController::class, 'store'])->middleware('throttle:20,1,acc1');
        Route::post('users/{user}/reset-password', [AccountController::class, 'resetPassword'])->middleware('throttle:10,1,acc2');
        Route::get('users/{user}', [AdminController::class, 'user']);
        Route::post('users/{user}/reveal', [AdminController::class, 'reveal']);
        Route::post('users/{user}/status', [AdminController::class, 'setStatus']);
        Route::post('users/{user}/balance', [AdminController::class, 'adjustBalance']);
        Route::post('users/{user}/quota-reset', [AdminController::class, 'resetQuota']);
        Route::get('approvals', [AdminController::class, 'approvals']);
        Route::post('approvals/{approval}/decide', [AdminController::class, 'decide']);
        Route::post('withdrawals/{withdrawal}/status', [AdminController::class, 'withdrawalStatus']);
        Route::get('settings', [AdminController::class, 'settings']);
        Route::put('settings', [AdminController::class, 'saveSettings']);
        Route::get('system', [AdminController::class, 'system']);
        Route::get('ai/usage', [AdminController::class, 'aiUsage']);
        Route::post('ai-providers/{provider}/rotate', [AdminController::class, 'rotateKey']);
        Route::post('ai-providers/{provider}/test', [AdminController::class, 'testProvider'])->middleware('throttle:10,1,r24');
        Route::get('tickets/{ticket}', [AdminController::class, 'ticket']);
        Route::post('tickets/{ticket}/messages', [AdminController::class, 'replyTicket']);
        Route::patch('tickets/{ticket}', [AdminController::class, 'updateTicket']);
        Route::post('custom-packages/{package}/offer', [AdminController::class, 'offerCustomPackage']);

        // Inbox admin, hero showcase, media, template studio, kartu dompet platform
        Route::get('inbox', [InboxController::class, 'adminIndex']);
        Route::get('inbox/feed', [InboxController::class, 'adminFeed']);
        Route::get('inbox/canned', [InboxController::class, 'cannedReplies']);
        Route::post('inbox/bulk', [InboxController::class, 'adminBulk']);
        Route::get('inbox/{ticket}', [InboxController::class, 'adminShow']);
        Route::patch('inbox/{ticket}', [InboxController::class, 'adminUpdate']);
        Route::post('inbox/{ticket}/messages', [InboxController::class, 'adminReply']);
        Route::post('inbox/{ticket}/notes', [InboxController::class, 'adminNote']);
        Route::post('inbox/{ticket}/typing', [InboxController::class, 'adminTyping'])->middleware('throttle:60,1,inb4');
        Route::post('inbox/{ticket}/read', [InboxController::class, 'adminRead']);
        Route::get('inbox/{ticket}/export', [InboxController::class, 'adminExport']);
        Route::get('hero', [ShowcaseController::class, 'heroAdmin']);
        Route::get('hero/live', [ShowcaseController::class, 'hero']);
        Route::put('hero', [ShowcaseController::class, 'saveHero']);
        Route::post('media', [ShowcaseController::class, 'upload'])->middleware('throttle:30,1,med1');
        Route::post('templates/reorder', [ShowcaseController::class, 'reorderTemplates']);
        Route::post('templates/{template}/duplicate', [ShowcaseController::class, 'duplicateTemplate']);
        Route::get('wallet/cards', [ShowcaseController::class, 'walletCards']);

        Route::get('integrations/{kind}', [OpsController::class, 'integration']);
        Route::put('integrations/{kind}', [OpsController::class, 'saveIntegration']);
        Route::post('integrations/{kind}/test', [OpsController::class, 'testIntegration'])->middleware('throttle:10,1,ops1');
        Route::post('whatsapp/test-send', [OpsController::class, 'testWhatsappSend'])->middleware('throttle:10,1,ops2');
        Route::post('whatsapp/sync-templates', [OpsController::class, 'syncWaTemplates'])->middleware('throttle:10,1,ops3');
        Route::get('reports/finance', [OpsController::class, 'financeReport']);
        Route::get('reports/messaging', [OpsController::class, 'messagingReport']);
        Route::get('backups', [OpsController::class, 'backups']);
        Route::post('backups/run', [OpsController::class, 'runBackup'])->middleware('throttle:5,1,ops4');
        Route::get('backups/{backup}/download', [OpsController::class, 'downloadBackup']);
        Route::delete('backups/{backup}', [OpsController::class, 'deleteBackup']);
        Route::post('maintenance/prune', [OpsController::class, 'prune'])->middleware('throttle:5,1,ops5');

        Route::get('r/{resource}', [ResourceController::class, 'index']);
        Route::post('r/{resource}', [ResourceController::class, 'store']);
        Route::post('r/{resource}/bulk', [ResourceController::class, 'bulk']);
        Route::get('r/{resource}/{id}', [ResourceController::class, 'show']);
        Route::put('r/{resource}/{id}', [ResourceController::class, 'update']);
        Route::delete('r/{resource}/{id}', [ResourceController::class, 'destroy']);
    });
});
