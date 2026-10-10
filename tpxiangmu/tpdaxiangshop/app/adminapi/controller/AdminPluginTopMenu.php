<?php

namespace app\adminapi\controller;

use app\admin\common\PluginTopMenuConfig as PluginTopMenuConfigService;

class AdminPluginTopMenu extends Common
{
    /**
     * @var PluginTopMenuConfigService
     */
    private $service;

    public function initialize()
    {
        parent::initialize();
        $this->service = new PluginTopMenuConfigService(intval($this->aid));
    }

    public function getList()
    {
        try {
            $list = $this->service->getManageList();
            return $this->success([
                'list' => $list,
                'total' => count($list),
                'hide_plugin_entry' => $this->service->getHidePluginEntry(),
            ], '获取成功');
        } catch (\Throwable $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
    }

    public function saveHidePluginEntry()
    {
        try {
            $hide = input('post.hide_plugin_entry/d', 0);
            $value = $this->service->saveHidePluginEntry($hide);
            return $this->success(['hide_plugin_entry' => $value], '保存成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function saveItem()
    {
        try {
            $info = input('post.info/a', []);
            $row = $this->service->saveConfig($info);
            return $this->success($row, '保存成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
