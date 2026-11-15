<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('hospital')->insert(['id' => 1, 'title' => '影像尺寸测试医院']);
        DB::table('user')->insert([
            'id'     => 1, 'hospitalid' => 1, 'realname' => '影像尺寸测试医生',
            'mobile' => '13800000001', 'password' => Hash::make('test-password'),
        ]);
        $user = User::findOrFail(1);
        $this->actingAs($user)->withSession(['auth_password' => $user->password]);
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function modules(): iterable
    {
        yield '门诊复查' => ['outpatient', 'check'];
        yield '住院随访' => ['inhospital', 'followup'];
    }

    private function createPatient(string $module): int
    {
        return $this->postJson('/'.$module.'/patient', [
            'realname' => '影像尺寸测试患者', 'gender' => 1, 'age' => 45,
            'mobile'   => '13900000001', 'hospcode' => 'VIDEO-001',
            'firstday' => '2026-11-14', 'hospday' => '2026-11-14',
        ])->assertOk()->json('data.patientid');
    }

    #[DataProvider('modules')]
    public function test_create_and_update_accept_empty_dimensions_and_integer_form_values(string $module, string $action): void
    {
        $patientid = $this->createPatient($module);
        $payload   = [
            'patientid' => $patientid,
            'treatment' => ['checkday' => '2026-12-14', 'checkalive' => 1],
            'video'     => [
                'cemrilength'   => '', 'cemriwidth' => null, 'cemrithick' => '12',
                't2flairlength' => 15, 't2flairwidth' => 65535,
            ],
        ];
        $treatmentid = $this->post('/'.$module.'/'.$action, $payload)->assertOk()
            ->assertJsonPath('code', 0)->json('data.treatmentid');
        $this->assertDatabaseHas('video', [
            'patientid'     => $patientid, 'treatmentid' => $treatmentid, 'videoday' => null,
            'cemrilength'   => 0, 'cemriwidth' => 0, 'cemrithick' => 12,
            't2flairlength' => 15, 't2flairwidth' => 65535, 't2flairthick' => 0,
        ]);
        $count                               = DB::table('treatment')->where('patientid', $patientid)->count();
        $payload['treatment']['treatmentid'] = $treatmentid;
        $payload['video']                    = ['cemrilength' => '25', 'cemriwidth' => 0, 't2flairwidth' => '', 'videoday' => ''];
        $this->post('/'.$module.'/'.$action, $payload)->assertOk()->assertJsonPath('code', 0);
        $this->assertDatabaseHas('video', [
            'patientid'     => $patientid, 'treatmentid' => $treatmentid, 'videoday' => null,
            'cemrilength'   => 25, 'cemriwidth' => 0, 'cemrithick' => 0,
            't2flairlength' => 0, 't2flairwidth' => 0, 't2flairthick' => 0,
        ]);
        $this->assertSame($count, DB::table('treatment')->where('patientid', $patientid)->count());
    }

    /**
     * @return iterable<string,array{string,string,mixed}>
     */
    public static function invalidDimensions(): iterable
    {
        foreach (self::modules() as $name => [$module, $action]) {
            foreach (['负数' => -1, '超出字段范围' => 65536, '小数' => 1.5, '非数字' => 'abc', '数组' => ['12'], '布尔值' => true] as $description => $value) {
                yield $name.'：'.$description => [$module, $action, $value];
            }
        }
    }

    #[DataProvider('invalidDimensions')]
    public function test_invalid_dimensions_are_rejected_before_writing(string $module, string $action, mixed $value): void
    {
        $patientid = $this->createPatient($module);
        $count     = DB::table('treatment')->count();
        $fields    = ['cemrilength', 'cemriwidth', 'cemrithick', 't2flairlength', 't2flairwidth', 't2flairthick'];
        $this->postJson('/'.$module.'/'.$action, [
            'patientid' => $patientid,
            'treatment' => ['checkday' => '2026-12-14'],
            'video'     => array_fill_keys($fields, $value),
        ])->assertStatus(422)->assertJsonValidationErrors(array_map(fn (string $field): string => 'video.'.$field, $fields), 'data.errors');
        $this->assertDatabaseCount('treatment', $count);
        $this->assertDatabaseCount('video', 0);
    }

    #[DataProvider('modules')]
    public function test_invalid_video_date_is_rejected_before_writing(string $module, string $action): void
    {
        $patientid = $this->createPatient($module);
        $count     = DB::table('treatment')->count();
        $this->postJson('/'.$module.'/'.$action, [
            'patientid' => $patientid,
            'treatment' => ['checkday' => '2026-12-14'],
            'video'     => ['videoday' => '2026-02-30'],
        ])->assertStatus(422)->assertJsonValidationErrors('video.videoday', 'data.errors');
        $this->assertDatabaseCount('treatment', $count);
        $this->assertDatabaseCount('video', 0);
    }
}
