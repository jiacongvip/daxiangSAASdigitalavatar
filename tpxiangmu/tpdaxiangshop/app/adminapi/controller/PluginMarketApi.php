<?php
namespace app\adminapi\controller;

class PluginMarketApi extends Common
{
    public function getList()
    {
        return $this->success(['list' => [], 'total' => 0, 'ce_mode' => 1], '社区版无在线插件市场');
    }

    public function getRequestList()
    {
        return $this->success(['list' => [], 'total' => 0], '社区版无市场申请');
    }

    public function reviewRequest()
    {
        return $this->error('社区版不支持市场审核');
    }

    public function saveConfig()
    {
        return $this->error('社区版不支持市场配置');
    }

    public function __call($method, $args)
    {
        return $this->error('社区版不支持插件市场接口：' . $method);
    }
}
