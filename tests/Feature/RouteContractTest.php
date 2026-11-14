<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituationType;
use App\Enums\SurgeryType;
use App\Enums\VideoType;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * 每个路由独立执行成功用例，避免仅命中认证拦截就被算作业务覆盖。
 */
class RouteContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['remeo.super_users' => [1], 'remeo.sms_url' => 'https://sms.example.test/send']);
        Http::preventStrayRequests();
        Http::fake(['https://sms.example.test/send' => Http::response(['success' => true])]);
        Storage::fake('local');
        Storage::fake('patient_media');

        DB::table('hospital')->insert([
            ['id' => 1, 'title' => '路由测试医院'], ['id' => 2, 'title' => '待删除空医院'],
        ]);
        foreach ([1, 2] as $id) {
            DB::table('user')->insert([
                'id'     => $id, 'hospitalid' => 1, 'realname' => '路由测试医生'.$id,
                'mobile' => '1380000000'.$id, 'password' => Hash::make('route-test-password'), 'salt' => '',
            ]);
        }
        DB::table('category')->insert([
            ['id' => 10, 'parentid' => 0, 'title' => 'route_category'],
            ['id' => 11, 'parentid' => 10, 'title' => '路由测试字典项'],
        ]);
        $this->seedPatient(101, false);
        $this->seedPatient(102, true);
        DB::table('media')->insert(['id' => 1, 'patientid' => 102, 'file' => '测试影像.png', 'thumb' => '测试缩略图.png']);
    }

    private function seedPatient(int $id, bool $inpatient): void
    {
        $hospseq = $inpatient ? 1 : 0;
        DB::table('patient')->insert([
            'id'          => $id, 'hospitalid' => 1, 'doctorid' => 1,
            'realname'    => $inpatient ? '路由住院患者' : '路由门诊患者', 'gender' => 1, 'age' => 45,
            'isout'       => $inpatient ? 0 : 1, 'isin' => $inpatient ? 1 : 0, 'hospseq' => $hospseq,
            'hospday'     => $inpatient ? '2026-11-14' : null, 'firstday' => '2026-11-14',
            'hospcode'    => $inpatient ? 'ROUTE-102' : '', 'hospinitialstate' => 1,
            'checkcount'  => $inpatient ? 0 : 1, 'followupcount' => $inpatient ? 1 : 0,
            'lastcheckid' => $inpatient ? 0 : $id, 'lastfollowupid' => $inpatient ? $id : 0,
        ] + array_fill_keys(['linkman', 'linkrel', 'linkman2', 'linkrel2', 'linkman3', 'linkrel3'], ''));
        DB::table('progress')->insert(['patientid' => $id, 'hospsituation' => 1, 'surgery' => 1]);
        if (! $inpatient) {
            // 门诊首诊占 step=1，复查次数不包含这条首诊记录。
            DB::table('treatment')->insert(['id' => 1, 'patientid' => $id, 'outin' => 'o', 'step' => 1, 'checkday' => '2026-11-14']);
        }
        DB::table('treatment')->insert([
            'id'      => $id, 'patientid' => $id, 'outin' => $inpatient ? 'i' : 'o',
            'hospseq' => $hospseq, 'step' => 2, 'checkday' => '2026-12-14', 'checkalive' => 1,
        ]);
        foreach ([0, $id] as $treatmentid) {
            $situationType = $inpatient
                ? ($treatmentid ? SituationType::IhFollowup : SituationType::IhInhospital)
                : ($treatmentid ? SituationType::OpCheck : SituationType::OpFisrt);
            $videoType = $inpatient
                ? ($treatmentid ? VideoType::IhFollowup : VideoType::IhBefore)
                : ($treatmentid ? VideoType::OpCheck : VideoType::OpFisrt);
            DB::table('situation')->insert([
                'patientid' => $id, 'treatmentid' => $treatmentid, 'hospseq' => $hospseq,
                'stype'     => $situationType->value, 'cc' => '路由测试主诉', 'kps' => 100,
            ]);
            DB::table('video')->insert([
                'patientid' => $id, 'treatmentid' => $treatmentid, 'hospseq' => $hospseq,
                'vtype'     => $videoType->value, 'videoday' => '2026-11-14', 'cemrilength' => 10,
            ]);
        }
        if ($inpatient) {
            DB::table('surgery')->insert([
                'patientid'  => $id, 'hospseq' => 1, 'sytype' => SurgeryType::Surgery->value,
                'surgeryday' => '2026-11-15', 'surgeryway' => 1,
            ]);
            DB::table('pathology')->insert(['patientid' => $id, 'hospseq' => 1, 'pycode' => 'ROUTE-PY', 'who' => 65]);
            DB::table('radiotherapy')->insert(['patientid' => $id, 'hospseq' => 1, 'treatmentid' => $id, 'ryaction' => 2]);
        }
    }

    /**
     * 每条记录依次为请求参数、响应关键字段、应持久化的数据。
     *
     * @return array<string,array{array<string,mixed>,array<string,mixed>,array<string,array<string,mixed>>}>
     */
    private static function cases(): array
    {
        $cases = [
            'GET /'                       => [[], ['code' => 0], []],
            'GET /index'                  => [[], ['code' => 0], []],
            'GET /signin/csrf'            => [[], ['msg' => '会话已初始化'], []],
            'POST /signin/logout'         => [[], ['msg' => '已退出登录'], []],
            'GET /signin/captcha'         => [[], [], []],
            'POST /signin/dologin'        => [['mobile' => '13800000001', 'password' => 'route-test-password'], ['data.user.name' => '路由测试医生1'], []],
            'GET /signin/sendsmscode'     => [['mobile' => '13800000001'], ['msg' => '验证码已发送'], ['smscode' => ['userid' => 1, 'mobile' => '13800000001']]],
            'POST /signin/resetpassword'  => [['mobile' => '13800000001', 'smscode' => '123456'], ['msg' => '密码已重置'], []],
            'GET /signin/islogin'         => [[], ['data.user.name' => '路由测试医生1'], []],
            'POST /signin/changepassword' => [['password' => 'route-test-password', 'password1' => 'changed-password', 'password2' => 'changed-password'], ['msg' => '密码修改成功'], []],
            'GET /category'               => [[], ['data.route_category.0.title' => '路由测试字典项'], []],
            'GET /globaloptions'          => [[], ['data.opTotal' => 1, 'data.ihTotal' => 1], []],
            'GET /operator'               => [[], ['data.operator' => true, 'data.candownload' => true], []],
            'POST /upload'                => [['patientid' => 102, 'uid' => 'route-upload'], ['data.uid' => 'route-upload'], ['media' => ['patientid' => 102, 'deletedat' => 0]]],
            'GET /hospital/all'           => [[], ['data.0.hospitalid' => 2], []],
            'GET /hospital/items'         => [['title' => '路由测试医院'], ['data.total' => 1, 'data.items.0.title' => '路由测试医院'], []],
            'POST /hospital/update'       => [['hospitalid' => 2, 'title' => '路由更新医院'], ['data.title' => '路由更新医院'], ['hospital' => ['id' => 2, 'title' => '路由更新医院']]],
            'DELETE /hospital/delete'     => [['hospitalid' => 2], ['data.hospitalid' => 2], []],
            'GET /doctor/items'           => [['title' => '路由测试医生2'], ['data.total' => 1, 'data.items.0.doctorid' => 2], []],
            'POST /doctor/update'         => [['doctorid' => 2, 'realname' => '路由更新医生', 'mobile' => '13800000002', 'hospitalid' => 1, 'permissions' => ['download.patient' => 'true']], ['data.realname' => '路由更新医生', 'data.permissions.0' => 'download.patient'], ['user' => ['id' => 2, 'realname' => '路由更新医生'], 'userpermission' => ['doctorid' => 2, 'permissioncode' => 'download.patient', 'deletedat' => 0]]],
            'POST /doctor/resetpassword'  => [['doctorid' => 2], ['data.doctorid' => 2], []],
            'DELETE /doctor/delete'       => [['doctorid' => 2], ['data.doctorid' => 2], []],
        ];
        foreach (['outpatient' => 101, 'inhospital' => 102] as $module => $id) {
            $inpatient     = $module === 'inhospital';
            $patient       = ['patientid' => $id];
            $videoType     = $inpatient ? VideoType::IhBefore->value : VideoType::OpFisrt->value;
            $situationType = $inpatient ? SituationType::IhInhospital->value : SituationType::OpFisrt->value;
            $name          = $inpatient ? '路由住院患者' : '路由门诊患者';
            $cases += [
                "GET /$module/items"            => [[], ['data.total' => 1, 'data.items.0.patientid' => $id], []],
                "GET /$module/download"         => [[], [], []],
                "POST /$module/updatepatient"   => [$patient + ['realname' => '路由更新患者', 'gender' => 1, 'age' => 46, 'mobile' => '13900000101', 'firstday' => '2026-11-14', 'hospday' => '2026-11-14', 'hospcode' => 'ROUTE-UPDATED'], ['data.patientid' => $id], ['patient' => ['id' => $id, 'realname' => '路由更新患者', 'age' => 46]]],
                "GET /$module/getpatient"       => [$patient, ['data.patientid' => $id, 'data.realname' => $name], []],
                "DELETE /$module/delete"        => [$patient, ['code' => 0], []],
                "POST /$module/updatesituation" => [$patient + ['comefrom' => $situationType, 'cc' => '更新后的主诉', 'kps' => 90], ['data.patientid' => $id], ['situation' => ['patientid' => $id, 'treatmentid' => 0, 'stype' => $situationType, 'cc' => '更新后的主诉', 'kps' => 90]]],
                "GET /$module/getsituation"     => [$patient + ['comefrom' => $situationType], ['data.cc' => '路由测试主诉', 'data.kps' => 100], []],
                "POST /$module/updatevideo"     => [$patient + ['vtype' => $videoType, 'videoday' => '2026-11-20', 'cemrilength' => 20], ['data.patientid' => $id], ['video' => ['patientid' => $id, 'treatmentid' => 0, 'vtype' => $videoType, 'cemrilength' => 20]]],
                "GET /$module/getvideo"         => [$patient + ['vtype' => $videoType], ['data.cemrilength' => 10], []],
                "GET /$module/homepage"         => [$patient, ['data.patientid' => $id, 'data.patient.realname' => $name, 'data.situation.cc' => '路由测试主诉'], []],
            ];
            $action = $inpatient ? 'followup' : 'check';
            $cases += [
                "POST /$module/update$action"    => [$patient + ['treatment' => ['checkday' => '2027-01-14', 'checkalive' => 1], 'situation' => ['cc' => '新增复查随访主诉'], 'video' => ['videoday' => '2027-01-14']], ['data.patientid' => $id], ['treatment' => ['patientid' => $id, 'checkday' => '2027-01-14'], 'situation' => ['patientid' => $id, 'cc' => '新增复查随访主诉'], 'patient' => ['id' => $id, $action.'count' => 2]]],
                "GET /$module/get$action"        => [$patient + ['treatmentid' => $id], ['data.treatmentid' => $id, 'data.situation.cc' => '路由测试主诉'], []],
                "GET /$module/get{$action}count" => [$patient, ['data.patientid' => $id, 'data.'.$action.'count' => 1], []],
                "GET /$module/{$action}page"     => [$patient + ['treatmentid' => $id], ['data.treatmentid' => $id, 'data.patient.realname' => $name], []],
                "GET /$module/{$action}daylist"  => [$patient, ['data.0.treatmentid' => $id, 'data.0.checkday' => '2026-12-14'], []],
            ];
        }

        return $cases + [
            'POST /outpatient/hospitalized'       => [['patientid' => 101], ['code' => 0], ['patient' => ['id' => 101, 'isin' => 1, 'hospseq' => 1]]],
            'GET /outpatient/ishosp'              => [['patientid' => 101], ['data.isin' => 0], []],
            'POST /inhospital/inhospagain'        => [['patientid' => 102], ['data.hospseq' => 2], ['patient' => ['id' => 102, 'hospseq' => 2, 'hospday' => null], 'hospday' => ['patientid' => 102, 'hospseq' => 1, 'inday' => '2026-11-14'], 'progress' => ['patientid' => 102, 'hospsituation' => 0, 'surgery' => 0]]],
            'POST /inhospital/updatecode'         => [['patientid' => 102, 'hospcode' => 'ROUTE-NEW', 'hospday' => '2026-11-20'], ['data.hospcode' => 'ROUTE-NEW'], ['patient' => ['id' => 102, 'hospcode' => 'ROUTE-NEW', 'hospday' => '2026-11-20']]],
            'POST /inhospital/updateinitialstate' => [['patientid' => 102, 'data' => ['initialstate' => 2]], ['data.data.initialstate' => 2], ['patient' => ['id' => 102, 'hospinitialstate' => 2]]],
            'GET /inhospital/getinitialstate'     => [['patientid' => 102], ['data.initialstate' => 1], []],
            'POST /inhospital/updatesurgery'      => [['patientid' => 102, 'surgeryday' => '2026-11-20', 'surgeryway' => 2], ['data.patientid' => 102], ['surgery' => ['patientid' => 102, 'surgeryday' => '2026-11-20', 'surgeryway' => 2]]],
            'GET /inhospital/getsurgery'          => [['patientid' => 102], ['data.surgeryday' => '2026-11-15', 'data.surgeryway' => 1], []],
            'POST /inhospital/updatepathology'    => [['patientid' => 102, 'pycode' => 'ROUTE-PY-NEW', 'who' => 65], ['data.patientid' => 102], ['pathology' => ['patientid' => 102, 'pycode' => 'ROUTE-PY-NEW', 'who' => 65]]],
            'GET /inhospital/getpathology'        => [['patientid' => 102], ['data.pycode' => 'ROUTE-PY'], []],
            'GET /inhospital/homepagedaylist'     => [['patientid' => 102], ['data.0.hospseq' => 1, 'data.0.hospday' => '2026-11-14'], []],
            'GET /inhospital/getmedia'            => [['patientid' => 102], ['data.0.id' => 1], []],
            'DELETE /inhospital/deletemedia'      => [['patientid' => 102, 'fileid' => 1], ['code' => 0], []],
        ];
    }

    /**
     * @return iterable<string,array{string,string,array<string,mixed>,array<string,mixed>,array<string,array<string,mixed>>}>
     */
    public static function routeCases(): iterable
    {
        foreach (self::cases() as $route => [$parameters, $expected, $records]) {
            [$method, $uri] = explode(' ', $route, 2);
            yield $route => [$method, $uri, $parameters, $expected, $records];
        }
    }

    public function test_every_registered_business_route_has_a_success_case(): void
    {
        $registered = [];
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with(ltrim($route->getActionName(), '\\'), 'Mews\\Captcha\\')) {
                continue;
            }
            if (! in_array('web', $route->gatherMiddleware(), true)) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $registered[] = $method.' /'.ltrim($route->uri(), '/');
            }
        }
        $covered = array_keys(self::cases());
        sort($registered);
        sort($covered);
        $this->assertSame($registered, $covered, '路由与成功用例不一致，请为新增或变更的接口补充测试。');
    }

    /**
     * @param  array<string,mixed>  $parameters
     * @param  array<string,mixed>  $expected
     * @param  array<string,array<string,mixed>>  $records
     */
    #[DataProvider('routeCases')]
    public function test_route_success_contract(string $method, string $uri, array $parameters, array $expected, array $records): void
    {
        if (! in_array($uri, ['/', '/index', '/signin/csrf', '/signin/captcha', '/signin/dologin', '/signin/sendsmscode', '/signin/resetpassword'], true)) {
            $user = User::findOrFail(1);
            $this->actingAs($user)->withSession(['auth_password' => $user->password]);
        }
        if ($uri === '/signin/dologin') {
            $parameters['captcha'] = $this->issueLoginCaptcha();
        }
        if ($uri === '/signin/resetpassword') {
            DB::table('smscode')->insert(['userid' => 1, 'mobile' => '13800000001', 'smscode' => '123456', 'createdat' => time(), 'smsday' => date('Ymd')]);
        }
        if ($uri === '/upload') {
            $parameters['file'] = UploadedFile::fake()->image('路由上传.png', 300, 200);
        }

        // 与前端一致：GET 使用 query，写请求使用表单，DELETE 参数保留在 body。
        $response = $this->call($method, $uri, $parameters, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response->assertOk();
        if (str_ends_with($uri, '/download')) {
            $this->assertDownload($response, str_starts_with($uri, '/outpatient') ? '路由门诊患者' : '路由住院患者', false);

            return;
        }
        $response->assertJsonStructure(['code', 'msg', 'data'])->assertJsonPath('code', 0);
        $this->assertIsString($response->json('msg'));
        foreach ($expected as $path => $value) {
            $response->assertJsonPath($path, $value);
        }
        foreach ($records as $table => $record) {
            $this->assertDatabaseHas($table, $record);
        }
        $this->assertSideEffects($uri, $parameters, $response);
        if (! in_array($uri, ['/signin/sendsmscode', '/signin/resetpassword'], true)) {
            Http::assertNothingSent();
        }
    }

    /**
     * @param  array<string,mixed>  $parameters
     */
    private function assertSideEffects(string $uri, array $parameters, TestResponse $response): void
    {
        $deleted = match ($uri) {
            '/hospital/delete'        => ['hospital', 2],
            '/doctor/delete'          => ['user', 2],
            '/outpatient/delete'      => ['patient', 101],
            '/inhospital/delete'      => ['patient', 102],
            '/inhospital/deletemedia' => ['media', 1],
            default                   => null,
        };
        if ($deleted !== null) {
            [$table, $id] = $deleted;
            $this->assertGreaterThan(0, DB::table($table)->where('id', $id)->value('deletedat'));
        }
        if ($uri === '/signin/dologin' || $uri === '/signin/changepassword') {
            $this->assertAuthenticatedAs(User::findOrFail(1));
            $this->assertSame(User::findOrFail(1)->password, session('auth_password'));
            $response->assertJsonMissingPath('data.user.password')->assertJsonMissingPath('data.user.salt');
        }
        if ($uri === '/signin/logout') {
            $this->assertGuest();
            $this->assertFalse(session()->has('auth_password'));
        }
        if ($uri === '/signin/csrf') {
            $response->assertCookie('XSRF-TOKEN');
        }
        if ($uri === '/signin/changepassword') {
            $this->assertTrue(Hash::check('changed-password', User::findOrFail(1)->password));
        }
        if ($uri === '/doctor/resetpassword') {
            $this->assertTrue(Hash::check('0000002', User::findOrFail(2)->password));
        }
        if ($uri === '/signin/sendsmscode' || $uri === '/signin/resetpassword') {
            Http::assertSentCount(1);
            Http::assertSent(function (Request $request) use ($uri): bool {
                $data = json_decode($request['templateParam'], true, 512, JSON_THROW_ON_ERROR);
                if ($uri === '/signin/sendsmscode') {
                    $code = (string) DB::table('smscode')->where('userid', 1)->value('smscode');
                    $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
                    $this->assertSame($code, $data['code']);
                } else {
                    $this->assertTrue(Hash::check($data['pwd'], User::findOrFail(1)->password));
                    $this->assertDatabaseCount('smscode', 0);
                }

                return $request->url() === 'https://sms.example.test/send' && $request['phoneNumbers'] === '13800000001';
            });
            $this->assertDatabaseHas('smslog', ['userid' => 1, 'mobile' => '13800000001']);
        }
        if ($uri === '/upload') {
            $this->assertDatabaseHas('media', ['id' => $response->json('data.id'), 'patientid' => $parameters['patientid']]);
            foreach (['url', 'fullurl'] as $field) {
                Storage::disk('patient_media')->assertExists(substr($response->json('data.'.$field), strlen('/file/')));
            }
        }
    }

    private function assertDownload(TestResponse $response, string $patientName, bool $word): void
    {
        $response->assertHeader('content-disposition');
        $response->assertHeader('content-type', $word ? 'application/vnd.ms-word' : 'application/vnd.ms-excel');
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $file = $response->baseResponse->getFile()->getPathname();
        try {
            if ($word) {
                $zip = new ZipArchive;
                $this->assertTrue($zip->open($file));
                try {
                    $xml = $zip->getFromName('word/document.xml');
                    $this->assertIsString($xml);
                    $this->assertStringContainsString($patientName, $xml);
                } finally {
                    $zip->close();
                }
            } else {
                $book = IOFactory::load($file);
                try {
                    $this->assertStringContainsString($patientName, json_encode($book->getActiveSheet()->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                } finally {
                    $book->disconnectWorksheets();
                }
            }
        } finally {
            // 测试客户端不执行 sendContent，需主动清理下载响应生成的临时文件。
            unlink($file);
        }
    }

    public function test_individual_patient_word_downloads(): void
    {
        $user = User::findOrFail(1);
        $this->actingAs($user)->withSession(['auth_password' => $user->password]);
        foreach (['outpatient' => [101, '路由门诊患者'], 'inhospital' => [102, '路由住院患者']] as $module => [$id, $name]) {
            $this->assertDownload($this->get('/'.$module.'/download?patientid='.$id)->assertOk(), $name, true);
        }
    }

    public function test_framework_health_endpoint(): void
    {
        $this->get('/up')->assertOk();
    }
}
