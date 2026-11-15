<?php

use App\Controller\DoctorController;
use App\Controller\HospitalController;
use App\Controller\IndexController;
use App\Controller\InhospitalController;
use App\Controller\OutpatientController;
use App\Controller\SigninController;
use Illuminate\Support\Facades\Route;

// 公开入口仅限首页和会话建立、登录、找回密码；退出允许失效会话重复调用。
Route::get('/', [IndexController::class, 'index']);
Route::get('/index', [IndexController::class, 'index']);

Route::prefix('signin')->group(function (): void {
    Route::get('/captcha', [SigninController::class, 'captcha'])->middleware('throttle:30,1,login-captcha');
    Route::get('/csrf', fn () => response()->json(['code' => 0, 'msg' => '会话已初始化', 'data' => (object) []]));
    Route::post('/logout', [SigninController::class, 'logout']);
    Route::post('/login', [SigninController::class, 'dologin'])->middleware('throttle:5,1');
    Route::get('/send-sms-code', [SigninController::class, 'sendSmsCode'])->middleware('throttle:3,1');
    Route::post('/reset-password', [SigninController::class, 'resetPassword'])->middleware('throttle:3,1');
});

// 新增业务接口放入此组，统一校验登录态及密码变更后的会话有效性。
Route::middleware(['auth', 'session.password'])->group(function (): void {
    Route::get('/category', [IndexController::class, 'category']);
    Route::get('/global-options', [IndexController::class, 'globalOptions']);
    Route::get('/operator', [IndexController::class, 'operator']);
    Route::post('/upload', [InhospitalController::class, 'uploadMedia']);

    Route::prefix('signin')->group(function (): void {
        Route::get('/is-login', [SigninController::class, 'islogin']);
        Route::post('/change-password', [SigninController::class, 'changePassword']);
    });

    Route::middleware('can:manage-administration')->group(function (): void {
        Route::prefix('hospital')->group(function (): void {
            Route::get('/all', [HospitalController::class, 'all']);
            Route::get('/items', [HospitalController::class, 'index']);
            Route::post('/', [HospitalController::class, 'updateHospital']);
            Route::delete('/', [HospitalController::class, 'deleteHospital']);
        });

        Route::prefix('doctor')->group(function (): void {
            Route::get('/items', [DoctorController::class, 'index']);
            Route::post('/', [DoctorController::class, 'updateDoctor']);
            Route::post('/reset-password', [DoctorController::class, 'resetPassword']);
            Route::delete('/', [DoctorController::class, 'deleteDoctor']);
        });
    });

    Route::prefix('outpatient')->group(function (): void {
        Route::get('/items', [OutpatientController::class, 'index']);
        Route::get('/download', [OutpatientController::class, 'download']);
        Route::post('/patient', [OutpatientController::class, 'updatePatient']);
        Route::get('/patient', [OutpatientController::class, 'getPatientData']);
        Route::delete('/patient', [OutpatientController::class, 'deletePatient']);
        Route::post('/situation', [OutpatientController::class, 'updateSituation']);
        Route::get('/situation', [OutpatientController::class, 'getSituationData']);
        Route::post('/video', [OutpatientController::class, 'updateVideo']);
        Route::get('/video', [OutpatientController::class, 'getVideo']);
        Route::post('/check', [OutpatientController::class, 'updateCheck']);
        Route::get('/check', [OutpatientController::class, 'getCheck']);
        Route::get('/check-count', [OutpatientController::class, 'getCheckCount']);
        Route::post('/hospitalized', [OutpatientController::class, 'hospitalized']);
        Route::get('/is-hosp', [OutpatientController::class, 'isHospitalized']);
        Route::get('/homepage', [OutpatientController::class, 'getHomepage']);
        Route::get('/check-page', [OutpatientController::class, 'getCheckpage']);
        Route::get('/check-day-list', [OutpatientController::class, 'getCheckDayList']);
    });

    Route::prefix('inhospital')->group(function (): void {
        Route::get('/items', [InhospitalController::class, 'index']);
        Route::get('/download', [InhospitalController::class, 'download']);
        Route::post('/patient', [InhospitalController::class, 'updatePatient']);
        Route::post('/inhosp-again', [InhospitalController::class, 'inhospAgain']);
        Route::get('/patient', [InhospitalController::class, 'getPatientData']);
        Route::delete('/patient', [InhospitalController::class, 'deletePatient']);
        Route::post('/hosp-info', [InhospitalController::class, 'updateCode']);
        Route::post('/initial-state', [InhospitalController::class, 'updateInitialState']);
        Route::get('/initial-state', [InhospitalController::class, 'getInitialStateData']);
        Route::post('/situation', [InhospitalController::class, 'updateSituation']);
        Route::get('/situation', [InhospitalController::class, 'getSituationData']);
        Route::post('/video', [InhospitalController::class, 'updateVideo']);
        Route::get('/video', [InhospitalController::class, 'getVideo']);
        Route::post('/surgery', [InhospitalController::class, 'updateSurgery']);
        Route::get('/surgery', [InhospitalController::class, 'getSurgeryData']);
        Route::post('/pathology', [InhospitalController::class, 'updatePathology']);
        Route::get('/pathology', [InhospitalController::class, 'getPathologyData']);
        Route::post('/followup', [InhospitalController::class, 'updateFollowup']);
        Route::get('/followup', [InhospitalController::class, 'getFollowup']);
        Route::get('/followup-count', [InhospitalController::class, 'getFollowupCount']);
        Route::get('/homepage', [InhospitalController::class, 'getHomepage']);
        Route::get('/homepage-day-list', [InhospitalController::class, 'getHomepageDayList']);
        Route::get('/followup-page', [InhospitalController::class, 'getFollowuppage']);
        Route::get('/followup-day-list', [InhospitalController::class, 'getFollowupDayList']);
        Route::get('/media', [InhospitalController::class, 'getMedia']);
        Route::delete('/media', [InhospitalController::class, 'deleteMedia']);
    });
});
