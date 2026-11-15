<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Log;

/**
 * 日志类
 */
class LogRepository
{
    /**
     * 管理日志
     *
     * @param  array<string,mixed>  $data  数据
     * @return int 日志ID
     */
    public function record(array $data): int
    {
        if (! empty($data['from']) && ! empty($data['to'])) {
            $data['to'] = array_diff_assoc($data['to'], $data['from']);
        }

        foreach (['from', 'to'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                unset($data[$key]['password'], $data[$key]['salt']);
            }
        }

        return Log::query()
            ->insertGetId(
                [
                    'type'        => (string) ($data['type'] ?? request()->route()?->getActionMethod()),
                    'objectid'    => (int) $data['id'],
                    'script'      => $data['script'] ?? request()->path(),
                    'action'      => (string) ($data['action'] ?? request()->route()?->getActionName()),
                    'ipaddress'   => request()->ip(),
                    'userid'      => (int) ($data['userid'] ?? currentUserId()),
                    'fromcontent' => json_encode($data['from'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'tocontent'   => json_encode($data['to'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
    }

    /**
     * 获取日志详细信息
     *
     * @param  int  $logid  日志ID
     * @return array<string,mixed>
     */
    public function getLogDetail(int $logid)
    {
        $record = Log::query()
            ->where('id', $logid)
            ->first();
        if ($record === null) {
            return [];
        }
        $item = $record->getAttributes();

        $this->parseContent($item['fromcontent']);
        $this->parseContent($item['tocontent']);

        return $item;
    }

    /**
     * 获取日志
     *
     * @param  array<string,mixed>  $conditions
     * @param  int  $offset  数据偏移量
     * @param  int  $limit  获取数据数量
     * @return array<array<string,mixed>>
     */
    public function getLogs(array $conditions, int $offset = 0, int $limit = 20)
    {
        $items = Log::query()
            ->where($conditions)
            ->orderByDesc('id')
            ->offset(max(0, $offset))
            ->limit($limit)
            ->get()
            ->keyBy('id')
            ->map(fn (Log $record): array => $record->getAttributes())
            ->all();

        foreach ($items as &$item) {
            $this->parseContent($item['fromcontent']);
            $this->parseContent($item['tocontent']);
        }

        return $items;
    }

    /**
     * 解析日志内容
     *
     * @param  string  $content
     */
    private function parseContent(mixed &$content = null): void
    {
        if ($content) {
            $content = json_decode($content, true);
        }
    }
}
