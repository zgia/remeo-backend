<?php

namespace App\Controller;

use App\Common\CommonConst;
use App\Service\PatientService;
use App\Service\SystemService;
use Illuminate\Http\JsonResponse;

/**
 * 首页控制器
 */
class IndexController extends BaseController
{
    public function __construct(private readonly SystemService $systemService, private readonly PatientService $patientService) {}

    /**
     * 网站首页
     */
    public function index(): JsonResponse
    {
        return $this->success('Hello World.');
    }

    public function category(): JsonResponse
    {
        $categories = $this->systemService->categories();

        return $this->success('category', $categories);
    }

    public function globalOptions(): JsonResponse
    {
        $categories = $this->systemService->categories();
        $options    = [
            'pwdminlen' => getOption('pwdminlen'),
            'smslife'   => getOption('smslife'),
        ];

        $patients = $this->patientService->getTotalGroupbyType();

        $data = [
            'categories' => $categories,
            'options'    => $options,
            'perpage'    => CommonConst::PAGE_SIZE,
            'opTotal'    => $patients[1] ?? 0,
            'ihTotal'    => $patients[0] ?? 0,
        ];

        return $this->success('category', $data);
    }

    public function operator(): JsonResponse
    {
        return $this->success('operator', ['operator' => isOperator(), 'viewer' => isViewer(), 'candownload' => canDownload()]);
    }
}
