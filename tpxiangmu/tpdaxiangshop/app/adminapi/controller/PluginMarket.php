<?php
/**
 * community-ce stub: plugin market is not part of the open-source base.
 * Keep my_plugin / market buttons from fatal 404; return empty local-only payload.
 */
namespace app\adminapi\controller;

class PluginMarket extends Common
{
    public function index()
    {
        return $this->success([
            'list' => [],
            'total' => 0,
            'categories' => [],
            'ce_mode' => 1,
            'notice' => '社区版不提供在线插件市场，请使用本地已授权插件（如 digitalavatar）。',
        ], '社区版无在线插件市场');
    }

    public function createRequest()
    {
        return $this->error('社区版不支持插件市场申请，请使用本地插件');
    }

    public function __call($method, $args)
    {
        return $this->error('社区版不支持插件市场接口：' . $method);
    }
}
