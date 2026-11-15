<?php

namespace App\Service;

use App\Common\CommonConst;
use App\Exception\BusinessException;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Repositories\HospitalRepository;
use App\Repositories\LogRepository;

class HospitalService
{
    public function __construct(
        protected readonly HospitalRepository $hospitalRepository,
        protected readonly LogRepository $logRepository,
    ) {}

    public function getTitle(int $hospitalid): ?string
    {
        return $this->hospitalRepository->titleFor($hospitalid);
    }

    /**
     * 所有的医院
     *
     * @return array<array<string,mixed>>
     */
    public function getAllItems()
    {
        return Hospital::query()
            ->select('id AS hospitalid', 'title')
            ->orderBy('title')
            ->get()
            ->map(fn (Hospital $record): array => $record->getAttributes())
            ->all();
    }

    /**
     * 列表
     *
     * @return array<string,mixed>
     */
    public function items(string $title)
    {
        $conds = [];
        // 搜索
        if ($title) {
            $conds[] = ['hospital.title', 'LIKE', '%'.$title.'%'];
        }

        $total = Hospital::query()->where($conds)->count();

        $hs = Hospital::query()
            ->where($conds)
            ->select('id AS hospitalid', 'title')
            ->orderBy('id', 'desc')
            ->forPage(currentPage(), CommonConst::PAGE_SIZE)
            ->get()
            ->map(fn (Hospital $record): array => $record->getAttributes())
            ->all();

        $doctors = User::query()
            ->selectRaw('hospitalid,COUNT(*) AS total')
            ->groupBy('hospitalid')
            ->pluck('total', 'hospitalid')
            ->all();
        $patients = Patient::query()
            ->selectRaw('hospitalid,COUNT(*) AS total')
            ->groupBy('hospitalid')
            ->pluck('total', 'hospitalid')
            ->all();

        foreach ($hs as &$h) {
            $h['doctors']  = $doctors[$h['hospitalid']]  ?? 0;
            $h['patients'] = $patients[$h['hospitalid']] ?? 0;
        }

        return ['total' => $total, 'items' => $hs];
    }

    /**
     * 更新
     *
     * @param  array<string,mixed>  $hospital
     * @return array<string,mixed>
     */
    public function update(array $hospital)
    {
        $duplicate = Hospital::query()
            ->where('title', $hospital['title'])
            ->when($hospital['hospitalid'], fn ($query, $id) => $query->where('id', '!=', $id))
            ->exists();
        if ($duplicate) {
            throw new BusinessException('已经有同名的医院了。');
        }

        if ($hospital['hospitalid']) {
            Hospital::query()
                ->where('hospital.id', $hospital['hospitalid'])
                ->update([
                    'title'      => $hospital['title'],
                    'updated_at' => now(),
                ]);
        } else {
            $hospital['hospitalid'] = Hospital::query()
                ->insertGetId([
                    'title'      => $hospital['title'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        // 保存日志
        $log = [
            'type'   => 'hospital',
            'action' => $hospital['hospitalid'] ? 'update' : 'insert',
            'to'     => $hospital,
            'id'     => $hospital['hospitalid'],
        ];
        $this->logRepository->record($log);

        return $hospital;
    }

    /**
     * 删除
     */
    public function delete(int $hospitalid): void
    {
        // 历史医生仍关联医院，删除检查需保留原先包含已删除医生的语义。
        $users = User::query()->withTrashed()->where('user.hospitalid', $hospitalid)->exists();
        if ($users) {
            throw new BusinessException('医院有医生存在，不可以删除。');
        }

        Hospital::query()->where('hospital.id', $hospitalid)->delete();
    }
}
