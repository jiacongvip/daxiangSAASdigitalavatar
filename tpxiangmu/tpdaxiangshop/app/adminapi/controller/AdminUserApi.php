<?php
namespace app\adminapi\controller;

use think\facade\Db;

class AdminUserApi extends Common
{
    public function detail()
    {
        $id = intval(input('get.id/d', 0));
        $info = [];
        if ($id > 0) {
            $info = Db::name('admin_user')->where('aid', $this->aid)->where('id', $id)->find();
            if (!$info) {
                return $this->error('账号不存在');
            }
        }

        $menudata = \app\admin\common\Menu::getScopedPermissionData($this->aid, $this->uid);

        if (intval($this->user['isadmin']) === 0) {
            if (intval($this->user['groupid']) === 0) {
                $groupList = [];
            } else {
                $thisgroup = Db::name('admin_user_group')->where('id', intval($this->user['groupid']))->find();
                if ($thisgroup) {
                    $groupList = Db::name('admin_user_group')
                        ->where('aid', $this->aid)
                        ->where('bid', $this->bid)
                        ->where("`sort`<{$thisgroup['sort']} or (`sort`={$thisgroup['sort']} and id>{$thisgroup['id']})")
                        ->field('id,name')
                        ->order('sort desc,id')
                        ->select()
                        ->toArray();
                } else {
                    $groupList = [];
                }
            }
        } else {
            $groupList = Db::name('admin_user_group')
                ->where('aid', $this->aid)
                ->where('bid', $this->bid)
                ->order('sort desc,id')
                ->field('id,name')
                ->select()
                ->toArray();
        }
        $groupList = array_merge($groupList, [['id' => 0, 'name' => '手动选择权限']]);

        if (!$info) {
            $info = [
                'id' => 0,
                'un' => '',
                'mid' => 0,
                'remark' => '',
                'addid' => 0,
                'groupid' => isset($groupList[0]) ? intval($groupList[0]['id']) : 0,
                'mdid' => intval($this->user['mdid'] ?? 0),
                'showtj' => 0,
                'auth_data' => [],
                'wxauth_data' => [],
                'notice_auth_data' => [],
                'hexiao_auth_data' => [],
            ];
        } else {
            $info['auth_data'] = !empty($info['auth_data']) ? (json_decode($info['auth_data'], true) ?: []) : [];
            $info['wxauth_data'] = !empty($info['wxauth_data']) ? (json_decode($info['wxauth_data'], true) ?: []) : [];
            $info['notice_auth_data'] = !empty($info['notice_auth_data']) ? (json_decode($info['notice_auth_data'], true) ?: []) : [];
            $info['hexiao_auth_data'] = !empty($info['hexiao_auth_data']) ? (json_decode($info['hexiao_auth_data'], true) ?: []) : [];
            $info['auth_data'] = \app\admin\common\Menu::sanitizeScopedPermissionSelection($this->aid, $this->uid, is_array($info['auth_data']) ? $info['auth_data'] : []);
            $info['groupid'] = intval($info['groupid'] ?? 0);
            $info['mdid'] = intval($info['mdid'] ?? 0);
            $info['showtj'] = intval($info['showtj'] ?? 0);
            $info['mid'] = intval($info['mid'] ?? 0);
            $info['addid'] = intval($info['addid'] ?? 0);
        }

        $userlist = [];
        if (intval($this->user['isadmin']) !== 0) {
            $userlist = Db::name('admin_user')
                ->where('aid', $this->aid)
                ->where('bid', $this->bid)
                ->order('id')
                ->field('id,un')
                ->select()
                ->toArray();
        }

        $mendianlist = Db::name('mendian')->where('aid', $this->aid)->where('bid', $this->bid)->select()->toArray();
        $memberInfo = null;
        if (intval($info['mid'] ?? 0) > 0) {
            $memberInfo = Db::name('member')
                ->where('aid', $this->aid)
                ->where('id', intval($info['mid']))
                ->field('id,nickname,realname,headimg,tel,mpopenid')
                ->find();
        }
        $thisuserWxauth = !empty($this->user['wxauth_data']) ? (json_decode($this->user['wxauth_data'], true) ?: []) : [];
        $thisuserNoticeAuth = !empty($this->user['notice_auth_data']) ? (json_decode($this->user['notice_auth_data'], true) ?: []) : [];
        $thisuserHexiaoAuth = !empty($this->user['hexiao_auth_data']) ? (json_decode($this->user['hexiao_auth_data'], true) ?: []) : [];

        return $this->success(array_merge([
            'info' => $info,
            'member_info' => $memberInfo,
            'groupList' => $groupList,
            'mendianlist' => $mendianlist,
            'userlist' => $userlist,
            'menudata' => $menudata,
            'thisuserid' => intval($this->user['id'] ?? 0),
            'thisuser_isadmin' => intval($this->user['isadmin'] ?? 0),
            'thisuser_auth_type' => intval($this->user['auth_type'] ?? 0),
            'thisuser_showtj' => intval(($this->user['showtj'] == 1 || intval($this->user['isadmin'] ?? 0) > 0) ? 1 : 0),
            'thisuser_mdid' => intval($this->user['mdid'] ?? 0),
            'thisuser_wxauth' => array_values($thisuserWxauth),
            'thisuser_notice_auth' => array_values($thisuserNoticeAuth),
            'thisuser_hexiao_auth' => array_values($thisuserHexiaoAuth),
            'restaurant_auth' => strpos(strval($this->user['wxauth_data'] ?? ''), 'restaurant') !== false ? 1 : 0,
            'bid' => intval($this->bid),
        ], \app\admin\common\PluginHexiaoRegistry::getReadyPayload(intval($this->aid))));
    }
}
