<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarModelService
{
    private const TASK_TYPES = [
        'avatar_video' => '数字人视频',
        'voice_clone' => '声音克隆',
        'avatar_clone' => '数字人克隆',
        'audio' => '语音合成',
        'video' => '创意视频',
        'image' => '图片生成',
    ];

    public static function getTaskTypes(): array
    {
        return self::TASK_TYPES;
    }

    public static function listModels(int $aid, bool $activeOnly = false, string $taskType = ''): array
    {
        self::ensureNiwaCatalogModels($aid);
        $query = Db::name('digitalavatar_model')->where('aid', $aid);
        if ($activeOnly) {
            $query->where('status', 1);
        }
        if ($taskType !== '') {
            $query->where('task_type', $taskType);
        }

        $rows = $query->order('task_type asc,is_default desc,sort asc,id asc')->select()->toArray();
        $providerNames = self::providerNames($aid, $rows);
        $chargeRules = self::chargeRules($aid, $rows);
        foreach ($rows as &$row) {
            $chargeKey = self::chargeKey($row['task_type'] ?? '', $row['model_code'] ?? '');
            $row = self::formatModel($row, $providerNames, $chargeRules[$chargeKey] ?? []);
        }
        unset($row);

        return $rows;
    }

    public static function getPortalOptions(int $aid): array
    {
        self::ensureNiwaCatalogModels($aid);
        return array_map(function ($row) {
            $versionRules = self::portalVersionRules($row);
            $capabilities = DigitalAvatarNiwaCatalog::portalCapabilities($row);
            $capabilities = self::attachVersionParamField($capabilities, $row, $versionRules);
            $matchMode = strval($row['match_mode'] ?? 'platform_code') === 'model' ? 'model' : 'platform_code';
            $billing = self::portalBillingFromRules($row, $versionRules);
            return [
                'id' => intval($row['id'] ?? 0),
                'task_type' => strval($row['task_type'] ?? ''),
                'task_name' => strval($row['task_name'] ?? ''),
                'name' => strval($row['title'] ?? ''),
                'model_code' => strval($row['model_code'] ?? ''),
                'provider_key' => strval($row['provider_key'] ?? ''),
                'provider_name' => strval($row['provider_name'] ?? ''),
                'description' => strval($row['description'] ?? ''),
                'credit_cost' => DigitalAvatarCreditService::creditAmount($billing['credit_cost'] ?? 0),
                'billing_mode' => strval($billing['billing_mode'] ?? 'fixed'),
                'unit_count' => intval($billing['unit_count'] ?? 1),
                'unit_credit' => DigitalAvatarCreditService::creditAmount($billing['unit_credit'] ?? 0),
                'base_credit' => DigitalAvatarCreditService::creditAmount($billing['base_credit'] ?? 0),
                'charge_text' => strval($billing['charge_text'] ?? ''),
                'match_mode' => $matchMode,
                'platform_code' => strval($row['platform_code'] ?? ''),
                'api_model' => strval($billing['api_model'] ?? ($row['api_model'] ?? '')),
                'version_rules' => $versionRules,
                'is_default' => intval($row['is_default'] ?? 0),
                'capabilities' => $capabilities,
            ];
        }, self::listModels($aid, true));
    }

    public static function saveModel(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $taskType = trim(strval($info['task_type'] ?? ''));
        $title = trim(strval($info['title'] ?? ''));
        $modelCode = trim(strval($info['model_code'] ?? ''));
        $providerKey = strtolower(trim(strval($info['provider_key'] ?? '')));

        if (!isset(self::TASK_TYPES[$taskType])) {
            throw new \RuntimeException('请选择正确的任务类型');
        }
        if ($title === '') {
            throw new \RuntimeException('请填写模型名称');
        }
        if ($modelCode === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,119}$/', $modelCode)) {
            throw new \RuntimeException('模型编码只能包含字母、数字、点、下划线、连字符和冒号');
        }
        if ($providerKey === '') {
            throw new \RuntimeException('请选择提供商');
        }

        $provider = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('provider_key', $providerKey)
            ->find();
        if (!$provider) {
            throw new \RuntimeException('所选提供商不存在，请先完成提供商配置');
        }

        $duplicate = Db::name('digitalavatar_model')
            ->where('aid', $aid)
            ->where('task_type', $taskType)
            ->where('model_code', $modelCode)
            ->when($id > 0, function ($query) use ($id) {
                $query->where('id', '<>', $id);
            })
            ->find();
        if ($duplicate) {
            throw new \RuntimeException('同一任务类型下的模型编码不能重复');
        }

        $now = time();
        $data = [
            'aid' => $aid,
            'task_type' => $taskType,
            'title' => mb_substr($title, 0, 120),
            'model_code' => $modelCode,
            'provider_key' => $providerKey,
            'description' => mb_substr(trim(strval($info['description'] ?? '')), 0, 500),
            'config_json' => self::encodeConfig($info['config'] ?? []),
            'sort' => intval($info['sort'] ?? 0),
            'is_default' => intval($info['is_default'] ?? 0) === 1 ? 1 : 0,
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'update_time' => $now,
        ];

        $hasCreditCost = array_key_exists('credit_cost', $info);
        $creditCost = DigitalAvatarCreditService::creditAmount($info['credit_cost'] ?? 0);

        Db::transaction(function () use ($aid, &$id, &$data, $now, $hasCreditCost, $info) {
            if ($id > 0) {
                $exists = Db::name('digitalavatar_model')->where('aid', $aid)->where('id', $id)->find();
                if (!$exists) {
                    throw new \RuntimeException('模型不存在');
                }
                Db::name('digitalavatar_model')->where('id', $id)->update($data);
            } else {
                $data['create_time'] = $now;
                $id = intval(Db::name('digitalavatar_model')->insertGetId($data));
            }

            if (intval($data['is_default']) === 1) {
                Db::name('digitalavatar_model')
                    ->where('aid', $aid)
                    ->where('task_type', strval($data['task_type']))
                    ->where('id', '<>', $id)
                    ->update(['is_default' => 0, 'update_time' => $now]);
            }

            if ($hasCreditCost || array_key_exists('version_rules', $info) || array_key_exists('match_mode', $info) || array_key_exists('billing_mode', $info)) {
                self::saveModelChargeRules($aid, $id, $data, $info, $now);
            }
            $data['id'] = $id;
        });

        $row = Db::name('digitalavatar_model')->where('aid', $aid)->where('id', intval($data['id'] ?? 0))->find();
        $chargeKey = self::chargeKey($row['task_type'] ?? '', $row['model_code'] ?? '');
        $chargeRules = self::chargeRules($aid, [$row ?: []]);
        return self::formatModel(
            $row ?: [],
            [strval($providerKey) => strval($provider['provider_name'] ?? $providerKey)],
            $chargeRules[$chargeKey] ?? []
        );
    }

    private static function saveModelChargeRules(int $aid, int $modelId, array $data, array $info, int $now): void
    {
        $taskType = strval($data['task_type'] ?? '');
        $modelCode = strval($data['model_code'] ?? '');
        if ($taskType === '' || $modelCode === '') {
            return;
        }
        $config = self::decodeConfig($data['config_json'] ?? ($info['config'] ?? []));
        $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, $config, ['task_type' => $taskType]);
        $platformCode = trim(strval($info['platform_code'] ?? ($spec['platform_code'] ?? $modelCode)));
        $matchMode = strval($info['match_mode'] ?? '') === 'model' ? 'model' : 'platform_code';
        $versionRules = $info['version_rules'] ?? ($info['charge_rules'] ?? []);
        if (is_string($versionRules)) {
            $decoded = json_decode($versionRules, true);
            $versionRules = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($versionRules)) {
            $versionRules = [];
        }

        $rows = [];
        if ($versionRules !== []) {
            foreach ($versionRules as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $apiModel = trim(strval($item['api_model'] ?? ($item['model'] ?? '')));
                if ($matchMode === 'model' && $apiModel === '') {
                    continue;
                }
                if ($matchMode === 'platform_code') {
                    $apiModel = '';
                }
                $mode = DigitalAvatarCreditService::normalizeBillingMode(strval($item['billing_mode'] ?? ($info['billing_mode'] ?? 'fixed')));
                $alias = mb_substr(trim(strval($item['api_model_alias'] ?? ($item['alias'] ?? ''))), 0, 120);
                $rows[$apiModel] = [
                    'api_model' => $apiModel,
                    'api_model_alias' => $alias,
                    'rule_name' => mb_substr(trim(strval($item['rule_name'] ?? '')) ?: (strval($data['title']) . ($apiModel !== '' ? (' ' . $apiModel) : '') . '扣点'), 0, 120),
                    'billing_mode' => $mode,
                    'unit_count' => max(1, intval($item['unit_count'] ?? 1)),
                    'unit_credit' => DigitalAvatarCreditService::creditAmount($item['unit_credit'] ?? ($item['credit_cost'] ?? 0)),
                    'base_credit' => DigitalAvatarCreditService::creditAmount($item['base_credit'] ?? 0),
                    'success_charge' => max(0, intval($item['success_charge'] ?? 1)),
                    'status' => intval($item['status'] ?? 1) === 0 ? 0 : 1,
                ];
                if ($matchMode === 'platform_code') {
                    break;
                }
            }
        }
        if ($rows === []) {
            $mode = DigitalAvatarCreditService::normalizeBillingMode(strval($info['billing_mode'] ?? 'fixed'));
            $apiModel = $matchMode === 'model' ? trim(strval($info['api_model'] ?? ($spec['model'] ?? ''))) : '';
            $rows[$apiModel] = [
                'api_model' => $apiModel,
                'api_model_alias' => mb_substr(trim(strval($info['api_model_alias'] ?? ($info['alias'] ?? ''))), 0, 120),
                'rule_name' => mb_substr(strval($data['title']) . '扣点', 0, 120),
                'billing_mode' => $mode,
                'unit_count' => max(1, intval($info['unit_count'] ?? 1)),
                'unit_credit' => DigitalAvatarCreditService::creditAmount($info['unit_credit'] ?? ($info['credit_cost'] ?? 0)),
                'base_credit' => DigitalAvatarCreditService::creditAmount($info['base_credit'] ?? 0),
                'success_charge' => max(0, intval($info['success_charge'] ?? 1)),
                'status' => 1,
            ];
        }

        $keepApi = [];
        foreach ($rows as $row) {
            $keepApi[] = strval($row['api_model']);
            $exists = Db::name('digitalavatar_model_charge_rule')
                ->where('aid', $aid)
                ->where('task_type', $taskType)
                ->where('model_code', $modelCode)
                ->where('api_model', strval($row['api_model']))
                ->find();
            $payload = [
                'aid' => $aid,
                'task_type' => $taskType,
                'model_code' => $modelCode,
                'match_mode' => $matchMode,
                'platform_code' => mb_substr($platformCode, 0, 60),
                'api_model' => mb_substr(strval($row['api_model']), 0, 120),
                'api_model_alias' => mb_substr(strval($row['api_model_alias'] ?? ''), 0, 120),
                'rule_name' => $row['rule_name'],
                'billing_mode' => $row['billing_mode'],
                'unit_count' => $row['unit_count'],
                'unit_credit' => DigitalAvatarCreditService::creditStore($row['unit_credit']),
                'base_credit' => DigitalAvatarCreditService::creditStore($row['base_credit']),
                'success_charge' => $row['success_charge'],
                'status' => $row['status'],
                'update_time' => $now,
            ];
            if ($exists) {
                Db::name('digitalavatar_model_charge_rule')->where('id', intval($exists['id']))->update($payload);
            } else {
                $payload['create_time'] = $now;
                Db::name('digitalavatar_model_charge_rule')->insert($payload);
            }
        }
        $obsolete = Db::name('digitalavatar_model_charge_rule')
            ->where('aid', $aid)
            ->where('task_type', $taskType)
            ->where('model_code', $modelCode)
            ->select()
            ->toArray();
        foreach ($obsolete as $old) {
            if (!in_array(strval($old['api_model'] ?? ''), $keepApi, true)) {
                Db::name('digitalavatar_model_charge_rule')->where('id', intval($old['id']))->delete();
            }
        }
    }

    public static function deleteModel(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请传入模型ID');
        }
        $model = Db::name('digitalavatar_model')->where('aid', $aid)->where('id', $id)->find();
        if (!$model) {
            throw new \RuntimeException('模型不存在');
        }
        Db::name('digitalavatar_model')->where('id', $id)->delete();
        Db::name('digitalavatar_model_charge_rule')
            ->where('aid', $aid)
            ->where('task_type', strval($model['task_type'] ?? ''))
            ->where('model_code', strval($model['model_code'] ?? ''))
            ->delete();
    }

    public static function resolveForTask(int $aid, string $taskType, string $modelCode = ''): array
    {
        if (!isset(self::TASK_TYPES[$taskType])) {
            throw new \RuntimeException('任务类型不支持');
        }

        $query = Db::name('digitalavatar_model')
            ->where('aid', $aid)
            ->where('task_type', $taskType)
            ->where('status', 1);
        if ($modelCode !== '') {
            $query->where('model_code', $modelCode);
        } else {
            $query->order('is_default desc,sort asc,id asc');
        }
        $model = $query->find();
        if (!$model) {
            $suffix = $modelCode !== '' ? '或已停用' : '';
            throw new \RuntimeException(self::TASK_TYPES[$taskType] . '暂未配置可用模型' . $suffix);
        }

        $provider = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('provider_key', strval($model['provider_key']))
            ->where('status', 1)
            ->find();
        if (!$provider) {
            throw new \RuntimeException('模型绑定的提供商未配置或已停用');
        }

        return [
            'id' => intval($model['id']),
            'task_type' => strval($model['task_type']),
            'title' => strval($model['title']),
            'model_code' => strval($model['model_code']),
            'provider_key' => strval($model['provider_key']),
            'provider_name' => strval($provider['provider_name'] ?? $model['provider_key']),
            'config' => self::decodeConfig($model['config_json'] ?? ''),
        ];
    }

    public static function resolveById(int $aid, int $id, string $taskType = ''): array
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择图片模型');
        }
        $query = Db::name('digitalavatar_model')
            ->where('aid', $aid)
            ->where('id', $id)
            ->where('status', 1);
        if ($taskType !== '') {
            $query->where('task_type', $taskType);
        }
        $model = $query->find();
        if (!$model) {
            throw new \RuntimeException('所选图片模型不存在或已停用');
        }
        return self::resolveForTask($aid, strval($model['task_type'] ?? ''), strval($model['model_code'] ?? ''));
    }

    private static function formatModel(array $row, array $providerNames = [], $chargeRule = []): array
    {
        $providerKey = strval($row['provider_key'] ?? '');
        $rules = [];
        if (isset($chargeRule[0]) && is_array($chargeRule[0])) {
            $rules = $chargeRule;
        } elseif ($chargeRule !== []) {
            $rules = [$chargeRule];
        }
        $primary = $rules[0] ?? [];
        foreach ($rules as $item) {
            if (trim(strval($item['api_model'] ?? '')) === '') {
                $primary = $item;
                break;
            }
        }
        $config = self::decodeConfig($row['config_json'] ?? '');
        $spec = DigitalAvatarNiwaCatalog::resolve(strval($row['model_code'] ?? ''), $config, ['task_type' => strval($row['task_type'] ?? '')]);
        $versionRules = [];
        $texts = [];
        foreach ($rules as $item) {
            $alias = trim(strval($item['api_model_alias'] ?? ($item['alias'] ?? '')));
            $apiModel = strval($item['api_model'] ?? '');
            $versionRules[] = [
                'id' => intval($item['id'] ?? 0),
                'api_model' => $apiModel,
                'api_model_alias' => $alias,
                'alias' => $alias !== '' ? $alias : $apiModel,
                'match_mode' => strval($item['match_mode'] ?? 'platform_code'),
                'billing_mode' => DigitalAvatarCreditService::normalizeBillingMode(strval($item['billing_mode'] ?? 'fixed')),
                'unit_count' => max(1, intval($item['unit_count'] ?? 1)),
                'unit_credit' => DigitalAvatarCreditService::creditAmount($item['unit_credit'] ?? 0),
                'base_credit' => DigitalAvatarCreditService::creditAmount($item['base_credit'] ?? 0),
                'success_charge' => max(0, intval($item['success_charge'] ?? 1)),
                'status' => intval($item['status'] ?? 1),
                'charge_text' => DigitalAvatarCreditService::formatRuleText($item),
            ];
            $label = trim(strval($item['api_model_alias'] ?? '')) ?: trim(strval($item['api_model'] ?? ''));
            $texts[] = ($label !== '' ? $label . '：' : '') . DigitalAvatarCreditService::formatRuleText($item);
        }
        $matchMode = strval($primary['match_mode'] ?? 'platform_code') === 'model' ? 'model' : 'platform_code';
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'task_type' => strval($row['task_type'] ?? ''),
            'task_name' => self::TASK_TYPES[strval($row['task_type'] ?? '')] ?? strval($row['task_type'] ?? ''),
            'title' => strval($row['title'] ?? ''),
            'model_code' => strval($row['model_code'] ?? ''),
            'provider_key' => $providerKey,
            'provider_name' => strval($providerNames[$providerKey] ?? $providerKey),
            'description' => strval($row['description'] ?? ''),
            'credit_cost' => DigitalAvatarCreditService::creditAmount(
                DigitalAvatarCreditService::creditAdd($primary['base_credit'] ?? 0, $primary['unit_credit'] ?? 0)
            ),
            'billing_mode' => DigitalAvatarCreditService::normalizeBillingMode(strval($primary['billing_mode'] ?? 'fixed')),
            'unit_count' => max(1, intval($primary['unit_count'] ?? 1)),
            'unit_credit' => DigitalAvatarCreditService::creditAmount($primary['unit_credit'] ?? 0),
            'base_credit' => DigitalAvatarCreditService::creditAmount($primary['base_credit'] ?? 0),
            'match_mode' => $matchMode,
            'platform_code' => strval($primary['platform_code'] ?? ($spec['platform_code'] ?? '')),
            'api_model' => strval($primary['api_model'] ?? ($spec['model'] ?? '')),
            'version_rules' => $versionRules,
            'charge_text' => $texts !== [] ? implode('；', $texts) : '',
            'config' => $config,
            'sort' => intval($row['sort'] ?? 0),
            'is_default' => intval($row['is_default'] ?? 0),
            'status' => intval($row['status'] ?? 0),
            'create_time' => intval($row['create_time'] ?? 0),
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    private static function portalVersionRules(array $row): array
    {
        $rules = is_array($row['version_rules'] ?? null) ? $row['version_rules'] : [];
        $out = [];
        foreach ($rules as $item) {
            if (!is_array($item) || intval($item['status'] ?? 1) === 0) {
                continue;
            }
            $apiModel = trim(strval($item['api_model'] ?? ''));
            $alias = trim(strval($item['api_model_alias'] ?? ($item['alias'] ?? '')));
            $out[] = [
                'api_model' => $apiModel,
                'api_model_alias' => $alias,
                'alias' => $alias !== '' ? $alias : $apiModel,
                'billing_mode' => DigitalAvatarCreditService::normalizeBillingMode(strval($item['billing_mode'] ?? 'fixed')),
                'unit_count' => max(1, intval($item['unit_count'] ?? 1)),
                'unit_credit' => DigitalAvatarCreditService::creditAmount($item['unit_credit'] ?? 0),
                'base_credit' => DigitalAvatarCreditService::creditAmount($item['base_credit'] ?? 0),
                'charge_text' => strval($item['charge_text'] ?? DigitalAvatarCreditService::formatRuleText($item)),
                'status' => 1,
            ];
        }
        return $out;
    }

    private static function portalBillingFromRules(array $row, array $versionRules): array
    {
        $matchMode = strval($row['match_mode'] ?? 'platform_code') === 'model' ? 'model' : 'platform_code';
        $source = $row;
        if ($matchMode === 'model') {
            foreach ($versionRules as $rule) {
                if (trim(strval($rule['api_model'] ?? '')) !== '') {
                    $source = $rule;
                    break;
                }
            }
        }
        $unitCredit = DigitalAvatarCreditService::creditAmount($source['unit_credit'] ?? ($row['unit_credit'] ?? 0));
        $baseCredit = DigitalAvatarCreditService::creditAmount($source['base_credit'] ?? ($row['base_credit'] ?? 0));
        return [
            'api_model' => strval($source['api_model'] ?? ($row['api_model'] ?? '')),
            'billing_mode' => DigitalAvatarCreditService::normalizeBillingMode(strval($source['billing_mode'] ?? ($row['billing_mode'] ?? 'fixed'))),
            'unit_count' => max(1, intval($source['unit_count'] ?? ($row['unit_count'] ?? 1))),
            'unit_credit' => $unitCredit,
            'base_credit' => $baseCredit,
            'credit_cost' => DigitalAvatarCreditService::creditAdd($baseCredit, $unitCredit),
            'charge_text' => strval($source['charge_text'] ?? ($row['charge_text'] ?? '')),
        ];
    }

    private static function attachVersionParamField(array $capabilities, array $row, array $versionRules): array
    {
        if (strval($row['match_mode'] ?? '') !== 'model') {
            return $capabilities;
        }
        $options = [];
        foreach ($versionRules as $rule) {
            $value = trim(strval($rule['api_model'] ?? ''));
            if ($value === '') {
                continue;
            }
            $options[] = [
                'value' => $value,
                'label' => trim(strval($rule['alias'] ?? '')) ?: $value,
            ];
        }
        if ($options === []) {
            return $capabilities;
        }
        $field = [
            'key' => 'api_model',
            'label' => '模型版本',
            'required' => 1,
            'type' => 'select',
            'min' => 0,
            'max' => 0,
            'step' => 0,
            'default' => $options[0]['value'],
            'hint' => '按所选接口 model 版本计费',
            'options' => $options,
        ];
        $fields = is_array($capabilities['param_fields'] ?? null) ? $capabilities['param_fields'] : [];
        $filtered = [];
        foreach ($fields as $item) {
            if (trim(strval(is_array($item) ? ($item['key'] ?? '') : '')) === 'api_model') {
                continue;
            }
            $filtered[] = $item;
        }
        array_unshift($filtered, $field);
        $capabilities['param_fields'] = array_values($filtered);
        $params = is_array($capabilities['params'] ?? null) ? $capabilities['params'] : [];
        if (!in_array('api_model', $params, true)) {
            array_unshift($params, 'api_model');
        }
        $capabilities['params'] = array_values($params);
        $required = is_array($capabilities['required_params'] ?? null) ? $capabilities['required_params'] : [];
        if (!in_array('api_model', $required, true)) {
            array_unshift($required, 'api_model');
        }
        $capabilities['required_params'] = array_values($required);
        return $capabilities;
    }

    private static function providerNames(int $aid, array $models): array
    {
        $keys = array_values(array_unique(array_filter(array_map(function ($row) {
            return strval($row['provider_key'] ?? '');
        }, $models))));
        if (empty($keys)) {
            return [];
        }
        $rows = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->whereIn('provider_key', $keys)
            ->field('provider_key,provider_name')
            ->select()
            ->toArray();
        $result = [];
        foreach ($rows as $row) {
            $result[strval($row['provider_key'])] = strval($row['provider_name'] ?? $row['provider_key']);
        }
        return $result;
    }

    private static function chargeKey($taskType, $modelCode): string
    {
        return strval($taskType) . '|' . strval($modelCode);
    }

    private static function chargeRules(int $aid, array $models): array
    {
        $pairs = [];
        $taskTypes = [];
        foreach ($models as $model) {
            $taskType = trim(strval($model['task_type'] ?? ''));
            $modelCode = trim(strval($model['model_code'] ?? ''));
            if ($taskType !== '' && $modelCode !== '') {
                $pairs[] = self::chargeKey($taskType, $modelCode);
                $taskTypes[] = $taskType;
            }
        }
        if (empty($pairs)) {
            return [];
        }

        $rules = Db::name('digitalavatar_model_charge_rule')
            ->where('aid', $aid)
            ->where('status', 1)
            ->whereIn('task_type', array_values(array_unique($taskTypes)))
            ->select()
            ->toArray();
        $result = [];
        $wildcard = [];
        foreach ($rules as $rule) {
            $modelCode = trim(strval($rule['model_code'] ?? ''));
            $taskType = trim(strval($rule['task_type'] ?? ''));
            if ($modelCode === '*') {
                $wildcard[$taskType] = $rule;
                continue;
            }
            $key = self::chargeKey($taskType, $modelCode);
            if (in_array($key, $pairs, true)) {
                if (!isset($result[$key])) {
                    $result[$key] = [];
                }
                $result[$key][] = $rule;
            }
        }
        foreach ($models as $model) {
            $key = self::chargeKey($model['task_type'] ?? '', $model['model_code'] ?? '');
            if (!isset($result[$key])) {
                $taskType = trim(strval($model['task_type'] ?? ''));
                if ($taskType !== '' && isset($wildcard[$taskType])) {
                    $result[$key] = [$wildcard[$taskType]];
                }
            }
        }
        return $result;
    }

    public static function ensureNiwaCatalogModels(int $aid): void
    {
        static $ensured = [];
        if ($aid <= 0 || isset($ensured[$aid])) {
            return;
        }
        $ensured[$aid] = true;
        self::syncOfficialNiwaModels($aid, true);
        self::fillEmptyVoiceCloneConfigs($aid);
    }

    public static function syncOfficialNiwaModels(int $aid, bool $createMissing = true): void
    {
        $providerKey = self::resolveNiwaProviderKey($aid);
        if ($aid <= 0 || $providerKey === '') {
            return;
        }
        foreach (DigitalAvatarNiwaCatalog::seedTemplates($providerKey) as $template) {
            $exists = Db::name('digitalavatar_model')
                ->where('aid', $aid)
                ->where('task_type', strval($template['task_type']))
                ->where('model_code', strval($template['model_code']))
                ->find();
            if ($exists) {
                $config = self::decodeConfig($exists['config_json'] ?? '');
                $official = is_array($template['config'] ?? null) ? $template['config'] : [];
                $merged = array_merge($official, $config);
                if (self::encodeConfig($merged) !== self::encodeConfig($config)) {
                    Db::name('digitalavatar_model')->where('id', intval($exists['id']))->update([
                        'config_json' => self::encodeConfig($merged),
                        'update_time' => time(),
                    ]);
                }
                continue;
            }
            $hasSibling = Db::name('digitalavatar_model')
                ->where('aid', $aid)
                ->where('model_code', strval($template['model_code']))
                ->find();
            if (!$createMissing && !$hasSibling) {
                continue;
            }
            try {
                self::saveModel($aid, $template);
            } catch (\Throwable $e) {
                continue;
            }
        }

        $legacyCodes = [
            'niwaai-voice-clone',
            'niwaai-professional-clone',
            'niwaai-minimax-clone',
            'niwaai-tts',
            'niwaai-professional',
            'niwaai-minimax',
            'niwaai-tts666',
            'niwaai-3d',
            'niwaai-avatar-clone',
            'niwaai-3d-clone',
            'niwaai-basic-clone',
            'niwaai-avatar-video',
        ];
        Db::name('digitalavatar_model')
            ->where('aid', $aid)
            ->whereIn('model_code', $legacyCodes)
            ->update(['status' => 0, 'is_default' => 0, 'update_time' => time()]);
    }

    public static function fillEmptyVoiceCloneConfigs(int $aid): void
    {
        if ($aid <= 0) {
            return;
        }
        $rows = Db::name('digitalavatar_model')
            ->where('aid', $aid)
            ->where('task_type', 'voice_clone')
            ->select()
            ->toArray();
        $now = time();
        foreach ($rows as $row) {
            $config = self::decodeConfig($row['config_json'] ?? '');
            if (trim(strval($config['voice_service_code'] ?? '')) !== '') {
                continue;
            }
            $filled = self::defaultVoiceCloneConfig(strval($row['model_code'] ?? ''));
            if ($filled === []) {
                continue;
            }
            Db::name('digitalavatar_model')->where('id', intval($row['id']))->update([
                'config_json' => self::encodeConfig(array_merge($filled, $config)),
                'update_time' => $now,
            ]);
        }
    }

    public static function defaultVoiceCloneConfig(string $modelCode): array
    {
        $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, [], ['task_type' => 'voice_clone']);
        return DigitalAvatarNiwaCatalog::configOf(strval($spec['family'] ?? 'spro'));
    }

    private static function resolveNiwaProviderKey(int $aid): string
    {
        $rows = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('status', 1)
            ->order('id asc')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $extra = self::decodeConfig($row['extra_json'] ?? '');
            $protocol = strtolower(trim(strval($extra['provider_protocol'] ?? ($extra['protocol'] ?? ''))));
            if ($protocol === 'niwaai') {
                return trim(strval($row['provider_key'] ?? ''));
            }
        }
        foreach ($rows as $row) {
            $providerKey = strtolower(trim(strval($row['provider_key'] ?? '')));
            if (in_array($providerKey, ['niwaai', 'zhuibao', 'deepsekk', 'deepseek', 'avatar', 'tts'], true)) {
                return trim(strval($row['provider_key'] ?? ''));
            }
        }
        $model = Db::name('digitalavatar_model')
            ->where('aid', $aid)
            ->where('status', 1)
            ->order('id asc')
            ->find();
        return trim(strval($model['provider_key'] ?? ''));
    }

    private static function decodeConfig($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function encodeConfig($value): string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
