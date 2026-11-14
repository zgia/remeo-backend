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
    Route::post('/dologin', [SigninController::class, 'dologin'])->middleware('throttle:5,1');
    Route::get('/sendsmscode', [SigninController::class, 'sendSmsCode'])->middleware('throttle:3,1');
    Route::post('/resetpassword', [SigninController::class, 'resetPassword'])->middleware('throttle:3,1');
});

// 新增业务接口放入此组，统一校验登录态及密码变更后的会话有效性。
Route::middleware(['auth', 'session.password'])->group(function (): void {
    Route::get('/category', [IndexController::class, 'category']);
    Route::get('/globaloptions', [IndexController::class, 'globalOptions']);
    Route::get('/operator', [IndexController::class, 'operator']);
    Route::post('/upload', [InhospitalController::class, 'uploadMedia']);

    Route::prefix('signin')->group(function (): void {
        Route::get('/islogin', [SigninController::class, 'islogin']);
        Route::post('/changepassword', [SigninController::class, 'changePassword']);
    });

    Route::middleware('can:manage-administration')->group(function (): void {
        Route::prefix('hospital')->group(function (): void {
            Route::get('/all', [HospitalController::class, 'all']);
            Route::get('/items', [HospitalController::class, 'index']);
            Route::post('/update', [HospitalController::class, 'updateHospital']);
            Route::delete('/delete', [HospitalController::class, 'deleteHospital']);
        });

        Route::prefix('doctor')->group(function (): void {
            Route::get('/items', [DoctorController::class, 'index']);
            Route::post('/update', [DoctorController::class, 'updateDoctor']);
            Route::post('/resetpassword', [DoctorController::class, 'resetPassword']);
            Route::delete('/delete', [DoctorController::class, 'deleteDoctor']);
        });
    });

    Route::prefix('outpatient')->group(function (): void {
        Route::get('/items', [OutpatientController::class, 'index']);
        Route::get('/download', [OutpatientController::class, 'download']);
        Route::post('/updatepatient', [OutpatientController::class, 'updatePatient']);
        Route::get('/getpatient', [OutpatientController::class, 'getPatientData']);
        Route::delete('/delete', [OutpatientController::class, 'deletePatient']);
        Route::post('/updatesituation', [OutpatientController::class, 'updateSituation']);
        Route::get('/getsituation', [OutpatientController::class, 'getSituationData']);
        Route::post('/updatevideo', [OutpatientController::class, 'updateVideo']);
        Route::get('/getvideo', [OutpatientController::class, 'getVideo']);
        Route::post('/updatecheck', [OutpatientController::class, 'updateCheck']);
        Route::get('/getcheck', [OutpatientController::class, 'getCheck']);
        Route::get('/getcheckcount', [OutpatientController::class, 'getCheckCount']);
        Route::post('/hospitalized', [OutpatientController::class, 'hospitalized']);
        Route::get('/ishosp', [OutpatientController::class, 'isHospitalized']);
        Route::get('/homepage', [OutpatientController::class, 'getHomepage']);
        Route::get('/checkpage', [OutpatientController::class, 'getCheckpage']);
        Route::get('/checkdaylist', [OutpatientController::class, 'getCheckDayList']);
    });

    Route::prefix('inhospital')->group(function (): void {
        Route::get('/items', [InhospitalController::class, 'index']);
        Route::get('/download', [InhospitalController::class, 'download']);
        Route::post('/updatepatient', [InhospitalController::class, 'updatePatient']);
        Route::post('/inhospagain', [InhospitalController::class, 'inhospAgain']);
        Route::get('/getpatient', [InhospitalController::class, 'getPatientData']);
        Route::delete('/delete', [InhospitalController::class, 'deletePatient']);
        Route::post('/updatecode', [InhospitalController::class, 'updateCode']);
        Route::post('/updateinitialstate', [InhospitalController::class, 'updateInitialState']);
        Route::get('/getinitialstate', [InhospitalController::class, 'getInitialStateData']);
        Route::post('/updatesituation', [InhospitalController::class, 'updateSituation']);
        Route::get('/getsituation', [InhospitalController::class, 'getSituationData']);
        Route::post('/updatevideo', [InhospitalController::class, 'updateVideo']);
        Route::get('/getvideo', [InhospitalController::class, 'getVideo']);
        Route::post('/updatesurgery', [InhospitalController::class, 'updateSurgery']);
        Route::get('/getsurgery', [InhospitalController::class, 'getSurgeryData']);
        Route::post('/updatepathology', [InhospitalController::class, 'updatePathology']);
        Route::get('/getpathology', [InhospitalController::class, 'getPathologyData']);
        Route::post('/updatefollowup', [InhospitalController::class, 'updateFollowup']);
        Route::get('/getfollowup', [InhospitalController::class, 'getFollowup']);
        Route::get('/getfollowupcount', [InhospitalController::class, 'getFollowupCount']);
        Route::get('/homepage', [InhospitalController::class, 'getHomepage']);
        Route::get('/homepagedaylist', [InhospitalController::class, 'getHomepageDayList']);
        Route::get('/followuppage', [InhospitalController::class, 'getFollowuppage']);
        Route::get('/followupdaylist', [InhospitalController::class, 'getFollowupDayList']);
        Route::get('/getmedia', [InhospitalController::class, 'getMedia']);
        Route::delete('/deletemedia', [InhospitalController::class, 'deleteMedia']);
    });
});
