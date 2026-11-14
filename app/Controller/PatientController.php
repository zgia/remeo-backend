<?php

namespace App\Controller;

use App\Exception\PatientException;
use App\Http\Requests\PatientUpdateVideoRequest;
use App\Http\Requests\UploadMediaRequest;
use App\Service\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 患者
 */
class PatientController extends BaseController
{
    protected int $patientid = 0;

    /**
     * 构造函数
     */
    public function __construct(protected readonly PatientService $patientService, Request $request)
    {
        $request->validate(['patientid' => ['nullable', 'integer', 'min:0']]);

        $this->patientid = $request->integer('patientid');
        if ($this->patientid) {
            try {
                $this->patientService->getPatientById($this->patientid);
            } catch (PatientException $exception) {
                abort(404, $exception->getMessage());
            }
        }
    }

    /**
     * 影像学记录
     */
    public function getVideo(): JsonResponse
    {
        // f 和 b
        $vtype = (string) request()->query('vtype', '');

        $video = $this->patientService->getVideo(0, $vtype);

        return $this->success('患者影像记录', $video);

    }

    /**
     * 影像学记录
     */
    public function updateVideo(PatientUpdateVideoRequest $request): JsonResponse
    {
        $video = $request->validated();

        $video = $this->patientService->updateVideo($video);

        return $this->success('患者影像学检查记录添加成功。', $video);

    }

    /**
     * 影像资料
     */
    public function getMedia(): JsonResponse
    {
        $media = $this->patientService->getMedia();

        return $this->success('患者影像资料', $media);

    }

    /**
     * 影像资料
     */
    public function deleteMedia(): JsonResponse
    {
        $fileid = max(0, request()->integer('fileid'));

        $this->patientService->deleteMedia($fileid);

        return $this->success('成功删除患者影像资料。');

    }

    public function uploadMedia(UploadMediaRequest $request): JsonResponse
    {
        $media = $this->patientService->uploadMedia($request->file('file'));
        $uid   = (string) $request->input('uid', '');

        $media['uid'] = $uid;

        return $this->success('患者影像资料', $media);

    }
}
