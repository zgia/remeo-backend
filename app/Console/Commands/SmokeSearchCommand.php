<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 用当前库的真实数据对搜索、列表和患者详情接口做只读冒烟。
 *
 * 测试 fixture 造不出软删除的医生/医院、空字符串和 NULL 日期这类真实形态，
 * 这里直接复用真实 HTTP Kernel 和中间件跑一遍，把 5xx 与日志里的 Warning 抓出来。
 * 全程只发送 GET，并包在一个事务里，结束时无条件回滚。
 */
final class SmokeSearchCommand extends Command
{
    /**
     * 判定为失败的日志关键字：数组缺键与空值取属性都会在列表/详情里出现。
     */
    private const FAILURE_PATTERNS = '/Undefined array key|Trying to access array offset|must be of type|Attempt to read property/i';

    private const LOG_LEVELS = ['notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    protected $signature = 'remeo:smoke
        {--user=3 : 以哪个账号登录运行，默认是开发库的超级用户}
        {--strict : 边界探测组的发现也计为失败}
        {--force : 允许在 production 环境运行}';

    protected $description = '用真实数据只读冒烟搜索/列表/详情接口，捕获 5xx 与警告日志';

    /**
     * @var list<string>
     */
    private array $logs = [];

    private User $user;

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('默认禁止在 production 运行；确认只读且可回滚后加 --force。');

            return self::FAILURE;
        }

        $user = User::query()->find((int) $this->option('user'));
        if (! $user instanceof User) {
            $this->error('找不到用于冒烟的账号：'.$this->option('user'));

            return self::FAILURE;
        }

        $this->user = $user;

        // 控制台没有会话，改用数组驱动避免写 session 文件；剩余数据写入由事务回滚兜住。
        config(['session.driver' => 'array']);
        Auth::guard()->login($user);
        session()->put('auth_password', $user->getAuthPassword());

        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            if (in_array($event->level, self::LOG_LEVELS, true)) {
                $this->logs[] = '['.$event->level.'] '.$event->message;
            }
        });

        $cases    = $this->buildCases();
        $kernel   = app(HttpKernel::class);
        $failures = [];
        $findings = [];
        $rows     = [];

        DB::beginTransaction();

        try {
            foreach ($cases as $case) {
                $this->logs = [];
                $started    = hrtime(true);

                try {
                    $response = $kernel->handle(Request::create($case['uri'], 'GET'));
                    $status   = $response->getStatusCode();
                    $body     = $this->decodeBody($response);
                } catch (Throwable $exception) {
                    $status = 500;
                    $body   = ['msg' => $exception->getMessage()];
                }

                $elapsed    = round((hrtime(true) - $started) / 1_000_000).'ms';
                $logs       = $this->logs;
                $this->logs = [];

                [$result, $note] = $this->classify($case, $status, $body, $logs);

                if ($result === '失败') {
                    $failures[] = $case['uri'].' :: '.$note;
                } elseif ($result === '发现') {
                    $findings[] = $case['uri'].' :: '.$note;
                }

                $rows[] = [$case['group'], $this->shorten($case['uri']), $status, $elapsed, $result, $this->shorten($note)];
            }
        } finally {
            DB::rollBack();
        }

        $this->table(['分组', '请求', 'HTTP', '耗时', '结果', '备注'], $rows);
        $this->newLine();
        $this->line('用例 '.count($cases).' 个：失败 '.count($failures).' 个，发现 '.count($findings).' 个。');

        foreach ($findings as $finding) {
            $this->warn('发现：'.$finding);
        }

        foreach ($failures as $failure) {
            $this->error('失败：'.$failure);
        }

        if ($failures !== []) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $findings !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{group:string,uri:string,note:string,minTotal:int}>
     */
    private function buildCases(): array
    {
        // 列表按当前医院过滤，样本必须落在同一个医院，否则冒烟会空跑。
        $hospital    = (int) ($this->user->hospitalid ?? 0);
        $inpatient   = 'isin = 1 AND deletedin = 0 AND hospitalid = '.$hospital;
        $outpatient  = 'isout = 1 AND deletedout = 0 AND hospitalid = '.$hospital;
        $deletedUser = 'doctorid IN (SELECT id FROM user WHERE deleted_at IS NOT NULL)';

        $inpatientName    = $this->sample('realname', $inpatient." AND realname REGEXP '[^ -~]'");
        $outpatientName   = $this->sample('realname', $outpatient." AND realname REGEXP '[^ -~]'");
        $hospcode         = $this->sample('hospcode', $inpatient." AND hospcode <> ''");
        $deletedDoctorIn  = $this->sample('realname', $inpatient.' AND '.$deletedUser);
        $deletedDoctorOut = $this->sample('realname', $outpatient.' AND '.$deletedUser);

        $cases = [];

        foreach (['/outpatient/items', '/inhospital/items'] as $uri) {
            $cases[] = $this->case('断言', $uri, []);
            $cases[] = $this->case('断言', $uri, ['p' => 2]);
            $cases[] = $this->case('断言', $uri, ['p' => 999]);
            $cases[] = $this->case('断言', $uri, ['sort' => 'id', 'order' => 'ascending']);
            $cases[] = $this->case('断言', $uri, ['sort' => 'id', 'order' => 'descending']);
            $cases[] = $this->case('断言', $uri, ['hospitalid' => -1]);
        }

        if ($outpatientName !== null) {
            $cases[] = $this->case('断言', '/outpatient/items', ['realname' => $outpatientName], '中文姓名搜索', 1);
        }

        if ($inpatientName !== null) {
            $cases[] = $this->case('断言', '/inhospital/items', ['realname' => $inpatientName], '中文姓名搜索', 1);
        }

        if ($deletedDoctorIn !== null) {
            $cases[] = $this->case('断言', '/inhospital/items', ['realname' => $deletedDoctorIn], '软删除医生的患者搜索', 1);
        }

        if ($deletedDoctorOut !== null) {
            $cases[] = $this->case('断言', '/outpatient/items', ['realname' => $deletedDoctorOut], '软删除医生的患者搜索', 1);
        }

        if ($hospcode !== null) {
            // 住院号分支：纯数字/字母会走 hospcode LIKE，姓名分支则不会。
            $cases[] = $this->case('断言', '/inhospital/items', ['realname' => $hospcode], '住院号搜索', 1);
        }

        foreach (['/hospital/items', '/doctor/items', '/globaloptions', '/category', '/operator', '/signin/islogin'] as $uri) {
            $cases[] = $this->case('断言', $uri, []);
        }

        foreach ($this->detailedPatients() as [$patientid, $uri]) {
            $cases[] = $this->case('断言', $uri, ['patientid' => $patientid], '脏数据患者');
        }

        // 边界探测：只报告不失败，避免把已知的历史欠账混进冒烟结论。
        $cases[] = $this->case('探测', '/outpatient/items', ['realname' => '%']);
        $cases[] = $this->case('探测', '/inhospital/items', ['realname' => '_']);
        $cases[] = $this->case('探测', '/inhospital/items', ['realname' => str_repeat('测', 200)]);
        $cases[] = $this->case('探测', '/inhospital/items', ['sort' => 'not_a_column', 'order' => 'ascending']);
        $cases[] = $this->case('探测', '/outpatient/items', ['sort' => 'id', 'order' => 'sideways']);
        $cases[] = $this->case('探测', '/inhospital/getpatient', ['patientid' => 999999], '不存在的患者');

        return $cases;
    }

    /**
     * @param  array<string,mixed>  $query
     * @return array{group:string,uri:string,note:string,minTotal:int}
     */
    private function case(string $group, string $uri, array $query, string $note = '', int $minTotal = 0): array
    {
        $query = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'group'    => $group,
            'uri'      => $query === [] ? $uri : $uri.'?'.http_build_query($query),
            'note'     => $note,
            'minTotal' => $minTotal,
        ];
    }

    /**
     * @param  array{group:string,uri:string,note:string,minTotal:int}  $case
     * @param  array<string,mixed>  $body
     * @param  list<string>  $logs
     * @return array{0:string,1:string}
     */
    private function classify(array $case, int $status, array $body, array $logs): array
    {
        $notes = [$case['note']];

        foreach ($logs as $log) {
            if (preg_match(self::FAILURE_PATTERNS, $log) === 1 || str_starts_with($log, '[error]')) {
                $notes[] = $log;

                return [$case['group'] === '断言' ? '失败' : '发现', implode(' | ', array_filter($notes))];
            }
        }

        if ($logs !== []) {
            $notes[] = implode(' | ', $logs);
        }

        if ($status >= 500) {
            $notes[] = 'HTTP '.$status.' '.($body['msg'] ?? '');

            return [$case['group'] === '断言' ? '失败' : '发现', implode(' | ', array_filter($notes))];
        }

        // 探测组的非法参数由 FormRequest 拒绝是预期行为，不应算作问题。
        if ($status >= 400) {
            $notes[] = 'HTTP '.$status.' '.($body['msg'] ?? '');

            return [$case['group'] === '探测' ? '通过' : '发现', implode(' | ', array_filter($notes))];
        }

        if ($status !== 200) {
            $notes[] = 'HTTP '.$status.' '.($body['msg'] ?? '');

            return ['发现', implode(' | ', array_filter($notes))];
        }

        if (($body['code'] ?? 0) !== 0) {
            $notes[] = 'code '.$body['code'].' '.($body['msg'] ?? '');

            return ['发现', implode(' | ', array_filter($notes))];
        }

        // 真实样本必须真的命中数据，否则冒烟看似通过却什么都没覆盖。
        if ($case['minTotal'] > 0 && (int) ($body['data']['total'] ?? 0) < $case['minTotal']) {
            $notes[] = '样本未命中，冒烟未覆盖到该形态';

            return ['发现', implode(' | ', array_filter($notes))];
        }

        return ['通过', implode(' | ', array_filter($notes))];
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeBody(Response $response): array
    {
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 取真实数据里出现的脏形态患者，用患者详情接口验证空日期与软删除父记录。
     *
     * @return list<array{0:int,1:string}>
     */
    private function detailedPatients(): array
    {
        $hospital = (int) ($this->user->hospitalid ?? 0);

        $softDeletedDoctor = DB::table('patient')
            ->select(['id', 'isin'])
            ->where('isin', 1)
            ->where('hospitalid', $hospital)
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('user')
                    ->whereColumn('user.id', 'patient.doctorid')
                    ->whereNotNull('user.deleted_at');
            })
            ->orderBy('id')
            ->limit(2)
            ->get();

        $inpatientEmptyDate = DB::table('patient')
            ->select(['id', 'isin'])
            ->where('isin', 1)
            ->where('hospitalid', $hospital)
            ->where(function ($query): void {
                $query->whereNull('nextfollowupday')->orWhereNull('lastfollowupday');
            })
            ->orderBy('id')
            ->limit(1)
            ->get();

        $emptyIdcard = DB::table('patient')
            ->select(['id', 'isin'])
            ->where('isin', 1)
            ->where('hospitalid', $hospital)
            ->where('idcard', '')
            ->orderBy('id')
            ->limit(1)
            ->get();

        $outpatientEmptyDate = DB::table('patient')
            ->select(['id', 'isin'])
            ->where('isout', 1)
            ->where('hospitalid', $hospital)
            ->whereNull('nextfollowupday')
            ->orderBy('id')
            ->limit(1)
            ->get();

        $patients = [];
        foreach ($softDeletedDoctor->merge($inpatientEmptyDate)->merge($emptyIdcard)->merge($outpatientEmptyDate) as $row) {
            $module                   = (int) $row->isin === 1 ? 'inhospital' : 'outpatient';
            $patients[(int) $row->id] = [(int) $row->id, '/'.$module.'/getpatient'];
        }

        // 每个患者同时检查详情与首页两个读取路径。
        $cases = [];
        foreach ($patients as [$patientid, $uri]) {
            $cases[] = [$patientid, $uri];
            $cases[] = [$patientid, dirname($uri).'/homepage'];
        }

        return $cases;
    }

    private function sample(string $column, string $where): ?string
    {
        $value = DB::table('patient')->whereRaw($where)->value($column);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function shorten(string $text, int $limit = 90): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit).'…' : $text;
    }
}
