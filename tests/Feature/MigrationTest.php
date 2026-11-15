<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituationType;
use App\Helper\AliyunSmsHelper;
use App\Models\User;
use App\Repositories\LogRepository;
use App\Repositories\UserRepository;
use App\Service\ExportInhospitalService;
use App\Service\ExportOutpatientService;
use App\Service\HospitalService;
use App\Service\OutpatientService;
use App\Service\SystemService;
use App\Service\UserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** 覆盖框架替换后最易回归的认证、权限和查询契约。 */
class MigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('hospital')->insert([
            ['id' => 1, 'title' => '测试医院甲'], ['id' => 2, 'title' => '测试医院乙'],
        ]);
        DB::table('user')->insert([
            'id'       => 1, 'hospitalid' => 1, 'realname' => '测试医生', 'mobile' => '13800000001',
            'password' => Hash::make('test-password'), 'salt' => '',
        ]);
    }

    /** @param array<mixed> $rows @return void */
    private function insertPatients(array $rows): void
    {
        if (isset($rows['id'])) {
            $rows = [$rows];
        }
        foreach ($rows as $row) {
            DB::table('patient')->insert($row + array_fill_keys(['linkman', 'linkrel', 'linkman2', 'linkrel2', 'linkman3', 'linkrel3'], ''));
        }
    }

    /** @param int $id @return void */
    private function signIn(int $id = 1): void
    {
        $user = User::findOrFail($id);
        $this->actingAs($user)->withSession(['auth_password' => $user->password]);
    }

    public function test_guest_cannot_access_patient_or_management_routes(): void
    {
        foreach (['/outpatient/items', '/inhospital/getpatient?patientid=1', '/hospital/items', '/doctor/items', '/signin/changepassword'] as $uri) {
            ($uri === '/signin/changepassword' ? $this->postJson($uri) : $this->getJson($uri))->assertStatus(401)->assertJsonPath('code', 1);
        }
        $this->getJson('/')->assertOk()->assertJsonPath('code', 0);
        $this->get('/signin/islogin')->assertStatus(401);
    }

    public function test_legacy_password_is_upgraded_and_logout_ends_session(): void
    {
        DB::table('user')->where('id', 1)->update(['password' => md5(md5('old-password').'abcdef'), 'salt' => 'abcdef']);
        $this->postJson('/signin/dologin', ['mobile' => '13800000001', 'password' => 'old-password', 'captcha' => $this->issueLoginCaptcha()])
            ->assertOk()->assertJsonPath('code', 0)->assertJsonMissingPath('data.token')->assertJsonMissingPath('data.user.password');
        $this->assertTrue(Hash::check('old-password', User::find(1)->password));
        $this->getJson('/signin/islogin')->assertOk()->assertJsonPath('data.user.name', '测试医生');
        $this->postJson('/signin/logout')->assertOk();
        $this->getJson('/signin/islogin')->assertStatus(401);
    }

    public function test_deleted_account_and_wrong_password_cannot_login(): void
    {
        $this->postJson('/signin/dologin', ['mobile' => '13800000001', 'password' => 'wrong', 'captcha' => $this->issueLoginCaptcha()])->assertStatus(401)->assertJsonMissingPath('data.password');
        DB::table('user')->where('id', 1)->update(['deleted_at' => now()]);
        $this->postJson('/signin/dologin', ['mobile' => '13800000001', 'password' => 'test-password', 'captcha' => $this->issueLoginCaptcha()])->assertStatus(401);
    }

    public function test_password_change_uses_laravel_validation_and_invalidates_old_sessions(): void
    {
        $this->signIn();
        $this->postJson('/signin/changepassword', ['password' => 'wrong', 'password1' => 'new-password', 'password2' => 'new-password'])->assertStatus(422);
        $this->postJson('/signin/changepassword', ['password' => 'test-password', 'password1' => 'new-password', 'password2' => 'new-password'])->assertOk();
        $this->assertTrue(Hash::check('new-password', User::find(1)->password));
        $this->getJson('/signin/islogin')->assertOk();
        DB::table('user')->where('id', 1)->update(['password' => Hash::make('reset-password')]);
        Auth::forgetGuards();
        $this->getJson('/signin/islogin')->assertStatus(401);
    }

    public function test_csrf_is_required_and_issued_for_the_spa(): void
    {
        // Laravel 默认跳过测试环境 CSRF，这里切换环境以检查实际中间件。
        $this->app['env'] = 'local';
        $this->getJson('/signin/csrf')->assertOk()->assertCookie('XSRF-TOKEN');
        $this->postJson('/signin/dologin', ['mobile' => '13800000001', 'password' => 'test-password'])->assertStatus(419);
        $captcha = $this->issueLoginCaptcha();
        $this->withSession(['_token' => 'csrf-test'])->postJson('/signin/dologin', [
            '_token' => 'csrf-test', 'mobile' => '13800000001', 'password' => 'test-password', 'captcha' => $captcha,
        ])->assertOk();
    }

    public function test_native_queries_preserve_pagination_counts_joins_and_rollback(): void
    {
        $this->signIn();
        config(['remeo.options.perpage.value' => 1]);
        $this->insertPatients([
            ['id' => 11, 'realname' => '患者甲', 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1],
            ['id' => 22, 'realname' => '患者乙', 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1],
            ['id' => 33, 'realname' => '患者丙', 'hospitalid' => 2, 'doctorid' => 1, 'isout' => 1],
            ['id' => 44, 'realname' => '患者丁', 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1, 'deleted_at' => '2026-01-01 12:12:12'],
        ]);
        DB::table('progress')->insert(['patientid' => 22, 'firstsituation' => 1]);
        $this->getJson('/outpatient/items?realname='.urlencode('患者'))
            ->assertOk()->assertJsonPath('data.total', 2)->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.patientid', 22)
            ->assertJsonPath('data.items.0.hospital', '测试医院甲')
            ->assertJsonPath('data.items.0.recorder', '测试医生')
            ->assertJsonPath('data.items.0.progress.firstsituation', true);
        $this->getJson('/outpatient/items?p=2')->assertJsonPath('data.items.0.patientid', 11);
        $this->getJson('/outpatient/items?sort=id&order=ascending')->assertJsonPath('data.items.0.patientid', 11);
        $this->getJson('/outpatient/items?realname=missing')->assertJsonPath('data.total', 0)->assertJsonPath('data.items', []);
        $hospitals = app(HospitalService::class)->items('测试医院甲');
        $this->assertSame(1, $hospitals['total']);
        $this->assertEquals(2, $hospitals['items'][0]['patients']);
        $this->assertEquals(1, $hospitals['items'][0]['doctors']);
        DB::beginTransaction();
        app(OutpatientService::class)->addCheckCount(11);
        $this->assertDatabaseHas('patient', ['id' => 11, 'checkcount' => 1]);
        DB::rollBack();
        $this->assertDatabaseHas('patient', ['id' => 11, 'checkcount' => 0]);
    }

    public function test_repository_business_queries_and_json_storage_preserve_contracts(): void
    {
        $this->signIn();
        $users = app(UserRepository::class);
        $this->assertSame([], $users->getUsersByUserids([]));
        $this->assertSame([1], array_keys($users->getUsersByUserids([1, 999])));
        $this->assertSame([1], array_keys($users->getUsersByUserids(1)));
        $this->assertNull($users->getUserByMobile('missing'));
        DB::table('user')->insert(['id' => 2, 'mobile' => '13800000999', 'deleted_at' => '2026-01-01 12:12:12']);
        $this->assertNull($users->getUserByMobile('13800000999'));

        $logs = app(LogRepository::class);
        $id   = $logs->record(['type' => 'user', 'id' => 1, 'to' => ['realname' => '中文医生', 'password' => 'secret', 'salt' => 'secret']]);
        $this->assertSame(['realname' => '中文医生'], $logs->getLogDetail($id)['tocontent']);
        $this->assertSame([$id], array_keys($logs->getLogs(['objectid' => 1])));
        $this->assertSame([], $logs->getLogDetail(999));

        $this->mock(AliyunSmsHelper::class, function ($mock): void {
            $mock->shouldReceive('sendSMS')->once()->with('13800000001', '123456', 'code');
        });
        app(UserService::class)->sendSMSToUser(['id' => 1, 'mobile' => '13800000001'], '123456', 'code');
        $this->assertSame(['type' => 'code'], json_decode(DB::table('smslog')->value('msg'), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_service_transactions_do_not_commit_the_callers_transaction(): void
    {
        $this->signIn();
        $this->insertPatients(['id' => 72, 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1]);
        $initialLevel = DB::transactionLevel();
        DB::beginTransaction();
        try {
            $this->postJson('/outpatient/updatesituation', ['patientid' => 72, 'kps' => 100, 'cc' => '嵌套事务测试'])
                ->assertOk()->assertJsonPath('code', 0);
            $this->assertSame($initialLevel + 1, DB::transactionLevel());
            $this->assertDatabaseHas('situation', ['patientid' => 72, 'cc' => '嵌套事务测试']);
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseCount('treatment', 0);
        $this->assertDatabaseCount('situation', 0);
        $this->assertDatabaseCount('log', 0);
    }

    public function test_failed_audit_write_rolls_back_the_entire_check(): void
    {
        $this->signIn();
        $initialLevel = DB::transactionLevel();
        $this->insertPatients(['id' => 71, 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1]);
        $this->mock(LogRepository::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('模拟日志写入失败'));
        });
        $this->postJson('/outpatient/updatecheck', [
            'patientid' => 71, 'treatment' => ['checkday' => '2026-12-14', 'checkalive' => 1],
            'situation' => ['cc' => '回滚测试'], 'video' => ['videoday' => '2026-12-14'],
        ])->assertStatus(500)->assertJsonPath('msg', '请求处理失败，请稍后重试。');
        $this->assertDatabaseCount('treatment', 0);
        $this->assertDatabaseCount('situation', 0);
        $this->assertDatabaseCount('video', 0);
        $this->assertDatabaseHas('patient', ['id' => 71, 'checkcount' => 0, 'lastcheckid' => 0]);
        $this->assertSame($initialLevel, DB::transactionLevel());
    }

    public function test_patient_lists_validate_sort_and_pagination_before_queries(): void
    {
        $this->signIn();
        foreach (['outpatient', 'inhospital'] as $module) {
            foreach ([['sort' => 'missing_column'], ['order' => 'invalid'], ['p' => 0], ['sort' => ['id']]] as $parameters) {
                $this->getJson('/'.$module.'/items?'.http_build_query($parameters))
                    ->assertStatus(422)->assertJsonValidationErrors(array_key_first($parameters), 'data.errors');
            }
            $this->getJson('/'.$module.'/items?sort=id&order=descending')->assertOk()->assertJsonPath('data.items', []);
        }
    }

    public function test_business_translations_use_laravel_language_files(): void
    {
        app()->setLocale('zh_CN');
        $this->assertSame('该用户不存在。', __('The user is not existed.'));
        app()->setLocale('en');
        $this->assertSame('The user is not existed.', __('The user is not existed.'));
    }

    public function test_hospital_scope_soft_delete_and_patient_creation(): void
    {
        config(['remeo.super_users' => []]);
        $this->signIn();
        $this->insertPatients([
            ['id' => 1, 'realname' => '本院患者', 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1],
            ['id' => 2, 'realname' => '他院患者', 'hospitalid' => 2, 'doctorid' => 1, 'isout' => 1],
            ['id' => 3, 'realname' => '已删除', 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1, 'deleted_at' => '2026-01-01 12:12:12'],
        ]);
        $this->getJson('/outpatient/items?hospitalid=-1')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.realname', '本院患者');
        $this->getJson('/outpatient/getpatient?patientid=2')->assertStatus(404);
        $this->getJson('/doctor/items')->assertStatus(403);
        $this->postJson('/outpatient/updatepatient', ['realname' => '新增测试患者', 'gender' => 1, 'age' => 40, 'mobile' => '13900000000', 'firstday' => '2026-11-14'])->assertOk()->assertJsonPath('code', 0);
        $this->assertDatabaseHas('patient', ['realname' => '新增测试患者', 'hospitalid' => 1]);
    }

    public function test_exports_handle_missing_optional_records_and_null_dates(): void
    {
        $this->signIn();
        $this->insertPatients(['id' => 1, 'realname' => '导出测试患者', 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1, 'isin' => 0, 'hospseq' => 1]);
        foreach ([ExportOutpatientService::class, ExportInhospitalService::class] as $class) {
            $service = app($class);
            $file    = $service->saveToExcel();
            try {
                $sheet = IOFactory::load($file)->getActiveSheet();
                $this->assertStringContainsString('导出测试患者', json_encode($sheet->toArray(), JSON_UNESCAPED_UNICODE));
            } finally {
                @unlink($file);
            }
            $service->getPatientById(1);
            $word = $service->saveToWord();
            $this->assertGreaterThan(100, filesize($word));
            @unlink($word);
            DB::table('patient')->where('id', 1)->update(['isin' => 1]);
        }
        $this->get('/outpatient/download')->assertOk()->assertHeader('content-disposition');
    }

    public function test_female_situation_formats_optional_fields_without_inventing_values(): void
    {
        $service = app(ExportInhospitalService::class);
        foreach ([[], ['uterectomy' => null, 'uterinefibroids' => null, 'menopause' => null], ['uterectomy' => 0, 'uterinefibroids' => 0, 'menopause' => 0]] as $fields) {
            $situation = ['isfemale' => true] + $fields;
            $service->situationForPage($situation, []);
            foreach (['uterectomy', 'uterinefibroids', 'menopause'] as $field) {
                $this->assertSame('', $situation[$field]);
            }
        }

        $situation = ['isfemale' => true, 'uterectomy' => 1, 'uterinefibroids' => '2', 'menopause' => 2];
        $service->situationForPage($situation, []);
        $this->assertSame('是', $situation['uterectomy']);
        $this->assertSame('否', $situation['uterinefibroids']);
        $this->assertSame('否', $situation['menopause']);

        $situation = ['isfemale' => false];
        $service->situationForPage($situation, []);
        foreach (['uterectomy', 'fertility', 'uterinefibroids', 'menopause'] as $field) {
            $this->assertSame('-', $situation[$field]);
        }
    }

    public function test_female_patient_downloads_include_situation_values(): void
    {
        $this->signIn();
        $this->insertPatients(['id' => 1, 'realname' => '女性导出测试患者', 'gender' => 2, 'hospitalid' => 1, 'doctorid' => 1, 'isout' => 1, 'isin' => 1, 'hospseq' => 1, 'surgeryseq' => 1]);
        foreach ([SituationType::IhInhospital, SituationType::OpFisrt] as $type) {
            DB::table('situation')->insert([
                'patientid'       => 1, 'hospseq' => 1, 'stype' => $type->value,
                'cc'              => '女性患者主诉', 'uterectomy' => 2, 'fertility' => '2-1',
                'uterinefibroids' => 1, 'menopause' => 2,
            ]);
        }

        foreach (['inhospital', 'outpatient'] as $module) {
            DB::table('patient')->where('id', 1)->update(['isin' => $module === 'inhospital' ? 1 : 0]);
            $response = $this->get('/'.$module.'/download')->assertOk()->assertHeader('content-disposition');
            $file     = $response->baseResponse->getFile()->getPathname();
            try {
                $sheet = IOFactory::load($file)->getActiveSheet();
                $row   = $sheet->toArray()[3];
                $this->assertContains('女性导出测试患者', $row);
                $this->assertContains('女性患者主诉', $row);
                if ($module === 'inhospital') {
                    $this->assertSame('否', $row[17]);
                    $this->assertSame('G2P1', $row[18]);
                } else {
                    $this->assertSame('是', $sheet->getCell('M4')->getValue());
                    $this->assertSame('否', $sheet->getCell('N4')->getValue());
                }
            } finally {
                @unlink($file);
            }
        }
    }

    public function test_outpatient_full_record_workflow(): void
    {
        $this->signIn();
        $response = $this->postJson('/outpatient/updatepatient', ['realname' => '流程门诊患者', 'gender' => 1, 'age' => 40, 'mobile' => '13900000010', 'firstday' => '2026-11-14'])->assertOk()->assertJsonPath('code', 0);
        $id       = $response->json('data.patientid');
        $this->postJson('/outpatient/updatesituation', ['patientid' => $id, 'kps' => 100, 'cc' => '测试主诉'])->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/outpatient/updatevideo', ['patientid' => $id, 'vtype' => 'f', 'cemrilength' => 10, 'cemriwidth' => 8, 'cemrithick' => 6, 'videoday' => '2026-11-14'])->assertOk()->assertJsonPath('code', 0);
        $this->getJson('/outpatient/homepage?patientid='.$id)->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/outpatient/updatecheck', [
            'patientid' => $id, 'treatment' => ['checkday' => '2026-12-14', 'checkalive' => 1],
            'situation' => ['cc' => '复查主诉'], 'video' => ['videoday' => '2026-12-14'],
        ])->assertOk()->assertJsonPath('code', 0);
        $this->assertDatabaseHas('patient', ['id' => $id, 'checkcount' => 1]);
        $this->getJson('/outpatient/getcheck?patientid='.$id)->assertOk()->assertJsonPath('code', 0);
        $this->getJson('/outpatient/checkdaylist?patientid='.$id)->assertOk()->assertJsonPath('code', 0);
        $this->get('/outpatient/download?patientid='.$id)->assertOk()->assertHeader('content-type', 'application/vnd.ms-word');
        $this->deleteJson('/outpatient/delete', ['patientid' => $id])->assertOk();
        $this->getJson('/outpatient/items')->assertJsonPath('data.total', 0);
    }

    public function test_inhospital_surgery_pathology_and_followup_workflow(): void
    {
        $this->signIn();
        $response = $this->postJson('/inhospital/updatepatient', ['realname' => '流程住院患者', 'gender' => 1, 'age' => 50, 'mobile' => '13900000011', 'hospday' => '2026-11-14', 'hospcode' => 'TEST-001'])->assertOk()->assertJsonPath('code', 0);
        $id       = $response->json('data.patientid');
        $this->postJson('/inhospital/updatesituation', ['patientid' => $id, 'comefrom' => 'i', 'kps' => 100, 'cc' => '入院情况'])->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/inhospital/updatevideo', ['patientid' => $id, 'vtype' => 'b', 'videoday' => '2026-11-14'])->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/inhospital/updatesurgery', ['patientid' => $id, 'surgeryday' => '2026-11-15', 'surgeryway' => 1])->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/inhospital/updatepathology', ['patientid' => $id, 'pycode' => 'TEST-PY', 'who' => 65])->assertOk()->assertJsonPath('code', 0);
        $this->postJson('/inhospital/updatefollowup', [
            'patientid' => $id, 'treatment' => ['checkday' => '2026-12-15', 'checkalive' => 1],
            'situation' => ['cc' => '随访情况'], 'video' => ['videoday' => '2026-12-15'],
        ])->assertOk()->assertJsonPath('code', 0);
        $this->getJson('/inhospital/getfollowup?patientid='.$id)->assertOk()->assertJsonPath('code', 0);
        $this->getJson('/inhospital/items')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/inhospital/homepage?patientid='.$id)->assertOk()->assertJsonPath('code', 0);
        $this->getJson('/inhospital/homepagedaylist?patientid='.$id)->assertOk()->assertJsonPath('code', 0);
        $this->getJson('/inhospital/homepage?patientid='.$id.'&hospseq=999')->assertStatus(418)->assertJsonPath('code', 1);
        DB::table('radiotherapy')->where('patientid', $id)->delete();
        $this->getJson('/inhospital/followuppage?patientid='.$id)->assertOk()->assertJsonPath('data.radiotherapy', []);
    }

    public function test_another_patients_treatment_cannot_be_read(): void
    {
        $this->signIn();
        $this->insertPatients([['id' => 1, 'hospitalid' => 1], ['id' => 2, 'hospitalid' => 2]]);
        DB::table('treatment')->insert(['id' => 5, 'patientid' => 2]);
        $this->getJson('/outpatient/getcheck?patientid=1&treatmentid=5')->assertStatus(418)->assertJsonPath('code', 1);
    }

    public function test_form_requests_reject_invalid_fields_before_writing(): void
    {
        $this->signIn();
        $this->postJson('/outpatient/updatepatient', ['realname' => '参数测试', 'age' => 'invalid'])
            ->assertStatus(422)->assertJsonValidationErrors('age', 'data.errors');
        $this->assertDatabaseCount('patient', 0);
        $this->insertPatients(['id' => 1, 'hospitalid' => 1]);
        $this->postJson('/outpatient/updatecheck', ['patientid' => 1, 'video' => 'invalid'])
            ->assertStatus(422)->assertJsonValidationErrors('video', 'data.errors');
        $this->assertDatabaseCount('treatment', 0);
        $this->getJson('/outpatient/getpatient?patientid=invalid')->assertStatus(422);
    }

    public function test_hospital_and_doctor_management_uses_laravel_services(): void
    {
        $this->signIn();
        $hospital = $this->postJson('/hospital/update', ['title' => '新增测试医院'])->assertOk()->json('data.hospitalid');
        $this->postJson('/hospital/update', ['hospitalid' => $hospital, 'title' => '更新测试医院'])->assertOk();
        $this->assertDatabaseHas('hospital', ['id' => $hospital, 'title' => '更新测试医院']);
        $this->getJson('/hospital/items?'.http_build_query(['title' => '更新测试医院']))->assertOk()->assertJsonPath('data.total', 1);
        $doctor = $this->postJson('/doctor/update', [
            'realname'    => '新增测试医生', 'mobile' => '13800000002', 'hospitalid' => $hospital,
            'permissions' => ['download.patient' => 'true'],
        ])->assertOk()->assertJsonPath('data.permissions.0', 'download.patient')->json('data.doctorid');
        $this->assertTrue(Hash::check('0000002', User::findOrFail($doctor)->password));
        $this->postJson('/doctor/resetpassword', ['doctorid' => $doctor])->assertOk();
        $this->deleteJson('/hospital/delete', ['hospitalid' => $hospital])->assertStatus(418);
        $this->deleteJson('/doctor/delete', ['doctorid' => $doctor])->assertOk();
        $this->assertNotNull(DB::table('user')->where('id', $doctor)->whereNotNull('deleted_at')->first());
        $this->deleteJson('/hospital/delete', ['hospitalid' => $hospital])->assertStatus(418);
        $emptyHospital = $this->postJson('/hospital/update', ['title' => '待删除测试医院'])->assertOk()->json('data.hospitalid');
        $this->deleteJson('/hospital/delete', ['hospitalid' => $emptyHospital])->assertOk();
        $this->getJson('/hospital/items?'.http_build_query(['title' => '待删除测试医院']))->assertJsonPath('data.total', 0);
    }

    public function test_download_gate_denies_doctor_without_permission(): void
    {
        config(['remeo.super_users' => []]);
        $this->signIn();
        $this->getJson('/outpatient/download')->assertStatus(403);
        $this->getJson('/inhospital/download')->assertStatus(403);
    }

    public function test_media_upload_uses_validated_uploaded_file_and_storage(): void
    {
        $this->signIn();
        $this->insertPatients(['id' => 1, 'hospitalid' => 1]);
        Storage::fake('patient_media');
        $this->post('/upload', [
            'patientid' => 1, 'file' => UploadedFile::fake()->create('invalid.txt', 1, 'text/plain'),
        ])->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
        $media = $this->post('/upload', [
            'patientid' => 1, 'uid' => 'test-upload', 'file' => UploadedFile::fake()->image('影像.png', 300, 200),
        ])->assertOk()->assertJsonPath('data.uid', 'test-upload')->json('data');
        Storage::disk('patient_media')->assertExists(substr($media['fullurl'], strlen('/file/')));
        Storage::disk('patient_media')->assertExists(substr($media['url'], strlen('/file/')));
        $this->getJson('/inhospital/getmedia?patientid=1')->assertOk()->assertJsonPath('data.0.id', $media['id']);
        $this->deleteJson('/inhospital/deletemedia', ['patientid' => 1, 'fileid' => $media['id']])->assertOk();
        $this->getJson('/inhospital/getmedia?patientid=1')->assertJsonPath('data', []);
    }

    public function test_category_keys_use_ckey_with_chinese_display_titles(): void
    {
        $this->signIn();
        $groups = [
            [1, 'kps', '体能状态评分', 2, '正常', 100],
            [13, 'videostatus', '影像学检查', 14, '本院MR', 14],
            [19, 'lesionposition', '病灶位置', 20, '左', 20],
            [24, 'lesionpart', '病灶部位', 25, '额', 25],
            [38, 'checkway', '复查方式', 39, '来院', 39],
            [42, 'videoresult', '影像评估结果', 43, '未见复发', 43],
            [48, 'histopathology', '组织病理', 49, '纤维型', 49],
            [64, 'who', 'WHO分级', 65, 'WHO I级', 65],
            [76, 'tumorbloodsupply', '肿瘤血供', 77, '丰富', 77],
            [79, 'tumortexture', '肿瘤质地', 80, '软', 80],
            [83, 'tumoradhesionlevel', '粘连程度', 84, '无黏连', 84],
            [100, 'customcategory', '自定义分类', 101, '自定义选项', 101],
        ];
        foreach ($groups as [$parentId, $key, $title, $childId, $childTitle]) {
            DB::table('category')->insert([
                ['id' => $parentId, 'parentid' => 0, 'ckey' => $key, 'title' => $title],
                ['id' => $childId, 'parentid' => $parentId, 'ckey' => '', 'title' => $childTitle],
            ]);
        }

        $options    = $this->getJson('/globaloptions')->assertOk()->assertJsonPath('code', 0);
        $categories = $this->getJson('/category')->assertOk()->assertJsonPath('code', 0);
        $labels     = app(SystemService::class)->categoriesKV();
        foreach ($groups as [$parentId, $key, $title, $childId, $childTitle, $value]) {
            $expected = [['value' => $value, 'title' => $childTitle]];
            $options->assertJsonPath('data.categories.'.$key, $expected)
                ->assertJsonMissingPath('data.categories.'.$title);
            $categories->assertJsonPath('data.'.$key, $expected);
            $this->assertSame($childTitle, $labels[$key][$value]);
        }
    }

    public function test_global_options_use_repositories_for_hospital_counts_and_categories(): void
    {
        $this->signIn();
        $this->insertPatients([
            ['id' => 1, 'hospitalid' => 1, 'isout' => 1],
            ['id' => 2, 'hospitalid' => 1, 'isout' => 0],
            ['id' => 3, 'hospitalid' => 2, 'isout' => 1],
            ['id' => 4, 'hospitalid' => 1, 'isout' => 1, 'deleted_at' => '2026-01-01 12:12:12'],
        ]);
        DB::table('category')->insert([
            ['id' => 100, 'parentid' => 0, 'title' => 'testcategory'],
            ['id' => 101, 'parentid' => 100, 'title' => '测试字典项'],
        ]);
        $this->getJson('/globaloptions')->assertOk()
            ->assertJsonPath('data.opTotal', 1)->assertJsonPath('data.ihTotal', 1)
            ->assertJsonPath('data.categories.testcategory.0.title', '测试字典项');
        $this->getJson('/category')->assertOk()->assertJsonPath('data.testcategory.0.value', 101);
        $this->getJson('/signin/islogin')->assertOk()->assertJsonPath('data.user.hospital', '测试医院甲');
    }
}
