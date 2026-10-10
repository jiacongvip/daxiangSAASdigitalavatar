<?php

//decode by http://www.yunlu99.com/
namespace app\admin\controller;

class Base extends \app\BaseController
{
	public $aid;
	public $uid;
	public $user;
	public $mdid = 0;
	public $auth_data = "all";
	public $platform = "mp";
	public $xcxaid = 0;
	public function initialize()
	{
		$request = request();
		if ($request->controller() . "/" . $request->action() == "Backstage/index") {
			$this->checkauthkey();
		}

		// 自动同步已授权的插件菜单（仅站点）
		if(isset($this->aid) && $this->aid > 0) {
			$this->syncPluginMenus();
		}
	}

	/**
	 * 自动同步已授权的插件菜单
	 */
	protected function syncPluginMenus()
	{
		try {
			// 获取该站点的授权插件
			$licenses = \think\facade\Db::name('admin_plugin_license')
				->where('aid', $this->aid)
				->where('status', 1)
				->select()
				->toArray();

			foreach($licenses as $license) {
				// 检查是否过期
				if($license['expire_time'] > 0 && $license['expire_time'] < time()) {
					continue;
				}

				// 检查菜单是否已注册
				$exists = \think\facade\Db::name('admin_plugin_menu')
					->where('aid', $this->aid)
					->where('plugin_name', $license['plugin_name'])
					->count();

				if(!$exists) {
					// 自动注册菜单
					try {
						\app\admin\common\PluginMenu::registerFromConfig($license['plugin_name']);
					} catch(\Exception $e) {
						// 忽略错误（插件可能不存在）
					}
				}
			}
		} catch(\Exception $e) {
			// 忽略同步错误，不影响正常流程
		}
	}
	public function checkauthkey()
	{
		$domain = $_SERVER["HTTP_HOST"];
		return true;
		$client = new \GuzzleHttp\Client(["timeout" => 5, "verify" => false]);
		try {
			$response = $client->request("POST", "https://www.diandashop.com/index/install2/checkdomain", ["form_params" => ["domain" => $domain]]);
			$rs = $response->getBody()->getContents();
			$rs = json_decode($rs, true);
			if ($rs && $rs["status"] == 0) {
				exit($rs["msg"]);
			}
		} catch (\Throwable $e) {
		}
	}
}