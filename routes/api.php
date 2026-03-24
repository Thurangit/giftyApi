<?php

use App\Http\Controllers\PayController;
use App\Http\Controllers\ReceivePayController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
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

// ============================================
// Routes d'authentification (publiques)
// ============================================
Route::post('/auth/register', [AuthController::class, 'register'])->name('auth_register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth_login');

// ============================================
// Routes protégées par authentification
// ============================================
Route::middleware('auth:sanctum')->group(function () {
    // Profil utilisateur
    Route::get('/auth/profile', [AuthController::class, 'profile'])->name('auth_profile');
    Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth_update_profile');
    Route::post('/auth/upload-avatar', [AuthController::class, 'uploadAvatar'])->name('auth_upload_avatar');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->name('auth_change_password');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth_logout');

    // Codes de parrainage
    Route::get('/referral/my-code', [App\Http\Controllers\ReferralController::class, 'getMyReferralCode'])->name('referral_my_code');
    Route::get('/referral/stats', [App\Http\Controllers\ReferralController::class, 'getMyReferralStats'])->name('referral_stats');
    Route::post('/referral/generate', [App\Http\Controllers\ReferralController::class, 'generateReferralCode'])->name('referral_generate');

    // Codes promo (utilisateur - validation uniquement)
    Route::prefix('promo-codes')->group(function () {
        Route::post('/validate', [App\Http\Controllers\PromoCodeController::class, 'validateCode'])->name('promo_codes_validate');
    });

    // Notifications push
    Route::post('/notifications/subscribe', [App\Http\Controllers\NotificationController::class, 'subscribe'])->name('notifications_subscribe');
    Route::post('/notifications/unsubscribe', [App\Http\Controllers\NotificationController::class, 'unsubscribe'])->name('notifications_unsubscribe');

    // Notifications utilisateur (authentifié)
    Route::get('/user/notifications', [App\Http\Controllers\UserNotificationController::class, 'index'])->name('user_notifications');
    Route::get('/user/notifications/unread-count', [App\Http\Controllers\UserNotificationController::class, 'unreadCount'])->name('user_notifications_unread_count');
    Route::post('/user/notifications/{id}/read', [App\Http\Controllers\UserNotificationController::class, 'markAsRead'])->name('user_notification_mark_read');
    Route::post('/user/notifications/read-all', [App\Http\Controllers\UserNotificationController::class, 'markAllAsRead'])->name('user_notifications_mark_all_read');
    Route::delete('/user/notifications/{id}', [App\Http\Controllers\UserNotificationController::class, 'destroy'])->name('user_notification_delete');
    Route::delete('/user/notifications/read/all', [App\Http\Controllers\UserNotificationController::class, 'deleteAllRead'])->name('user_notifications_delete_all_read');

    // Wallet
    Route::get('/wallet/transactions', [App\Http\Controllers\WalletController::class, 'getTransactions'])->name('wallet_transactions');
    Route::post('/wallet/withdraw', [App\Http\Controllers\WalletController::class, 'withdraw'])->name('wallet_withdraw');
});

Route::post('/send/money/', [PayController::class, 'sendMoney'])->name('send_money');
Route::get('/infos/gift/{ref}', [ReceivePayController::class, 'infoGift'])->name('info_gift');

Route::post('/receive/money', [ReceivePayController::class, 'withdraw'])->name('receive_money');

// Quiz routes
Route::post('/quiz/create', [App\Http\Controllers\QuizController::class, 'createQuiz'])->name('quiz_create');
Route::get('/quiz/result/{attemptId}', [App\Http\Controllers\QuizController::class, 'getQuizResult'])->name('quiz_result');
Route::get('/quiz/{link}', [App\Http\Controllers\QuizController::class, 'getQuiz'])->name('quiz_get');
Route::get('/quiz/{link}/share', [App\Http\Controllers\QuizController::class, 'getQuizShareInfo'])->name('quiz_share_info');
Route::post('/quiz/{link}/check-access', [App\Http\Controllers\QuizController::class, 'checkAccess'])->name('quiz_check_access');
Route::post('/quiz/{link}/challenge/join', [App\Http\Controllers\QuizController::class, 'joinChallenge'])->name('quiz_challenge_join');
Route::post('/quiz/{link}/challenge/creator-withdraw', [App\Http\Controllers\QuizController::class, 'withdrawChallengePot'])->name('quiz_challenge_creator_withdraw');
Route::post('/quiz/{link}/submit', [App\Http\Controllers\QuizController::class, 'submitQuiz'])->name('quiz_submit');
Route::post('/quiz/withdraw-prize', [App\Http\Controllers\QuizController::class, 'withdrawQuizPrize'])->name('quiz_withdraw_prize');

// Moment routes
Route::post('/moment/create', [App\Http\Controllers\MomentController::class, 'createMoment'])->name('moment_create');
Route::get('/moment/result/{attemptId}', [App\Http\Controllers\MomentController::class, 'getMomentResult'])->name('moment_result');
Route::get('/moment/{link}', [App\Http\Controllers\MomentController::class, 'getMoment'])->name('moment_get');
Route::get('/moment/{link}/share', [App\Http\Controllers\MomentController::class, 'getMomentShareInfo'])->name('moment_share_info');
Route::post('/moment/{link}/check-access', [App\Http\Controllers\MomentController::class, 'checkAccess'])->name('moment_check_access');
Route::post('/moment/{link}/submit', [App\Http\Controllers\MomentController::class, 'submitMoment'])->name('moment_submit');

// Challenge routes (À nous 2)
Route::post('/challenge/create', [App\Http\Controllers\ChallengeController::class, 'createChallenge'])->name('challenge_create');
Route::get('/challenge/system-questions', [App\Http\Controllers\ChallengeController::class, 'getSystemQuestions'])->name('challenge_system_questions');
Route::get('/challenge/result/{resultId}', [App\Http\Controllers\ChallengeController::class, 'getChallengeResult'])->name('challenge_result');
Route::get('/challenge/{link}', [App\Http\Controllers\ChallengeController::class, 'getChallenge'])->name('challenge_get');
Route::post('/challenge/{link}/join', [App\Http\Controllers\ChallengeController::class, 'joinChallenge'])->name('challenge_join');
Route::post('/challenge/{link}/select-questions', [App\Http\Controllers\ChallengeController::class, 'selectQuestions'])->name('challenge_select_questions');
Route::post('/challenge/{link}/questions', [App\Http\Controllers\ChallengeController::class, 'getQuestionsToAnswer'])->name('challenge_get_questions');
Route::post('/challenge/{link}/submit-answers', [App\Http\Controllers\ChallengeController::class, 'submitAnswers'])->name('challenge_submit_answers');

// MyMind routes
Route::post('/mymind/create',            [App\Http\Controllers\MyMindController::class, 'createGame'])->name('mymind_create');
Route::get('/mymind/{link}',             [App\Http\Controllers\MyMindController::class, 'getGame'])->name('mymind_get');
Route::post('/mymind/{link}/challenge/join', [App\Http\Controllers\MyMindController::class, 'joinChallenge'])->name('mymind_challenge_join');
Route::post('/mymind/{link}/challenge/creator-withdraw', [App\Http\Controllers\MyMindController::class, 'withdrawChallengePot'])->name('mymind_challenge_creator_withdraw');
Route::post('/mymind/{link}/submit',     [App\Http\Controllers\MyMindController::class, 'submitAnswers'])->name('mymind_submit');
Route::post('/mymind/withdraw-prize',    [App\Http\Controllers\MyMindController::class, 'withdrawPrize'])->name('mymind_withdraw_prize');

// ============================================
// Routes utilisateur - Cadeaux
// ============================================
Route::get('/user/gifts/sent', [App\Http\Controllers\UserGiftsController::class, 'getSentGifts'])->name('user_gifts_sent');
Route::get('/user/gifts/received', [App\Http\Controllers\UserGiftsController::class, 'getReceivedGifts'])->name('user_gifts_received');
Route::get('/user/gifts/{ref}', [App\Http\Controllers\UserGiftsController::class, 'getGiftDetails'])->name('user_gift_details');
Route::post('/user/gifts/{ref}/cancel', [App\Http\Controllers\UserGiftsController::class, 'cancelGift'])->name('user_gift_cancel');

// ============================================
// Routes utilisateur - Jeux (Quiz, Moments, Challenges)
// ============================================
Route::get('/user/games/created', [App\Http\Controllers\UserGamesController::class, 'getCreatedGames'])->name('user_games_created');
Route::get('/user/games/participated', [App\Http\Controllers\UserGamesController::class, 'getParticipatedGames'])->name('user_games_participated');
Route::get('/user/games/{type}/{id}/participants', [App\Http\Controllers\UserGamesController::class, 'getGameParticipants'])->name('user_game_participants');
Route::post('/user/games/{type}/{id}/cancel', [App\Http\Controllers\UserGamesController::class, 'cancelGame'])->name('user_game_cancel');
Route::post('/user/games/{type}/{id}/withdraw', [App\Http\Controllers\UserGamesController::class, 'markWithdrawn'])->name('user_game_withdraw');

// ============================================
// API - Recherche par code d'accès
// ============================================
Route::post('/access-code/search', [App\Http\Controllers\AccessCodeController::class, 'searchByCode'])->name('access_code_search');

// ============================================
// Routes Admin (protégées par admin middleware)
// ============================================
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [App\Http\Controllers\AdminController::class, 'dashboard'])->name('admin_dashboard');

    // Utilisateurs
    Route::get('/users', [App\Http\Controllers\AdminController::class, 'users'])->name('admin_users');

    // Cadeaux
    Route::get('/gifts', [App\Http\Controllers\AdminController::class, 'gifts'])->name('admin_gifts');

    // Quiz
    Route::get('/quizzes', [App\Http\Controllers\AdminController::class, 'quizzes'])->name('admin_quizzes');

    // Moments
    Route::get('/moments', [App\Http\Controllers\AdminController::class, 'moments'])->name('admin_moments');

    // Challenges
    Route::get('/challenges', [App\Http\Controllers\AdminController::class, 'challenges'])->name('admin_challenges');

    // Codes promo (admin)
    Route::get('/promo-codes', [App\Http\Controllers\AdminController::class, 'promoCodes'])->name('admin_promo_codes');
    Route::post('/promo-codes/create', [App\Http\Controllers\AdminController::class, 'createPromoCode'])->name('admin_promo_codes_create');

    // Paramètres admin
    Route::get('/settings', [App\Http\Controllers\AdminController::class, 'getSettings'])->name('admin_settings_get');
    Route::put('/settings', [App\Http\Controllers\AdminController::class, 'updateSettings'])->name('admin_settings_update');

    // Gains de parrainage
    Route::get('/referral-earnings', [App\Http\Controllers\AdminController::class, 'referralEarnings'])->name('admin_referral_earnings');

    // Notifications push (admin)
    Route::post('/notifications/send', [App\Http\Controllers\AdminNotificationController::class, 'send'])->name('admin_notifications_send');
    Route::get('/notifications/history', [App\Http\Controllers\AdminNotificationController::class, 'history'])->name('admin_notifications_history');
});

