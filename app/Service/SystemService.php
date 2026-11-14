<?php

namespace App\Service;

use App\Repositories\CategoryRepository;

/**
 * Class SystemService
 */
class SystemService
{
    public function __construct(
        protected readonly CategoryRepository $categoryRepository,
    ) {}

    /**
     * 体能状态评分标准 Karnofsky Performance Status (KPS)
     * https://medicalcriteria.com/web/onck/
     * https://doctor-network.com/Public/LittleTools/376.html
     *
     * KPS 和 category表ID 的映射
     */
    public const KPS_MAPPING = [
        2  => 100,
        3  => 90,
        4  => 80,
        5  => 70,
        6  => 60,
        7  => 50,
        8  => 40,
        9  => 30,
        10 => 20,
        11 => 10,
        12 => 0,
    ];

    /**
     * 分类
     *
     * 父分类以 ckey 作为接口键名，title 仅用于显示；兼容旧数据中以 title 保存英文键名的情况。
     *
     * @return array<string,array<mixed>>
     */
    public function categories()
    {
        $cats = $this->categoryRepository->ordered();

        $categories = [];
        $titles     = [];
        foreach ($cats as $cat) {
            if ($cat['parentid'] == 0) {
                $key = strtolower(trim($cat['ckey'] ?? ''));
                if ($key === '') {
                    $key = strtolower($cat['title']);
                }
                $titles[$cat['id']] = $key;

                $categories[$key] = [];
            } else {
                $id = $titles[$cat['parentid']] == 'kps' ? static::KPS_MAPPING[$cat['id']] : $cat['id'];

                $categories[$titles[$cat['parentid']]][] = [
                    'value' => $id,
                    'title' => $cat['title'],
                ];
            }
        }

        return $categories;
    }

    /**
     * 分类
     *
     * 与表单选项共用分类键名，供详情和导出解析已保存的选项 ID。
     *
     * @return array<string,array<mixed>>
     */
    public function categoriesKV()
    {
        $categories = $this->categories();
        foreach ($categories as $k => $v) {
            $categories[$k] = array_column($v, 'title', 'value');
        }

        return $categories;
    }
}
