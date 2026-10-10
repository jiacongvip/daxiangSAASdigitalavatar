<?php

namespace app\plugin\digitalavatar\controller;

use app\admin\controller\ApiCommon;
use app\plugin\digitalavatar\common\DigitalAvatarInstaller;
use app\plugin\digitalavatar\common\DigitalAvatarService;
use app\plugin\digitalavatar\common\DigitalAvatarTaskService;

class ApiDigitalAvatar extends ApiCommon
{
    public function initialize()
    {
        parent::initialize();
        DigitalAvatarInstaller::ensureInstalled();
    }

    public function getWorkbench()
    {
        if (intval($this->mid) <= 0) {
            return $this->json(['status' => 0, 'msg' => '请先登录会员账号']);
        }
        try {
            return $this->json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarService::getWorkbench(intval($this->aid ?: aid), intval(defined('bid') ? bid : 0), intval($this->mid)),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['status' => 0, 'msg' => '获取失败：' . $e->getMessage()]);
        }
    }

    public function createTask()
    {
        if (intval($this->mid) <= 0) {
            return $this->json(['status' => 0, 'msg' => '请先登录会员账号']);
        }
        try {
            $data = DigitalAvatarTaskService::createTask(
                intval($this->aid ?: aid),
                intval(defined('bid') ? bid : 0),
                intval($this->mid),
                input('post.')
            );
            $batchCreated = intval($data['batch_created'] ?? 0);
            $msg = $batchCreated > 1
                ? ('已提交 ' . $batchCreated . ' 个图片任务，将按单张分别生成和扣费')
                : '任务已进入队列';
            return $this->json([
                'status' => 1,
                'msg' => $msg,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['status' => 0, 'msg' => '创建失败：' . $e->getMessage()]);
        }
    }

    public function getTaskList()
    {
        if (intval($this->mid) <= 0) {
            return $this->json(['status' => 0, 'msg' => '请先登录会员账号']);
        }
        try {
            return $this->json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarTaskService::getMemberList(
                    intval($this->aid ?: aid),
                    intval(defined('bid') ? bid : 0),
                    intval($this->mid),
                    max(1, intval(input('post.page/d', 1))),
                    max(1, min(100, intval(input('post.limit/d', 20)))),
                    trim(strval(input('post.keyword/s', ''))),
                    trim(strval(input('post.status/s', '')))
                ),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['status' => 0, 'msg' => '获取失败：' . $e->getMessage()]);
        }
    }
}
