<?php

namespace app\plugin\digitalavatar\controller;

use app\admin\common\System;
use app\adminapi\controller\Common;
use app\plugin\digitalavatar\common\DigitalAvatarCheckinService;
use app\plugin\digitalavatar\common\DigitalAvatarInviteService;
use app\plugin\digitalavatar\common\DigitalAvatarInspireService;
use app\plugin\digitalavatar\common\DigitalAvatarCreditService;
use app\plugin\digitalavatar\common\DigitalAvatarAdService;
use app\plugin\digitalavatar\common\DigitalAvatarImageSkillService;
use app\plugin\digitalavatar\common\DigitalAvatarCommerceService;
use app\plugin\digitalavatar\common\DigitalAvatarPortalDomain;
use app\plugin\digitalavatar\common\DigitalAvatarFinanceService;
use app\plugin\digitalavatar\common\DigitalAvatarOrgService;
use app\plugin\digitalavatar\common\DigitalAvatarImportService;
use app\plugin\digitalavatar\common\DigitalAvatarInstaller;
use app\plugin\digitalavatar\common\DigitalAvatarModelService;
use app\plugin\digitalavatar\common\DigitalAvatarProviderService;
use app\plugin\digitalavatar\common\DigitalAvatarService;
use app\plugin\digitalavatar\common\DigitalAvatarSidebarService;
use app\plugin\digitalavatar\common\DigitalAvatarTaskService;
use think\facade\Db;
use think\facade\Log;

class AdminapiDigitalAvatar extends Common
{
    private const AGENT_ACTIONS = [
        'getdashboard',
        'getsettings',
        'getpackagelist',
        'getredeemcodelist',
        'generateredeemcodes',
        'voidredeemcode',
        'getpackageorderlist',
        'getpackageorderdetail',
        'settlepackageorder',
        'getinvoicelist',
        'auditinvoice',
        'getmembercreditlist',
        'grantcredit',
        'savememberconcurrent',
        'getportalentry',
        'getcheckinlogs',
        'getinvitelogs',
    ];

    public function initialize()
    {
        parent::initialize();
        DigitalAvatarInstaller::ensureInstalled();
        DigitalAvatarInstaller::ensureSidebarSupport();
        DigitalAvatarCreditService::ensureSettings(intval($this->aid));
        DigitalAvatarInspireService::ensureSchema(intval($this->aid));

        if (intval($this->bid) > 0) {
            $action = strtolower(strval(request()->action()));
            $settings = DigitalAvatarCreditService::ensureSettings(intval($this->aid));
            if (intval($settings['agent_enabled'] ?? 0) !== 1) {
                echo json_encode(['status' => 0, 'msg' => '代理端尚未开启']);
                exit();
            }
            if (!in_array($action, self::AGENT_ACTIONS, true)) {
                echo json_encode(['status' => 0, 'msg' => '无操作权限']);
                exit();
            }
            if ($action === 'grantcredit' && intval($settings['agent_can_grant'] ?? 0) !== 1) {
                echo json_encode(['status' => 0, 'msg' => '代理端未开放额度调整']);
                exit();
            }
            if ($action === 'savememberconcurrent' && intval($settings['agent_can_grant'] ?? 0) !== 1) {
                echo json_encode(['status' => 0, 'msg' => '代理端未开放并发设置']);
                exit();
            }
            if (in_array($action, ['generateredeemcodes'], true) && intval($settings['agent_can_redeem'] ?? 0) !== 1) {
                echo json_encode(['status' => 0, 'msg' => '代理端未开放兑换码生成']);
                exit();
            }
        }
    }

    public function getDashboard()
    {
        return $this->respond('获取成功', '获取驾驶舱失败', function () {
            return DigitalAvatarService::getOverview(intval($this->aid), intval($this->bid));
        });
    }

    public function getSettings()
    {
        return $this->respond('获取成功', '获取设置失败', function () {
            return DigitalAvatarCreditService::formatSettings(
                DigitalAvatarCreditService::ensureSettings(intval($this->aid))
            );
        });
    }

    public function saveSettings()
    {
        return $this->respond('保存成功', '保存设置失败', function () {
            $info = input('post.info/a', []);
            $result = DigitalAvatarCreditService::saveSettings(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人系统设置');
            return $result;
        });
    }

    public function getProviderConfigs()
    {
        return $this->respond('获取成功', '获取提供商失败', function () {
            return ['list' => DigitalAvatarProviderService::listConfigs(intval($this->aid))];
        });
    }

    public function saveProviderConfig()
    {
        return $this->respond('保存成功', '保存提供商失败', function () {
            $info = input('post.info/a', []);
            return DigitalAvatarProviderService::saveConfig(intval($this->aid), is_array($info) ? $info : []);
        });
    }

    public function deleteProviderConfig()
    {
        return $this->respond('删除成功', '删除提供商失败', function () {
            DigitalAvatarProviderService::deleteConfig(intval($this->aid), intval(input('post.id/d', 0)));
            return [];
        });
    }

    public function testProviderConnection()
    {
        return $this->respond('连接成功', '测试提供商失败', function () {
            $providerKey = trim(strval(input('post.provider_key/s', '')));
            if ($providerKey === '') {
                throw new \RuntimeException('请传入提供商标识');
            }
            return DigitalAvatarProviderService::testConnection(intval($this->aid), $providerKey);
        });
    }

    public function getImportPreview()
    {
        return $this->respond('获取成功', '获取导入预览失败', function () {
            return DigitalAvatarImportService::getImportPreview(intval($this->aid));
        });
    }

    public function importConfiguredData()
    {
        return $this->respond('导入成功', '导入配置失败', function () {
            $result = DigitalAvatarImportService::importConfiguredData(intval($this->aid));
            System::plog('一键导入AI数字人接口配置');
            return $result;
        });
    }

    public function getModels()
    {
        return $this->respond('获取成功', '获取模型失败', function () {
            return ['list' => DigitalAvatarModelService::listModels(intval($this->aid))];
        });
    }

    public function getAds()
    {
        return $this->respond('获取成功', '获取首页广告失败', function () {
            return ['list' => DigitalAvatarAdService::list(intval($this->aid))];
        });
    }

    public function saveAd()
    {
        return $this->respond('保存成功', '保存首页广告失败', function () {
            $info = input('post.info/a', []);
            return DigitalAvatarAdService::save(intval($this->aid), is_array($info) ? $info : []);
        });
    }

    public function deleteAd()
    {
        return $this->respond('删除成功', '删除首页广告失败', function () {
            DigitalAvatarAdService::delete(intval($this->aid), intval(input('post.id/d', 0)));
            return [];
        });
    }

    public function getSidebarMenus()
    {
        try {
            return $this->success(
                DigitalAvatarSidebarService::adminConfig(intval($this->aid)),
                '获取成功'
            );
        } catch (\Throwable $e) {
            Log::error('获取侧栏菜单失败: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return $this->error('获取侧栏菜单失败：' . $e->getMessage());
        }
    }

    public function saveSidebarMenu()
    {
        return $this->respond('保存成功', '保存侧栏菜单失败', function () {
            $info = input('post.info/a', []);
            $result = DigitalAvatarSidebarService::saveItem(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人侧栏菜单');
            return $result;
        });
    }

    public function deleteSidebarMenu()
    {
        return $this->respond('删除成功', '删除侧栏菜单失败', function () {
            $result = DigitalAvatarSidebarService::deleteItem(intval($this->aid), strval(input('post.id/s', '')));
            System::plog('删除AI数字人侧栏菜单');
            return $result;
        });
    }

    public function sortSidebarMenus()
    {
        return $this->respond('排序成功', '保存侧栏菜单排序失败', function () {
            $ids = input('post.ids/a', []);
            return DigitalAvatarSidebarService::sortItems(intval($this->aid), is_array($ids) ? $ids : []);
        });
    }

    public function saveSidebarChatsEnabled()
    {
        return $this->respond('保存成功', '保存对话记录开关失败', function () {
            $enabled = intval(input('post.enabled/d', 0));
            $result = DigitalAvatarSidebarService::saveChatsEnabled(intval($this->aid), $enabled);
            System::plog($enabled === 1 ? '开启AI数字人对话记录菜单' : '关闭AI数字人对话记录菜单');
            return $result;
        });
    }

    public function restoreSidebarMenus()
    {
        return $this->respond('已恢复默认菜单', '恢复侧栏菜单失败', function () {
            $result = DigitalAvatarSidebarService::restoreDefaults(intval($this->aid));
            System::plog('恢复AI数字人默认侧栏菜单');
            return $result;
        });
    }

    public function getImageSkills()
    {
        return $this->respond('获取成功', '获取Agent作图技能失败', function () {
            return ['list' => DigitalAvatarImageSkillService::list(intval($this->aid))];
        });
    }

    public function saveImageSkill()
    {
        return $this->respond('保存成功', '保存Agent作图技能失败', function () {
            $info = input('post.info/a', []);
            $row = DigitalAvatarImageSkillService::save(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人Agent作图技能：' . strval($row['name'] ?? ''));
            return $row;
        });
    }

    public function deleteImageSkill()
    {
        return $this->respond('删除成功', '删除Agent作图技能失败', function () {
            $id = intval(input('post.id/d', 0));
            DigitalAvatarImageSkillService::delete(intval($this->aid), $id);
            System::plog('删除AI数字人Agent作图技能：' . $id);
            return [];
        });
    }

    public function saveModel()
    {
        return $this->respond('保存成功', '保存模型失败', function () {
            $info = input('post.info/a', []);
            return DigitalAvatarModelService::saveModel(intval($this->aid), is_array($info) ? $info : []);
        });
    }

    public function deleteModel()
    {
        return $this->respond('删除成功', '删除模型失败', function () {
            DigitalAvatarModelService::deleteModel(intval($this->aid), intval(input('post.id/d', 0)));
            return [];
        });
    }

    public function getChargeRules()
    {
        return $this->respond('获取成功', '获取计费规则失败', function () {
            $query = Db::name('digitalavatar_model_charge_rule')->where('aid', intval($this->aid));
            $taskType = trim(strval(input('post.task_type/s', '')));
            if ($taskType !== '') {
                $query->where('task_type', $taskType);
            }
            $list = $query->order('task_type asc,id asc')->select()->toArray();
            foreach ($list as &$row) {
                $row['unit_credit'] = DigitalAvatarCreditService::creditAmount($row['unit_credit'] ?? 0);
                $row['base_credit'] = DigitalAvatarCreditService::creditAmount($row['base_credit'] ?? 0);
            }
            unset($row);
            return [
                'list' => $list,
            ];
        });
    }

    public function saveChargeRule()
    {
        return $this->respond('保存成功', '保存计费规则失败', function () {
            $info = input('post.info/a', []);
            if (!is_array($info)) {
                $info = [];
            }
            $taskType = trim(strval($info['task_type'] ?? ''));
            $modelCode = trim(strval($info['model_code'] ?? ''));
            if ($taskType === '' || $modelCode === '') {
                throw new \RuntimeException('请填写任务类型和模型标识');
            }
            $data = [
                'aid' => intval($this->aid),
                'task_type' => mb_substr($taskType, 0, 60),
                'model_code' => mb_substr($modelCode, 0, 120),
                'match_mode' => strval($info['match_mode'] ?? 'platform_code') === 'model' ? 'model' : 'platform_code',
                'platform_code' => mb_substr(trim(strval($info['platform_code'] ?? '')), 0, 60),
                'api_model' => mb_substr(trim(strval($info['api_model'] ?? '')), 0, 120),
                'api_model_alias' => mb_substr(trim(strval($info['api_model_alias'] ?? ($info['alias'] ?? ''))), 0, 120),
                'rule_name' => mb_substr(trim(strval($info['rule_name'] ?? '')), 0, 120),
                'billing_mode' => in_array(
                    DigitalAvatarCreditService::normalizeBillingMode(strval($info['billing_mode'] ?? 'fixed')),
                    DigitalAvatarCreditService::allowedBillingModes(),
                    true
                )
                    ? DigitalAvatarCreditService::normalizeBillingMode(strval($info['billing_mode'] ?? 'fixed'))
                    : 'fixed',
                'unit_count' => max(1, intval($info['unit_count'] ?? 1)),
                'unit_credit' => DigitalAvatarCreditService::creditStore($info['unit_credit'] ?? 1),
                'base_credit' => DigitalAvatarCreditService::creditStore($info['base_credit'] ?? 0),
                'success_charge' => max(0, intval($info['success_charge'] ?? 1)),
                'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
                'update_time' => time(),
            ];
            $id = intval($info['id'] ?? 0);
            $exists = Db::name('digitalavatar_model_charge_rule')
                ->where('aid', intval($this->aid))
                ->where('task_type', $data['task_type'])
                ->where('model_code', $data['model_code'])
                ->where('api_model', $data['api_model'])
                ->when($id > 0, function ($query) use ($id) {
                    $query->where('id', '<>', $id);
                })
                ->find();
            if ($exists) {
                throw new \RuntimeException('该任务类型、模型与版本的计费规则已存在');
            }
            if ($id > 0) {
                $row = Db::name('digitalavatar_model_charge_rule')->where('aid', intval($this->aid))->where('id', $id)->find();
                if (!$row) {
                    throw new \RuntimeException('计费规则不存在');
                }
                Db::name('digitalavatar_model_charge_rule')->where('id', $id)->update($data);
            } else {
                $data['create_time'] = time();
                $id = intval(Db::name('digitalavatar_model_charge_rule')->insertGetId($data));
            }
            return Db::name('digitalavatar_model_charge_rule')->where('aid', intval($this->aid))->where('id', $id)->find() ?: [];
        });
    }

    public function getDailyQuotaRules()
    {
        return $this->respond('获取成功', '获取每日额度失败', function () {
            return [
                'list' => Db::name('digitalavatar_daily_quota_rule')
                    ->where('aid', intval($this->aid))
                    ->order('id asc')
                    ->select()
                    ->toArray(),
            ];
        });
    }

    public function saveDailyQuotaRule()
    {
        return $this->respond('保存成功', '保存每日额度失败', function () {
            $info = input('post.info/a', []);
            if (!is_array($info) || trim(strval($info['model_code'] ?? '')) === '') {
                throw new \RuntimeException('请填写模型标识');
            }
            $data = [
                'aid' => intval($this->aid),
                'model_code' => mb_substr(trim(strval($info['model_code'])), 0, 120),
                'quota_name' => mb_substr(trim(strval($info['quota_name'] ?? '')), 0, 120),
                'quota_count' => max(0, intval($info['quota_count'] ?? 0)),
                'carryover' => intval($info['carryover'] ?? 0) === 1 ? 1 : 0,
                'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
                'update_time' => time(),
            ];
            $id = intval($info['id'] ?? 0);
            $exists = Db::name('digitalavatar_daily_quota_rule')
                ->where('aid', intval($this->aid))
                ->where('model_code', $data['model_code'])
                ->when($id > 0, function ($query) use ($id) {
                    $query->where('id', '<>', $id);
                })
                ->find();
            if ($exists) {
                throw new \RuntimeException('该模型的每日额度规则已存在');
            }
            if ($id > 0) {
                $row = Db::name('digitalavatar_daily_quota_rule')->where('aid', intval($this->aid))->where('id', $id)->find();
                if (!$row) {
                    throw new \RuntimeException('每日额度规则不存在');
                }
                Db::name('digitalavatar_daily_quota_rule')->where('id', $id)->update($data);
            } else {
                $data['create_time'] = time();
                $id = intval(Db::name('digitalavatar_daily_quota_rule')->insertGetId($data));
            }
            return Db::name('digitalavatar_daily_quota_rule')->where('aid', intval($this->aid))->where('id', $id)->find() ?: [];
        });
    }

    public function getPublicAvatarList()
    {
        return $this->respond('获取成功', '获取公共数字人失败', function () {
            $result = $this->getAdminMediaList('digitalavatar_public_avatar', 'avatar');
            $result['list'] = DigitalAvatarService::formatAdminPublicAvatarList(intval($this->aid), $result['list']);
            return $result;
        });
    }

    public function searchMembers()
    {
        return $this->respond('获取成功', '搜索会员失败', function () {
            return [
                'list' => DigitalAvatarService::searchMembers(
                    intval($this->aid),
                    strval(input('post.keyword/s', input('keyword/s', ''))),
                    intval(input('post.limit/d', 20))
                ),
            ];
        });
    }

    public function savePublicAvatar()
    {
        return $this->respond('保存成功', '保存公共数字人失败', function () {
            $info = input('post.info/a', []);
            return DigitalAvatarService::savePublicAvatar(intval($this->aid), is_array($info) ? $info : []);
        });
    }

    public function deletePublicAvatar()
    {
        return $this->respond('删除成功', '删除公共数字人失败', function () {
            return $this->deletePublicMedia('digitalavatar_public_avatar');
        });
    }

    public function getPublicVoiceList()
    {
        return $this->respond('获取成功', '获取公共声音失败', function () {
            $result = $this->getAdminMediaList('digitalavatar_public_voice', 'voice');
            $result['list'] = DigitalAvatarService::formatAdminPublicAvatarList(intval($this->aid), $result['list']);
            return $result;
        });
    }

    public function savePublicVoice()
    {
        return $this->respond('保存成功', '保存公共声音失败', function () {
            $info = input('post.info/a', []);
            return DigitalAvatarService::savePublicVoice(intval($this->aid), is_array($info) ? $info : []);
        });
    }

    public function deletePublicVoice()
    {
        return $this->respond('删除成功', '删除公共声音失败', function () {
            return $this->deletePublicMedia('digitalavatar_public_voice');
        });
    }

    public function getPackageList()
    {
        return $this->respond('获取成功', '获取套餐失败', function () {
            $packageType = trim(strval(input('post.package_type/s', input('package_type/s', ''))));
            $page = max(1, intval(input('post.page/d', input('page/d', 1))));
            $limit = max(1, min(200, intval(input('post.limit/d', input('limit/d', 50)))));
            return DigitalAvatarService::listPackages(intval($this->aid), false, $packageType, $page, $limit);
        });
    }

    public function savePackage()
    {
        return $this->respond('保存成功', '保存套餐失败', function () {
            $info = input('post.info/a', []);
            if (!is_array($info)) {
                $info = [];
            }
            $result = DigitalAvatarService::savePackage(intval($this->aid), $info);
            $isNew = intval($info['id'] ?? 0) <= 0;
            $typeName = strval($info['package_type'] ?? 'credit') === 'boost' ? '加油包' : '会员套餐';
            System::plog(($isNew ? '新增' : '编辑') . 'AI数字人' . $typeName);
            return $result;
        });
    }

    public function deletePackage()
    {
        return $this->respond('删除成功', '删除套餐失败', function () {
            DigitalAvatarService::deletePackage(intval($this->aid), intval(input('post.id/d', 0)));
            System::plog('删除AI数字人套餐');
            return [];
        });
    }

    public function getMemberCreditList()
    {
        return $this->respond('获取成功', '获取会员额度失败', function () {
            $page = max(1, intval(input('post.page/d', 1)));
            $limit = max(1, min(100, intval(input('post.limit/d', 20))));
            $keyword = trim(strval(input('post.keyword/s', '')));
            $query = Db::name('digitalavatar_member_credit')->where('aid', intval($this->aid));
            if (intval($this->bid) > 0) {
                $query->where('bid', intval($this->bid));
            }
            if ($keyword !== '') {
                $memberIds = Db::name('member')
                    ->where('aid', intval($this->aid))
                    ->whereLike('nickname|tel', '%' . $keyword . '%')
                    ->column('id');
                if (empty($memberIds)) {
                    return ['list' => [], 'total' => 0, 'page' => $page, 'limit' => $limit];
                }
                $query->whereIn('member_id', $memberIds);
            }
            $total = intval((clone $query)->count());
            $list = $query->page($page, $limit)->order('id desc')->select()->toArray();
            $memberIds = array_values(array_unique(array_filter(array_map('intval', array_column($list, 'member_id')))));
            $members = [];
            if (!empty($memberIds)) {
                foreach (Db::name('member')->where('aid', intval($this->aid))->whereIn('id', $memberIds)->field('id,nickname,tel,headimg')->select()->toArray() as $member) {
                    $members[intval($member['id'])] = $member;
                }
            }
            $systemConcurrent = DigitalAvatarCreditService::systemConcurrentLimit(intval($this->aid));
            $runningMap = [];
            $queuedMap = [];
            if (!empty($memberIds)) {
                $taskQuery = Db::name('digitalavatar_task')
                    ->where('aid', intval($this->aid))
                    ->whereIn('member_id', $memberIds)
                    ->where('deleted_time', 0);
                if (intval($this->bid) > 0) {
                    $taskQuery->where('bid', intval($this->bid));
                }
                foreach ((clone $taskQuery)->where('status', 1)->field('member_id, count(*) as total')->group('member_id')->select()->toArray() as $row) {
                    $runningMap[intval($row['member_id'] ?? 0)] = intval($row['total'] ?? 0);
                }
                foreach ((clone $taskQuery)->whereIn('status', [0, 5])->field('member_id, count(*) as total')->group('member_id')->select()->toArray() as $row) {
                    $queuedMap[intval($row['member_id'] ?? 0)] = intval($row['total'] ?? 0);
                }
            }
            foreach ($list as &$item) {
                $member = $members[intval($item['member_id'] ?? 0)] ?? [];
                $customConcurrent = max(0, intval($item['max_concurrent_tasks'] ?? 0));
                $item['credit_total'] = DigitalAvatarCreditService::creditAmount($item['credit_total'] ?? 0);
                $item['credit_used'] = DigitalAvatarCreditService::creditAmount($item['credit_used'] ?? 0);
                $item['credit_balance'] = DigitalAvatarCreditService::creditAmount($item['credit_balance'] ?? 0);
                $item['credit_frozen'] = DigitalAvatarCreditService::creditAmount($item['credit_frozen'] ?? 0);
                $item['credit_available'] = DigitalAvatarCreditService::creditAmount(
                    DigitalAvatarCreditService::creditSub($item['credit_balance'], $item['credit_frozen'])
                );
                $item['member'] = $member;
                $item['max_concurrent_tasks'] = $customConcurrent;
                $item['system_max_concurrent_tasks'] = $systemConcurrent;
                $item['package_max_concurrent_tasks'] = DigitalAvatarCreditService::activePackageConcurrent(intval($this->aid), intval($this->bid), intval($item['member_id'] ?? 0));
                $item['effective_concurrent'] = DigitalAvatarCreditService::memberConcurrentLimit(intval($this->aid), intval($this->bid), intval($item['member_id'] ?? 0));
                $item['running_tasks'] = intval($runningMap[intval($item['member_id'] ?? 0)] ?? 0);
                $item['queued_tasks'] = intval($queuedMap[intval($item['member_id'] ?? 0)] ?? 0);
            }
            unset($item);
            return ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit];
        });
    }

    public function grantCredit()
    {
        return $this->respond('调整成功', '调整会员额度失败', function () {
            $info = input('post.info/a', []);
            return DigitalAvatarCreditService::grant(
                intval($this->aid),
                intval($this->bid) > 0 ? intval($this->bid) : 0,
                intval($info['member_id'] ?? $info['uid'] ?? 0),
                DigitalAvatarCreditService::creditSigned($info['credit_change'] ?? 0),
                trim(strval($info['remark'] ?? '后台调整额度')),
                intval($this->uid)
            );
        });
    }

    public function saveMemberConcurrent()
    {
        return $this->respond('保存成功', '保存会员并发失败', function () {
            $info = input('post.info/a', []);
            if (!is_array($info) || $info === []) {
                $info = input('post.');
            }
            $memberId = intval($info['member_id'] ?? $info['uid'] ?? 0);
            $maxConcurrent = intval($info['max_concurrent_tasks'] ?? 0);
            $result = DigitalAvatarCreditService::saveMemberConcurrent(
                intval($this->aid),
                intval($this->bid) > 0 ? intval($this->bid) : 0,
                $memberId,
                $maxConcurrent
            );
            System::plog('设置AI数字人会员并发：会员' . $memberId . ' 上限' . ($maxConcurrent > 0 ? $maxConcurrent : '跟随系统'));
            return $result;
        });
    }

    public function getTaskList()
    {
        return $this->respond('获取成功', '获取任务失败', function () {
            return DigitalAvatarTaskService::getAdminList(
                intval($this->aid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(100, intval(input('post.limit/d', 20)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', ''))),
                trim(strval(input('post.deleted/s', '')))
            );
        });
    }

    public function getTaskDetail()
    {
        return $this->respond('获取成功', '获取任务详情失败', function () {
            return DigitalAvatarTaskService::getDetail(intval($this->aid), intval(input('post.id/d', 0)));
        });
    }

    public function startTask()
    {
        return $this->respond('操作成功', '启动任务失败', function () {
            return DigitalAvatarTaskService::startTask(intval($this->aid), intval(input('post.id/d', 0)));
        });
    }

    public function completeTask()
    {
        return $this->respond('操作成功', '完成任务失败', function () {
            $result = input('post.result/a', []);
            return DigitalAvatarTaskService::completeTask(
                intval($this->aid),
                intval(input('post.id/d', 0)),
                is_array($result) ? $result : []
            );
        });
    }

    public function failTask()
    {
        return $this->respond('操作成功', '标记任务失败', function () {
            return DigitalAvatarTaskService::failTask(
                intval($this->aid),
                intval(input('post.id/d', 0)),
                trim(strval(input('post.error_msg/s', '任务执行失败')))
            );
        });
    }

    public function cancelTask()
    {
        return $this->respond('任务已取消', '取消任务失败', function () {
            return DigitalAvatarTaskService::cancelAdminTask(
                intval($this->aid),
                intval(input('post.id/d', 0))
            );
        });
    }

    public function retryTask()
    {
        return $this->respond('已重新提交任务', '重试任务失败', function () {
            return DigitalAvatarTaskService::retryAdminTask(
                intval($this->aid),
                intval(input('post.id/d', 0))
            );
        });
    }

    public function getCreditLogs()
    {
        return $this->respond('获取成功', '获取额度流水失败', function () {
            $page = max(1, intval(input('post.page/d', 1)));
            $limit = max(1, min(100, intval(input('post.limit/d', 20))));
            $memberId = intval(input('post.member_id/d', 0));
            $bid = intval($this->bid);
            return DigitalAvatarCreditService::listLogs(
                intval($this->aid),
                $bid,
                $memberId,
                $page,
                $limit,
                [
                    'task_type' => trim(strval(input('post.task_type/s', ''))),
                    'task_id' => trim(strval(input('post.task_id/s', ''))),
                ]
            );
        });
    }

    public function getCheckinSet()
    {
        return $this->respond('获取成功', '获取签到设置失败', function () {
            return DigitalAvatarCheckinService::ensureSet(intval($this->aid));
        });
    }

    public function saveCheckinSet()
    {
        return $this->respond('保存成功', '保存签到设置失败', function () {
            $info = input('post.info/a', []);
            $result = DigitalAvatarCheckinService::saveSet(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人签到设置');
            return $result;
        });
    }

    public function getCheckinLogs()
    {
        return $this->respond('获取成功', '获取签到记录失败', function () {
            return DigitalAvatarCheckinService::adminLogs(
                intval($this->aid),
                intval($this->bid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(100, intval(input('post.limit/d', 20)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.start_date/s', ''))),
                trim(strval(input('post.end_date/s', '')))
            );
        });
    }

    public function getInviteSet()
    {
        return $this->respond('获取成功', '获取拉新设置失败', function () {
            return DigitalAvatarInviteService::ensureSet(intval($this->aid));
        });
    }

    public function saveInviteSet()
    {
        return $this->respond('保存成功', '保存拉新设置失败', function () {
            $info = input('post.info/a', []);
            $result = DigitalAvatarInviteService::saveSet(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人拉新设置');
            return $result;
        });
    }

    public function getInviteLogs()
    {
        return $this->respond('获取成功', '获取拉新记录失败', function () {
            return DigitalAvatarInviteService::adminLogs(
                intval($this->aid),
                intval($this->bid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(100, intval(input('post.limit/d', 20)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.start_date/s', ''))),
                trim(strval(input('post.end_date/s', '')))
            );
        });
    }

    public function getInvoiceList()
    {
        return $this->respond('获取成功', '获取发票申请失败', function () {
            return DigitalAvatarCommerceService::getAdminInvoiceList(
                intval($this->aid),
                intval($this->bid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(100, intval(input('post.limit/d', 20)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', '')))
            );
        });
    }

    public function auditInvoice()
    {
        return $this->respond('处理成功', '处理发票失败', function () {
            $result = DigitalAvatarCommerceService::auditInvoice(
                intval($this->aid),
                intval($this->bid),
                intval(input('post.id/d', 0)),
                intval(input('post.status/d', 1)),
                trim(strval(input('post.admin_remark/s', ''))),
                intval($this->uid ?? 0),
                intval(input('post.allow_reapply/d', 0))
            );
            System::plog('审核AI数字人发票：' . strval($result['ordernum'] ?? '') . ' ' . strval($result['status_text'] ?? ''));
            return $result;
        });
    }

    public function getPortalEntry()
    {
        return $this->respond('获取成功', '获取用户端入口失败', function () {
            $settings = DigitalAvatarCreditService::ensureSettings(intval($this->aid));
            $path = trim(strval($settings['portal_path'] ?? '/portal/digitalavatar/')) ?: '/portal/digitalavatar/';
            $path = '/' . ltrim($path, '/');
            $separator = strpos($path, '?') === false ? '?' : '&';
            $url = rtrim(strval(request()->domain()), '/') . $path . $separator . 'aid=' . intval($this->aid);
            $loginPath = $path;
            if (strval($settings['login_style'] ?? 'niwa') !== 'classic') {
                $loginPath = rtrim($path, '/') . '/profile/';
            }
            $loginSeparator = strpos($loginPath, '?') === false ? '?' : '&';
            $loginUrl = rtrim(strval(request()->domain()), '/') . $loginPath . $loginSeparator . 'aid=' . intval($this->aid);
            $agentUrl = intval($this->bid) > 0 ? ($url . '&bid=' . intval($this->bid)) : $url;
            $currentUrl = intval($this->bid) > 0 ? $agentUrl : $url;
            $currentLoginUrl = intval($this->bid) > 0 ? ($loginUrl . '&bid=' . intval($this->bid)) : $loginUrl;
            $legacyUrl = $currentUrl;
            $domain = DigitalAvatarPortalDomain::storedHost($settings);
            $cleanUrl = DigitalAvatarPortalDomain::entryUrl($settings, intval($this->bid));
            $cleanSiteUrl = DigitalAvatarPortalDomain::entryUrl($settings, 0);
            if ($cleanUrl !== '') {
                $currentUrl = $cleanUrl;
                $currentLoginUrl = $cleanUrl;
                $url = $cleanSiteUrl !== '' ? $cleanSiteUrl : $cleanUrl;
                $agentUrl = intval($this->bid) > 0 ? $cleanUrl : $url;
            }
            return [
                'path' => $path,
                'url' => $currentUrl,
                'site_url' => $url,
                'login_url' => $currentLoginUrl,
                'workbench_url' => $currentUrl,
                'agent_url' => $agentUrl,
                'legacy_url' => $legacyUrl,
                'portal_domain' => $domain,
                'agent_enabled' => intval($settings['agent_enabled'] ?? 0) === 1 ? 1 : 0,
                'login_style' => strval($settings['login_style'] ?? 'niwa') === 'classic' ? 'classic' : 'niwa',
                'oem' => DigitalAvatarCommerceService::formatOem($settings),
                'route_mode' => 'hash',
            ];
        });
    }

    public function getRedeemCodeList()
    {
        return $this->respond('获取成功', '获取兑换码失败', function () {
            return DigitalAvatarCommerceService::getRedeemCodeList(
                intval($this->aid),
                intval($this->bid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(100, intval(input('post.limit/d', 20)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', ''))),
                trim(strval(input('post.batch_no/s', '')))
            );
        });
    }

    public function generateRedeemCodes()
    {
        return $this->respond('生成成功', '生成兑换码失败', function () {
            $info = input('post.info/a', []);
            $result = DigitalAvatarCommerceService::generateRedeemCodes(
                intval($this->aid),
                intval($this->bid),
                intval($this->uid),
                is_array($info) ? $info : []
            );
            System::plog('生成AI数字人兑换码 ' . intval($result['count'] ?? 0) . ' 张');
            return $result;
        });
    }

    public function voidRedeemCode()
    {
        return $this->respond('已作废', '作废兑换码失败', function () {
            $result = DigitalAvatarCommerceService::voidRedeemCode(
                intval($this->aid),
                intval($this->bid),
                intval(input('post.id/d', 0))
            );
            System::plog('作废AI数字人兑换码：' . strval($result['code'] ?? ''));
            return $result;
        });
    }

    public function getPackageOrderList()
    {
        return $this->respond('获取成功', '获取套餐订单失败', function () {
            return DigitalAvatarCommerceService::getAdminPackageOrderList(
                intval($this->aid),
                intval($this->bid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(200, intval(input('post.limit/d', 100)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', ''))),
                trim(strval(input('post.grant_status/s', ''))),
                trim(strval(input('post.start_date/s', ''))),
                trim(strval(input('post.end_date/s', ''))),
                trim(strval(input('post.package_type/s', ''))),
                trim(strval(input('post.pay_type/s', input('post.paytype/s', ''))))
            );
        });
    }

    public function getPackageOrderDetail()
    {
        return $this->respond('获取成功', '获取订单详情失败', function () {
            return DigitalAvatarCommerceService::getAdminPackageOrderDetail(
                intval($this->aid),
                intval($this->bid),
                intval(input('post.id/d', 0))
            );
        });
    }

    public function settlePackageOrder()
    {
        return $this->respond('补结算成功', '补结算失败', function () {
            $result = DigitalAvatarCommerceService::settlePackageOrder(
                intval($this->aid),
                intval($this->bid),
                intval(input('post.id/d', 0))
            );
            System::plog('补结算AI数字人套餐订单：' . strval($result['ordernum'] ?? ''));
            return $result;
        });
    }

    public function getWithdrawList()
    {
        return $this->respond('获取成功', '获取提现列表失败', function () {
            return DigitalAvatarFinanceService::listWithdraws(
                intval($this->aid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(200, intval(input('post.limit/d', 100)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', '')))
            );
        });
    }

    public function auditWithdraw()
    {
        return $this->respond('审核成功', '审核提现失败', function () {
            $result = DigitalAvatarFinanceService::auditWithdraw(
                intval($this->aid),
                intval(input('post.id/d', 0)),
                intval(input('post.status/d', 0)),
                trim(strval(input('post.audit_remark/s', input('post.remark/s', ''))))
            );
            System::plog('审核AI数字人提现：' . intval($result['id'] ?? 0) . ' ' . strval($result['status_text'] ?? ''));
            return $result;
        });
    }

    public function getCommissionLevelList()
    {
        return $this->respond('获取成功', '获取分佣等级失败', function () {
            return DigitalAvatarFinanceService::listCommissionLevels(
                intval($this->aid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(200, intval(input('post.limit/d', 50)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', '')))
            );
        });
    }

    public function saveCommissionLevel()
    {
        return $this->respond('保存成功', '保存分佣等级失败', function () {
            $info = input('post.info/a', []);
            if (!is_array($info) || $info === []) {
                $info = input('post.');
            }
            $result = DigitalAvatarFinanceService::saveCommissionLevel(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人分佣等级：' . strval($result['name'] ?? ''));
            return $result;
        });
    }

    public function deleteCommissionLevel()
    {
        return $this->respond('删除成功', '删除分佣等级失败', function () {
            $id = intval(input('post.id/d', 0));
            DigitalAvatarFinanceService::deleteCommissionLevel(intval($this->aid), $id);
            System::plog('删除AI数字人分佣等级：' . $id);
            return ['id' => $id];
        });
    }

    public function getAdminRoleList()
    {
        return $this->respond('获取成功', '获取管理员角色失败', function () {
            return DigitalAvatarOrgService::listRoles(
                intval($this->aid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(200, intval(input('post.limit/d', 50)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', '')))
            );
        });
    }

    public function saveAdminRole()
    {
        return $this->respond('保存成功', '保存管理员角色失败', function () {
            $info = input('post.info/a', []);
            if (!is_array($info) || $info === []) {
                $info = input('post.');
            }
            $result = DigitalAvatarOrgService::saveRole(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人管理员角色：' . strval($result['name'] ?? ''));
            return $result;
        });
    }

    public function deleteAdminRole()
    {
        return $this->respond('删除成功', '删除管理员角色失败', function () {
            $id = intval(input('post.id/d', 0));
            DigitalAvatarOrgService::deleteRole(intval($this->aid), $id);
            System::plog('删除AI数字人管理员角色：' . $id);
            return ['id' => $id];
        });
    }

    public function getAdminUserList()
    {
        return $this->respond('获取成功', '获取管理员失败', function () {
            return DigitalAvatarOrgService::listAdmins(
                intval($this->aid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(200, intval(input('post.limit/d', 50)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', ''))),
                intval(input('post.role_id/d', 0))
            );
        });
    }

    public function saveAdminUser()
    {
        return $this->respond('保存成功', '保存管理员失败', function () {
            $info = input('post.info/a', []);
            if (!is_array($info) || $info === []) {
                $info = input('post.');
            }
            $result = DigitalAvatarOrgService::saveAdmin(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人管理员：' . strval($result['username'] ?? ''));
            return $result;
        });
    }

    public function setAdminUserStatus()
    {
        return $this->respond('操作成功', '更新管理员状态失败', function () {
            $result = DigitalAvatarOrgService::setAdminStatus(
                intval($this->aid),
                intval(input('post.id/d', 0)),
                intval(input('post.status/d', 0))
            );
            System::plog('更新AI数字人管理员状态：' . strval($result['username'] ?? '') . ' ' . strval($result['status_text'] ?? ''));
            return $result;
        });
    }

    public function deleteAdminUser()
    {
        return $this->respond('删除成功', '删除管理员失败', function () {
            $id = intval(input('post.id/d', 0));
            DigitalAvatarOrgService::deleteAdmin(intval($this->aid), $id);
            System::plog('删除AI数字人管理员：' . $id);
            return ['id' => $id];
        });
    }

    public function getAgentList()
    {
        return $this->respond('获取成功', '获取代理列表失败', function () {
            return DigitalAvatarOrgService::listAgents(
                intval($this->aid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(200, intval(input('post.limit/d', 100)))),
                trim(strval(input('post.keyword/s', ''))),
                trim(strval(input('post.status/s', '')))
            );
        });
    }

    public function getAgentDetail()
    {
        return $this->respond('获取成功', '获取代理详情失败', function () {
            return DigitalAvatarOrgService::getAgentDetail(
                intval($this->aid),
                intval(input('post.id/d', 0))
            );
        });
    }

    public function saveAgent()
    {
        return $this->respond('保存成功', '保存代理失败', function () {
            $info = input('post.info/a', []);
            if (!is_array($info) || $info === []) {
                $info = input('post.');
            }
            $result = DigitalAvatarOrgService::saveAgent(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人代理：' . strval($result['name'] ?? ''));
            return $result;
        });
    }

    public function setAgentStatus()
    {
        return $this->respond('操作成功', '更新代理状态失败', function () {
            $result = DigitalAvatarOrgService::setAgentStatus(
                intval($this->aid),
                intval(input('post.id/d', 0)),
                intval(input('post.status/d', 0))
            );
            System::plog('更新AI数字人代理状态：' . strval($result['name'] ?? '') . ' ' . strval($result['status_text'] ?? ''));
            return $result;
        });
    }

    public function deleteAgent()
    {
        return $this->respond('删除成功', '删除代理失败', function () {
            $id = intval(input('post.id/d', 0));
            DigitalAvatarOrgService::deleteAgent(intval($this->aid), $id);
            System::plog('删除AI数字人代理：' . $id);
            return ['id' => $id];
        });
    }

    public function getInspireCategories()
    {
        return $this->respond('获取成功', '获取灵感分类失败', function () {
            return ['list' => DigitalAvatarInspireService::listCategories(intval($this->aid), false)];
        });
    }

    public function saveInspireCategory()
    {
        return $this->respond('保存成功', '保存灵感分类失败', function () {
            $info = input('post.info/a', []);
            $row = DigitalAvatarInspireService::saveCategory(intval($this->aid), is_array($info) ? $info : []);
            System::plog('保存AI数字人灵感分类：' . strval($row['name'] ?? ''));
            return $row;
        });
    }

    public function deleteInspireCategory()
    {
        return $this->respond('删除成功', '删除灵感分类失败', function () {
            $id = intval(input('post.id/d', 0));
            DigitalAvatarInspireService::deleteCategory(intval($this->aid), $id);
            System::plog('删除AI数字人灵感分类：' . $id);
            return [];
        });
    }

    public function getInspireList()
    {
        return $this->respond('获取成功', '获取灵感列表失败', function () {
            return DigitalAvatarInspireService::adminList(intval($this->aid), [
                'page' => input('post.page/d', 1),
                'limit' => input('post.limit/d', 20),
                'status' => input('post.status/s', ''),
                'category_id' => input('post.category_id/d', 0),
                'recommended' => input('post.recommended/s', ''),
                'keyword' => input('post.keyword/s', ''),
            ]);
        });
    }

    public function getInspireDetail()
    {
        return $this->respond('获取成功', '获取灵感详情失败', function () {
            return DigitalAvatarInspireService::getDetail(intval($this->aid), intval(input('post.id/d', 0)), 0, true);
        });
    }

    public function saveInspire()
    {
        return $this->respond('保存成功', '保存灵感失败', function () {
            $info = input('post.info/a', []);
            $row = DigitalAvatarInspireService::saveInspire(intval($this->aid), is_array($info) ? $info : [], intval($this->uid));
            System::plog('保存AI数字人灵感：' . intval($row['id'] ?? 0));
            return $row;
        });
    }

    public function auditInspire()
    {
        return $this->respond('审核成功', '审核灵感失败', function () {
            $id = intval(input('post.id/d', 0));
            $status = intval(input('post.status/d', 0));
            $remark = strval(input('post.audit_remark/s', ''));
            $row = DigitalAvatarInspireService::audit(intval($this->aid), $id, $status, $remark, intval($this->uid));
            System::plog(($status === 1 ? '通过' : '拒绝') . 'AI数字人灵感：' . $id);
            return $row;
        });
    }

    public function deleteInspire()
    {
        return $this->respond('删除成功', '删除灵感失败', function () {
            $id = intval(input('post.id/d', 0));
            DigitalAvatarInspireService::deleteInspire(intval($this->aid), $id);
            System::plog('删除AI数字人灵感：' . $id);
            return [];
        });
    }

    public function getInspireCommentList()
    {
        return $this->respond('获取成功', '获取评论列表失败', function () {
            return DigitalAvatarInspireService::adminCommentList(intval($this->aid), [
                'page' => input('post.page/d', 1),
                'limit' => input('post.limit/d', 20),
                'keyword' => input('post.keyword/s', ''),
                'inspire_id' => input('post.inspire_id/d', 0),
                'type' => input('post.type/s', ''),
            ]);
        });
    }

    public function saveInspireComment()
    {
        return $this->respond('保存成功', '保存评论失败', function () {
            $info = input('post.info/a', []);
            $row = DigitalAvatarInspireService::saveComment(intval($this->aid), is_array($info) ? $info : []);
            System::plog('编辑AI数字人灵感评论：' . intval($row['id'] ?? 0));
            return $row;
        });
    }

    public function deleteInspireComment()
    {
        return $this->respond('删除成功', '删除评论失败', function () {
            $id = intval(input('post.id/d', 0));
            DigitalAvatarInspireService::deleteComment(intval($this->aid), $id);
            System::plog('删除AI数字人灵感评论：' . $id);
            return ['id' => $id];
        });
    }

    private function getAdminMediaList(string $table, string $type): array
    {
        $page = max(1, intval(input('post.page/d', 1)));
        $limit = max(1, min(100, intval(input('post.limit/d', 20))));
        $query = Db::name($table)->where('aid', intval($this->aid));
        $status = trim(strval(input('post.status/s', '')));
        if ($status !== '' && is_numeric($status)) {
            $query->where('status', intval($status));
        }
        $keyword = trim(strval(input('post.keyword/s', '')));
        if ($keyword !== '') {
            $query->whereLike('name|provider_key|model_code', '%' . $keyword . '%');
        }
        $total = intval((clone $query)->count());
        return [
            'type' => $type,
            'list' => $query->page($page, $limit)->order('sort asc,id desc')->select()->toArray(),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private function deletePublicMedia(string $table): array
    {
        $id = intval(input('post.id/d', 0));
        if ($id <= 0) {
            throw new \RuntimeException('请传入资源ID');
        }
        $affected = Db::name($table)->where('aid', intval($this->aid))->where('id', $id)->delete();
        if ($affected <= 0) {
            throw new \RuntimeException('公共资源不存在');
        }
        return [];
    }

    private function respond(string $okMessage, string $failMessage, callable $callback)
    {
        try {
            return $this->success($callback(), $okMessage);
        } catch (\Throwable $e) {
            if (!($e instanceof \RuntimeException) && !($e instanceof \InvalidArgumentException) && !($e instanceof \LogicException)) {
                Log::error('AdminapiDigitalAvatar 系统异常: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                return $this->error($failMessage . '，系统繁忙，请稍后再试');
            }
            return $this->error($failMessage . '：' . $e->getMessage());
        }
    }
}
