<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarProviderService
{
    private const DEFAULT_TIMEOUT = 120;
    private const DEFAULT_POLL_INTERVAL = 8;

    public static function listConfigs(int $aid, bool $includeDisabled = true): array
    {
        $query = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->order('id asc');
        if (!$includeDisabled) {
            $query->where('status', 1);
        }

        $rows = $query->select()->toArray();
        foreach ($rows as &$row) {
            $row = self::formatConfig($row, true);
        }
        unset($row);

        return $rows;
    }

    public static function getConfig(int $aid, string $providerKey, bool $activeOnly = true): array
    {
        $providerKey = self::normalizeKey($providerKey);
        if ($providerKey === '') {
            return [];
        }

        $query = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('provider_key', $providerKey);
        if ($activeOnly) {
            $query->where('status', 1);
        }

        $row = $query->find();
        return is_array($row) ? $row : [];
    }

    public static function saveConfig(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $providerKey = self::normalizeKey(strval($info['provider_key'] ?? ''));
        $providerName = trim(strval($info['provider_name'] ?? ''));
        if ($providerKey === '') {
            throw new \RuntimeException('请填写提供商标识');
        }
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,59}$/', $providerKey)) {
            throw new \RuntimeException('提供商标识格式不正确');
        }
        if ($providerName === '') {
            $providerName = $providerKey;
        }

        $existing = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('provider_key', $providerKey)
            ->when($id > 0, function ($query) use ($id) {
                $query->where('id', '<>', $id);
            })
            ->find();
        if ($existing) {
            throw new \RuntimeException('该提供商标识已存在');
        }

        $data = [
            'aid' => $aid,
            'provider_key' => $providerKey,
            'provider_name' => mb_substr($providerName, 0, 120),
            'base_url' => trim(strval($info['base_url'] ?? '')),
            'status' => intval($info['status'] ?? 0) === 1 ? 1 : 0,
            'extra_json' => self::encodeExtra($info['extra'] ?? ($info['extra_json'] ?? [])),
            'update_time' => time(),
        ];

        foreach (['api_key', 'api_secret', 'callback_secret'] as $field) {
            if (!array_key_exists($field, $info)) {
                continue;
            }
            $value = trim(strval($info[$field]));
            if ($value === '' || strpos($value, '****') !== false) {
                continue;
            }
            $data[$field] = $value;
        }

        if ($id > 0) {
            $row = Db::name('digitalavatar_provider_config')
                ->where('aid', $aid)
                ->where('id', $id)
                ->find();
            if (!$row) {
                throw new \RuntimeException('提供商配置不存在');
            }
            Db::name('digitalavatar_provider_config')->where('id', $id)->update($data);
        } else {
            $data['create_time'] = time();
            $id = intval(Db::name('digitalavatar_provider_config')->insertGetId($data));
        }

        $row = Db::name('digitalavatar_provider_config')->where('aid', $aid)->where('id', $id)->find();
        return self::formatConfig($row ?: [], true);
    }

    public static function deleteConfig(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请传入配置ID');
        }
        $affected = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('id', $id)
            ->delete();
        if ($affected <= 0) {
            throw new \RuntimeException('提供商配置不存在');
        }
    }

    public static function resolveProviderModel(int $aid, array $info): array
    {
        $providerKey = self::normalizeKey(strval($info['provider_key'] ?? ''));
        $modelCode = trim(strval($info['model_code'] ?? ''));

        if ($providerKey !== '') {
            $provider = self::getConfig($aid, $providerKey, true);
            if (!$provider) {
                throw new \RuntimeException('提供商未配置或已停用');
            }
        } else {
            $provider = Db::name('digitalavatar_provider_config')
                ->where('aid', $aid)
                ->where('status', 1)
                ->order('id asc')
                ->find();
            if (!$provider) {
                throw new \RuntimeException('暂未配置可用的 AI 提供商');
            }
            $providerKey = strval($provider['provider_key']);
        }

        if ($modelCode === '') {
            $extra = self::decodeExtra($provider['extra_json'] ?? '');
            $modelCode = trim(strval($extra['default_model'] ?? ''));
        }
        if ($modelCode === '') {
            $modelCode = self::defaultModelForTask(strval($info['task_type'] ?? ''));
        }

        return [
            'provider_key' => $providerKey,
            'model_code' => $modelCode,
            'provider_name' => strval($provider['provider_name'] ?? $providerKey),
        ];
    }

    /**
     * 执行一个数字人任务。
     *
     * Provider 只接受服务端配置，浏览器不会拿到 api_key/api_secret。
     * 配置约定：
     * - extra.task_endpoints[task_type]：提交任务路径
     * - extra.status_endpoints[task_type]：查询任务路径，可使用 {remote_id}
     * - extra.auth_type：bearer / x-api-key / header / basic / none
     * - extra.response：remote_id/status/progress/result_url/error 的点号路径
     */
    public static function executeTask(int $aid, array $task): array
    {
        $localInput = self::decodeJson($task['input_json'] ?? '');
        if (!is_array($localInput)) {
            $localInput = [];
        }
        $localTaskType = trim(strval($task['task_type'] ?? ''));
        $localModelCode = trim(strval($task['model_code'] ?? ($localInput['model_code'] ?? '')));
        if ($localTaskType === 'avatar_clone' && DigitalAvatarService::isLocalImageAvatarClone($localModelCode, $localInput)) {
            try {
                $result = DigitalAvatarService::buildLocalImageAvatarCloneResult($localInput);
                return self::successResult($result, 'XPro 图片形象已保存');
            } catch (\Throwable $e) {
                return self::failedResult($e->getMessage());
            }
        }

        if ($localTaskType === 'image') {
            $existingResult = self::decodeJson($task['result_json'] ?? '');
            $existingResult = is_array($existingResult) ? $existingResult : [];
            $niwaPending = self::existingImageTaskIds($existingResult) !== []
                || trim(strval($existingResult['channel_search_id'] ?? '')) !== ''
                || trim(strval($existingResult['provider'] ?? '')) === 'niwaai';
            if (!$niwaPending) {
                $openAiProvider = self::getOpenAiImageProvider($aid, $task, $localInput);
                if ($openAiProvider) {
                    return self::executeOpenAiImage(
                        $aid,
                        $task,
                        $localInput,
                        $existingResult,
                        $openAiProvider
                    );
                }
            }
        }

        if (self::shouldUseZhuibaoNiwaai($task)) {
            return self::executeZhuibaoNiwaaiTask($aid, $task);
        }

        $providerKey = self::normalizeKey(strval($task['provider_key'] ?? ''));
        $provider = self::getRawConfig($aid, $providerKey);
        if (!$provider) {
            if (trim(strval($localInput['source'] ?? '')) === 'image_agent') {
                return self::failedResult('Agent 作图未匹配到已绑定的出图提供商');
            }
            return self::failedResult('提供商未配置或已停用');
        }

        $extra = self::decodeExtra($provider['extra_json'] ?? '');
        $input = self::decodeJson($task['input_json'] ?? '');
        $existingResult = self::decodeJson($task['result_json'] ?? '');
        $remoteId = trim(strval($existingResult['remote_id'] ?? ''));
        $responseMap = is_array($extra['response'] ?? null) ? $extra['response'] : [];
        $isPolling = intval($task['status'] ?? 0) === 1 && $remoteId !== '';

        if ($isPolling) {
            $nextPollTime = intval($existingResult['next_poll_time'] ?? 0);
            if ($nextPollTime > time()) {
                return [
                    'status' => 'skipped',
                    'message' => '等待提供商任务进入下一次轮询时间',
                    'task_id' => intval($task['id'] ?? 0),
                ];
            }
        }

        $taskType = strval($task['task_type'] ?? '');
        $modelConfig = self::loadModelConfig($aid, $taskType, strval($task['model_code'] ?? ''));
        if ($isPolling) {
            $path = trim(strval($modelConfig['status_endpoint'] ?? ''));
            if ($path === '') {
                $path = self::resolveEndpoint(
                    $extra['status_endpoints'] ?? [],
                    $taskType,
                    strval($extra['status_endpoint'] ?? '')
                );
            }
            if ($path === '') {
                $path = '/v1/tasks/{remote_id}';
            }
            $path = str_replace('{remote_id}', rawurlencode($remoteId), $path);
            $payload = [
                'task_id' => intval($task['id'] ?? 0),
                'request_id' => strval($task['request_id'] ?? ''),
                'remote_id' => $remoteId,
                'task_type' => $taskType,
                'model_code' => strval($task['model_code'] ?? ''),
            ];
            $method = strtoupper(trim(strval($extra['status_method'] ?? 'GET'))) ?: 'GET';
        } else {
            $path = '';
            if (in_array($taskType, ['voice_clone', 'avatar_clone'], true)) {
                $path = trim(strval($modelConfig['clone_endpoint'] ?? ''));
            }
            if ($path === '') {
                $path = trim(strval($modelConfig['submit_endpoint'] ?? ''));
            }
            if ($path === '') {
                $path = self::resolveEndpoint(
                    $extra['task_endpoints'] ?? [],
                    $taskType,
                    strval($extra['task_endpoint'] ?? '')
                );
            }
            if ($path === '') {
                $path = '/v1/generate';
            }
            $payload = $input;
            $payload['task_id'] = intval($task['id'] ?? 0);
            $payload['request_id'] = strval($task['request_id'] ?? '');
            $payload['task_type'] = $taskType;
            $payload['model_code'] = strval($task['model_code'] ?? '');
            $method = strtoupper(trim(strval($extra['task_method'] ?? $extra['method'] ?? 'POST'))) ?: 'POST';
        }

        $response = self::requestJson($provider, $path, $payload, $method, $extra);
        if (intval($response['status'] ?? 0) !== 1) {
            return self::failedResult(
                self::providerApiMessage($response, '提供商请求失败'),
                self::withProviderCall($existingResult, $response, $isPolling ? 'status' : 'submit')
            );
        }

        $body = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $bizCode = self::readFirstPath($body, ['code', 'data.code']);
        $statusValue = self::readPath($body, strval($responseMap['status'] ?? 'status'));
        if ($statusValue === null) {
            $statusValue = $bizCode;
        }
        if ($statusValue === null) {
            $statusValue = self::readPath($response, 'status');
        }
        $remoteIdValue = self::readFirstPath($body, [
            strval($responseMap['remote_id'] ?? ''),
            'remote_id',
            'task_id',
            'id',
            'data.id',
        ]);
        $profileIdValue = self::readFirstPath($body, [
            strval($responseMap['profile_id'] ?? ''),
            'model_id',
            'avatar_id',
            'voice_id',
            'voice_model_id',
            'resource_id',
            'data.model_id',
            'data.avatar_id',
            'data.voice_id',
        ]);
        $resultUrl = self::readFirstPath($body, [
            strval($responseMap['result_url'] ?? ''),
            'result_url',
            'url',
            'video_url',
            'audio_url',
            'image_url',
            'data.url',
        ]);
        $errorMessage = self::readFirstPath($body, [
            strval($responseMap['error'] ?? ''),
            'error.message',
            'error_msg',
            'message',
            'msg',
        ]);
        $progress = self::normalizeProgress(self::readFirstPath($body, [
            strval($responseMap['progress'] ?? ''),
            'progress',
            'data.progress',
        ]));
        $content = self::readFirstPath($body, [
            strval($responseMap['content'] ?? ''),
            'content',
            'text',
            'output',
            'data.content',
        ]);
        $statusName = strtolower(trim(strval($statusValue)));

        if (self::isFailedStatus($statusValue) || self::isFailedStatus($bizCode)) {
            return self::failedResult(
                self::providerApiMessage($response, trim(strval($errorMessage)) ?: '提供商任务执行失败'),
                self::withProviderCall($existingResult, $response, $isPolling ? 'status' : 'submit')
            );
        }

        if ($remoteIdValue !== '' && $resultUrl === '' && trim(strval($content)) === '') {
            $pollInterval = max(3, min(300, intval($extra['poll_interval'] ?? self::DEFAULT_POLL_INTERVAL)));
            $result = array_merge($existingResult, [
                'remote_id' => trim(strval($remoteIdValue)),
                'progress' => $progress,
                'next_poll_time' => time() + $pollInterval,
                'provider_status' => $statusName,
            ]);
            $result = self::withProviderCall($result, $response, 'status');
            return [
                'status' => 'pending',
                'message' => '提供商任务处理中',
                'task_id' => intval($task['id'] ?? 0),
                'remote_id' => trim(strval($remoteIdValue)),
                'progress' => $progress,
                'result' => $result,
            ];
        }

        if (in_array($taskType, ['voice_clone', 'avatar_clone'], true) && $profileIdValue === '') {
            return self::failedResult(
                self::providerApiMessage($response, trim(strval($errorMessage)) ?: '第三方未返回音色/形象ID，克隆未真正成功'),
                self::withProviderCall($existingResult, $response, $isPolling ? 'status' : 'submit')
            );
        }

        if (
            self::isSuccessStatus($statusValue)
            || ($resultUrl !== '' && $remoteIdValue === '')
            || trim(strval($content)) !== ''
            || ($profileIdValue !== '' && in_array($taskType, ['avatar_clone', 'voice_clone'], true))
        ) {
            $result = [
                'remote_id' => trim(strval($remoteIdValue)),
                'profile_id' => trim(strval($profileIdValue)),
                'url' => trim(strval($resultUrl)),
                'progress' => 100,
                'data' => self::limitValue($body),
                'content' => self::limitValue($content),
                'assets' => self::extractAssets($body, strval($task['task_type'] ?? '')),
            ];
            $result = self::withProviderCall($result, $response, $isPolling ? 'status' : 'submit');
            return [
                'status' => 'success',
                'message' => '提供商任务执行完成',
                'task_id' => intval($task['id'] ?? 0),
                'result' => $result,
            ];
        }

        return self::failedResult('提供商未返回任务编号或结果地址', [
            'provider_response' => self::limitResponse($response),
        ]);
    }

    private static function shouldUseZhuibaoNiwaai(array $task): bool
    {
        $taskType = trim(strval($task['task_type'] ?? ''));
        if (!in_array($taskType, ['avatar_video', 'audio', 'voice_clone', 'avatar_clone', 'image'], true)) {
            return false;
        }

        $providerKey = self::normalizeKey(strval($task['provider_key'] ?? ''));
        $input = self::decodeJson($task['input_json'] ?? '');
        $protocol = self::normalizeKey(strval($input['provider_protocol'] ?? ($input['protocol'] ?? '')));
        if ($protocol === 'niwaai' || in_array($providerKey, ['niwaai', 'niwa', 'max', 'zhuibao', 'deepsekk', 'deepseek', 'avatar', 'tts'], true)) {
            return true;
        }

        $aid = intval($task['aid'] ?? 0);
        if ($aid <= 0 || $providerKey === '') {
            return false;
        }
        $provider = self::getRawConfig($aid, $providerKey);
        $extra = self::decodeExtra($provider['extra_json'] ?? '');
        $baseUrl = strtolower(trim(strval($provider['base_url'] ?? ($extra['base_url'] ?? ''))));
        $extraProtocol = self::normalizeKey(strval($extra['provider_protocol'] ?? ($extra['protocol'] ?? '')));
        return $extraProtocol === 'niwaai' || strpos($baseUrl, 'niwaai') !== false;
    }

    private static function executeZhuibaoNiwaaiTask(int $aid, array $task): array
    {
        $taskType = trim(strval($task['task_type'] ?? ''));
        $input = self::decodeJson($task['input_json'] ?? '');
        $existingResult = self::decodeJson($task['result_json'] ?? '');

        if ($taskType === 'voice_clone') {
            return self::executeZhuibaoVoiceClone($aid, $task, $input, $existingResult);
        }
        if ($taskType === 'avatar_clone') {
            return self::executeZhuibaoAvatarClone($aid, $task, $input, $existingResult);
        }
        if ($taskType === 'audio') {
            return self::executeZhuibaoAudio($aid, $task, $input, $existingResult);
        }
        if ($taskType === 'avatar_video') {
            return self::executeZhuibaoAvatarVideo($aid, $task, $input, $existingResult);
        }
        if ($taskType === 'image') {
            return self::executeZhuibaoImage($aid, $task, $input, $existingResult);
        }

        return self::failedResult('NiwaAI 暂不支持该任务类型');
    }

    private static function getZhuibaoProvider(int $aid, string $providerKey): array
    {
        $fromDigital = self::niwaProviderFromDigitalAvatar($aid);
        if ($fromDigital) {
            return self::withEndpointOverrides($aid, $fromDigital, $providerKey);
        }

        if (!class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
            return [];
        }

        $provider = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getProviderConfigByKey($aid, $providerKey);
        if (intval($provider['status'] ?? 0) === 1 && trim(strval($provider['app_id'] ?? '')) !== '') {
            return self::withEndpointOverrides($aid, $provider, $providerKey);
        }
        if ($providerKey === 'tts') {
            $avatar = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getProviderConfigByKey($aid, 'avatar');
            if (intval($avatar['status'] ?? 0) === 1 && trim(strval($avatar['app_id'] ?? '')) !== '') {
                return self::withEndpointOverrides($aid, $avatar, 'avatar');
            }
        }
        return [];
    }

    private static function niwaProviderFromDigitalAvatar(int $aid): array
    {
        if ($aid <= 0) {
            return [];
        }
        $rows = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('status', 1)
            ->order('id asc')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $extra = self::decodeExtra($row['extra_json'] ?? '');
            $baseUrl = trim(strval($row['base_url'] ?? ($extra['base_url'] ?? '')));
            $providerKey = self::normalizeKey(strval($row['provider_key'] ?? ''));
            $protocol = self::normalizeKey(strval($extra['provider_protocol'] ?? ($extra['protocol'] ?? '')));
            $looksNiwa = $protocol === 'niwaai'
                || strpos(strtolower($baseUrl), 'niwaai') !== false
                || in_array($providerKey, ['max', 'niwa', 'niwaai', 'tts', 'avatar'], true);
            if (!$looksNiwa) {
                continue;
            }
            $appId = trim(strval($extra['app_id'] ?? ($row['api_key'] ?? '')));
            $appSecret = trim(strval($extra['app_secret'] ?? ($row['api_secret'] ?? '')));
            if ($appId === '' || $appSecret === '' || $baseUrl === '') {
                continue;
            }
            return [
                'status' => 1,
                'provider_key' => strval($row['provider_key'] ?? ''),
                'provider_name' => strval($row['provider_name'] ?? ''),
                'base_url' => rtrim($baseUrl, '/'),
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'api_key' => $appId,
                'api_secret' => $appSecret,
                'extra_json' => $extra,
            ];
        }
        return [];
    }

    private static function loadModelConfig(int $aid, string $taskType, string $modelCode): array
    {
        try {
            $model = DigitalAvatarModelService::resolveForTask($aid, $taskType, $modelCode);
            return is_array($model['config'] ?? null) ? $model['config'] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function withModelEndpoints(array $provider, array $modelConfig, array $spec): array
    {
        return DigitalAvatarNiwaCatalog::applyModelPathOverrides($provider, $modelConfig, $spec);
    }

    public static function shouldDeferImageExecution(int $aid, array $task, array $input): bool
    {
        $taskType = trim(strval($task['task_type'] ?? ($input['task_type'] ?? '')));
        if ($taskType !== 'image') {
            return false;
        }
        return self::getOpenAiImageProvider($aid, $task, $input) !== [];
    }

    private static function getOpenAiImageProvider(int $aid, array $task, array $input): array
    {
        $source = trim(strval($input['source'] ?? ''));
        $taskKey = self::normalizeKey(strval($task['provider_key'] ?? ''));
        $imageModel = self::normalizeKey(strval($input['image_model'] ?? ($task['model_code'] ?? '')));
        if ($taskKey !== '') {
            $row = self::getRawConfig($aid, $taskKey);
            if ($row && self::isOpenAiImageProvider($row)) {
                return $row;
            }
            if ($source === 'image_agent') {
                return [];
            }
        }
        $keys = [];
        if (in_array($taskKey, ['hejuapi', 'juapi', 'openai_image'], true)) {
            $keys[] = $taskKey;
        }
        if ($source !== 'image_agent' && in_array($imageModel, ['gpt-image-2', 'gptimage2', 'gpt_image_2'], true)) {
            array_push($keys, 'hejuapi', 'juapi', 'openai_image');
        }
        foreach (array_unique($keys) as $key) {
            $row = self::getRawConfig($aid, $key);
            if ($row && self::isOpenAiImageProvider($row)) {
                return $row;
            }
        }
        if ($source !== 'image_agent') {
            return [];
        }
        return [];
    }

    private static function isOpenAiImageProvider(array $row): bool
    {
        $key = self::normalizeKey(strval($row['provider_key'] ?? ''));
        if (in_array($key, ['hejuapi', 'juapi', 'openai_image'], true)) {
            return true;
        }
        $extra = self::decodeExtra($row['extra_json'] ?? '');
        $protocol = self::normalizeKey(strval($extra['provider_protocol'] ?? ($extra['protocol'] ?? '')));
        if (in_array($protocol, ['openai_image', 'hejuapi', 'juapi', 'openai', 'duomi_image', 'duomiapi'], true)) {
            return true;
        }
        $baseUrl = strtolower(trim(strval($row['base_url'] ?? ($extra['base_url'] ?? ''))));
        if (strpos($baseUrl, 'hejuapi.com') !== false || strpos($baseUrl, '790053500.com') !== false || strpos($baseUrl, 'duomiapi.com') !== false) {
            return true;
        }
        return in_array($key, ['nekoapi', 'xinwang', 'neko', 'duomiapi', 'duomi'], true);
    }

    private static function isDuomiImageProvider(array $row): bool
    {
        $key = self::normalizeKey(strval($row['provider_key'] ?? ''));
        if (in_array($key, ['duomiapi', 'duomi', 'dmiapi'], true)) {
            return true;
        }
        $extra = self::decodeExtra($row['extra_json'] ?? '');
        $protocol = self::normalizeKey(strval($extra['provider_protocol'] ?? ($extra['protocol'] ?? '')));
        if (in_array($protocol, ['duomi_image', 'duomiapi', 'duomi'], true)) {
            return true;
        }
        $baseUrl = strtolower(trim(strval($row['base_url'] ?? ($extra['base_url'] ?? ''))));
        return strpos($baseUrl, 'duomiapi.com') !== false || strpos($baseUrl, 'dmiapi.com') !== false;
    }

    private static function executeOpenAiImage(int $aid, array $task, array $input, array $existingResult, array $providerRow): array
    {
        if (self::isDuomiImageProvider($providerRow)) {
            return self::executeDuomiImage($aid, $task, $input, $existingResult, $providerRow);
        }

        if (!class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoImageApi')) {
            return self::failedResult('图片生成接口不可用');
        }

        $existingUrl = trim(strval($existingResult['url'] ?? ($existingResult['image_url'] ?? '')));
        if ($existingUrl !== '' && intval($existingResult['gen_status'] ?? 0) === 2) {
            return self::successResult(array_merge($existingResult, [
                'url' => $existingUrl,
                'assets' => [[
                    'url' => $existingUrl,
                    'asset_type' => 'image',
                    'metadata' => ['source' => 'hejuapi'],
                ]],
            ]), 'JuAPI 图片已生成');
        }

        $extra = self::decodeExtra($providerRow['extra_json'] ?? '');
        $built = DigitalAvatarNiwaCatalog::buildImagePayload([
            'platform_code' => 'imageA',
            'model' => 'gpt-image-2',
        ], $input);
        $prompt = trim(strval($built['prompt'] ?? ''));
        if ($prompt === '') {
            return self::failedResult('请先填写图片描述');
        }
        $size = self::normalizeOpenAiImageSize(strval($built['size'] ?? ($input['size'] ?? '1024x1024')));
        $quality = trim(strval($built['quality'] ?? ($input['quality'] ?? 'high'))) ?: 'high';
        if (trim(strval($input['source'] ?? '')) === 'image_agent') {
            $agentQuality = strtolower(trim(strval($input['quality'] ?? '')));
            if (in_array($agentQuality, ['low', 'medium', 'high'], true)) {
                $quality = $agentQuality;
            }
        }
        $images = is_array($built['images'] ?? null) ? array_values($built['images']) : [];
        $modelName = trim(strval($task['model_code'] ?? ($input['image_model'] ?? ($extra['default_model'] ?? 'gpt-image-2')))) ?: 'gpt-image-2';
        $providerName = trim(strval($providerRow['provider_name'] ?? '')) ?: '图片接口';
        $apiKey = trim(strval($providerRow['api_key'] ?? ''));
        $baseUrl = trim(strval($providerRow['base_url'] ?? ($extra['base_url'] ?? '')));
        if ($apiKey === '' || $baseUrl === '') {
            return self::failedResult($providerName . ' 未配置完整');
        }

        $providerConfig = [
            'provider_type' => 'openai',
            'base_url' => rtrim($baseUrl, '/'),
            'api_key' => $apiKey,
            'model_name' => $modelName,
            'image_size' => $size,
            'provider_name' => $providerName,
        ];
        $options = [
            'size' => $size,
            'quality' => $quality,
            'n' => 1,
            'timeout' => max(60, intval($extra['timeout'] ?? 180)),
            'connect_timeout' => 20,
            'reference_images' => $images,
            'max_reference_images' => 4,
            'retry_without_reference_images' => $images === [] ? 1 : 0,
            'temporary_failure_retry_count' => 1,
        ];
        $background = strtolower(trim(strval($input['background'] ?? ($built['background'] ?? ''))));
        if (in_array($background, ['transparent', 'opaque'], true)) {
            $options['background'] = $background;
        }
        $response = \app\plugin\zhuibaoagent\common\ZhuibaoImageApi::generate($providerConfig, $prompt, $options);
        $safeResponse = $response;
        unset($safeResponse['image_binary'], $safeResponse['raw']);
        if (isset($safeResponse['data']['data'][0]['b64_json'])) {
            $safeResponse['data']['data'][0]['b64_json'] = '[omitted]';
        }
        $providerKey = self::normalizeKey(strval($providerRow['provider_key'] ?? ''));
        $host = strval(parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl);
        $callName = ($providerKey !== '' ? $providerKey : 'openai') . '.image';
        $requestUrl = trim(strval($response['request_url'] ?? ''));
        if ($requestUrl === '') {
            $requestUrl = rtrim($baseUrl, '/') . (count($images) > 0 ? '/images/edits' : '/images/generations');
        }
        $safeResponse['request'] = [
            'url' => $requestUrl,
            'model' => $modelName,
            'provider_key' => $providerKey,
            'provider_name' => $providerName,
        ];

        if (intval($response['status'] ?? 0) !== 1) {
            $raw = self::providerApiMessage($response, '出图失败');
            return self::failedResult(
                self::explainOpenAiImageError($providerName, $host, $modelName, $raw, intval($response['http_code'] ?? 0)),
                self::withProviderCall($existingResult, $safeResponse, $callName)
            );
        }

        $imageUrl = trim(strval($response['image_url'] ?? ''));
        $binary = strval($response['image_binary'] ?? '');
        if ($binary === '' && $imageUrl !== '') {
            $binary = self::downloadRemoteBinary($imageUrl);
        }
        if ($binary !== '') {
            try {
                $stored = DigitalAvatarService::storeMemberImage(
                    $aid,
                    intval($task['bid'] ?? 0),
                    intval($task['member_id'] ?? 0),
                    $binary,
                    'gpt-image-2.png',
                    false
                );
                if (trim(strval($stored['url'] ?? '')) !== '') {
                    $imageUrl = trim(strval($stored['url']));
                }
            } catch (\Throwable $e) {
                if ($imageUrl === '') {
                    return self::failedResult('图片已生成但保存失败：' . $e->getMessage(), self::withProviderCall($existingResult, $safeResponse, $callName));
                }
            }
        }
        if ($imageUrl === '') {
            return self::failedResult($providerName . ' 未返回图片地址', self::withProviderCall($existingResult, $safeResponse, $callName));
        }

        $result = self::withProviderCall(array_merge($existingResult, [
            'provider' => $providerKey !== '' ? $providerKey : 'openai',
            'phase' => 'image',
            'model' => $modelName,
            'gen_status' => 2,
            'url' => $imageUrl,
            'image_url' => $imageUrl,
            'payload' => [
                'model' => $modelName,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'images' => $images,
            ],
            'assets' => [[
                'url' => $imageUrl,
                'asset_type' => 'image',
                'metadata' => ['source' => $providerKey !== '' ? $providerKey : 'openai', 'model' => $modelName],
            ]],
        ]), $safeResponse, $callName);

        return self::successResult($result, $providerName . ' 图片生成完成');
    }

    private static function executeDuomiImage(int $aid, array $task, array $input, array $existingResult, array $providerRow): array
    {
        $existingUrl = trim(strval($existingResult['url'] ?? ($existingResult['image_url'] ?? '')));
        if ($existingUrl !== '' && intval($existingResult['gen_status'] ?? 0) === 2) {
            return self::successResult(array_merge($existingResult, [
                'url' => $existingUrl,
                'assets' => [[
                    'url' => $existingUrl,
                    'asset_type' => 'image',
                    'metadata' => ['source' => 'duomiapi'],
                ]],
            ]), '多米图片已生成');
        }

        $extra = self::decodeExtra($providerRow['extra_json'] ?? '');
        $built = DigitalAvatarNiwaCatalog::buildImagePayload([
            'platform_code' => 'imageA',
            'model' => 'gpt-image-2',
        ], $input);
        $prompt = trim(strval($built['prompt'] ?? ''));
        if ($prompt === '') {
            return self::failedResult('请先填写图片描述');
        }
        $size = self::normalizeOpenAiImageSize(strval($built['size'] ?? ($input['size'] ?? '1024x1024')));
        $quality = trim(strval($built['quality'] ?? ($input['quality'] ?? 'high'))) ?: 'high';
        if (trim(strval($input['source'] ?? '')) === 'image_agent') {
            $agentQuality = strtolower(trim(strval($input['quality'] ?? '')));
            if (in_array($agentQuality, ['low', 'medium', 'high'], true)) {
                $quality = $agentQuality;
            }
        }
        if (DigitalAvatarImageAgentService::normalizeStage(strval($input['image_stage'] ?? '')) === 'final') {
            $size = self::duomiHdSize($size);
            if ($quality === 'low') {
                $quality = 'high';
            }
        }
        $images = is_array($built['images'] ?? null) ? array_values($built['images']) : [];
        $modelName = self::duomiApiModel(strval($task['model_code'] ?? ($input['image_model'] ?? '')), $extra);
        $providerName = trim(strval($providerRow['provider_name'] ?? '')) ?: '多米生图';
        $providerKey = self::normalizeKey(strval($providerRow['provider_key'] ?? 'duomiapi'));
        $host = strval(parse_url(strval($providerRow['base_url'] ?? ''), PHP_URL_HOST) ?: 'duomiapi.com');
        $callName = $providerKey . '.image';
        $remoteId = trim(strval($existingResult['remote_id'] ?? ''));

        if ($remoteId !== '') {
            $nextPoll = intval($existingResult['next_poll_time'] ?? 0);
            if ($nextPoll > time()) {
                return [
                    'status' => 'pending',
                    'message' => $providerName . ' 任务处理中',
                    'remote_id' => $remoteId,
                    'progress' => max(1, min(99, intval($existingResult['progress'] ?? 20))),
                    'result' => $existingResult,
                ];
            }
            return self::pollDuomiImage($aid, $task, $existingResult, $providerRow, $remoteId, $modelName, $providerName, $host, $callName);
        }

        $payload = [
            'model' => $modelName,
            'prompt' => mb_substr($prompt, 0, 5000),
            'size' => $size,
            'quality' => $quality,
        ];
        $refUrls = [];
        foreach ($images as $image) {
            $url = trim(strval($image));
            if (preg_match('#^https?://#i', $url)) {
                $refUrls[] = $url;
            }
        }
        if ($refUrls !== []) {
            $payload['image'] = count($refUrls) === 1 ? $refUrls[0] : $refUrls;
        }

        $requestExtra = array_merge($extra, [
            'auth_type' => trim(strval($extra['auth_type'] ?? 'bearer')) ?: 'bearer',
            'timeout' => max(20, intval($extra['timeout'] ?? 60)),
        ]);
        $response = self::requestJson($providerRow, '/v1/images/generations?async=true', $payload, 'POST', $requestExtra);
        $safeResponse = $response;
        unset($safeResponse['raw']);
        $safeResponse['request'] = [
            'url' => rtrim(strval($providerRow['base_url'] ?? ''), '/') . '/v1/images/generations?async=true',
            'model' => $modelName,
            'provider_key' => $providerKey,
            'provider_name' => $providerName,
        ];
        if (intval($response['status'] ?? 0) !== 1) {
            $raw = self::providerApiMessage($response, '创建生图任务失败');
            return self::failedResult(
                self::explainOpenAiImageError($providerName, $host, $modelName, $raw, intval($response['http_code'] ?? 0)),
                self::withProviderCall($existingResult, $safeResponse, $callName . '.submit')
            );
        }
        $body = is_array($response['data'] ?? null) ? $response['data'] : [];
        $newRemoteId = trim(strval($body['id'] ?? ($response['id'] ?? '')));
        if ($newRemoteId === '') {
            return self::failedResult($providerName . ' 未返回任务ID', self::withProviderCall($existingResult, $safeResponse, $callName . '.submit'));
        }

        $result = self::withProviderCall(array_merge($existingResult, [
            'provider' => $providerKey,
            'phase' => 'image',
            'model' => $modelName,
            'remote_id' => $newRemoteId,
            'gen_status' => 1,
            'progress' => 15,
            'next_poll_time' => time() + 2,
            'payload' => [
                'model' => $modelName,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'images' => $refUrls,
            ],
        ]), $safeResponse, $callName . '.submit');

        return [
            'status' => 'pending',
            'message' => $providerName . ' 任务已提交',
            'remote_id' => $newRemoteId,
            'progress' => 15,
            'result' => $result,
        ];
    }

    private static function pollDuomiImage(
        int $aid,
        array $task,
        array $existingResult,
        array $providerRow,
        string $remoteId,
        string $modelName,
        string $providerName,
        string $host,
        string $callName
    ): array {
        $extra = self::decodeExtra($providerRow['extra_json'] ?? '');
        $requestExtra = array_merge($extra, [
            'auth_type' => trim(strval($extra['auth_type'] ?? 'bearer')) ?: 'bearer',
            'timeout' => max(15, intval($extra['timeout'] ?? 60)),
        ]);
        $response = self::requestJson($providerRow, '/v1/tasks/' . rawurlencode($remoteId), [], 'GET', $requestExtra);
        $safeResponse = $response;
        unset($safeResponse['raw']);
        $safeResponse['request'] = [
            'url' => rtrim(strval($providerRow['base_url'] ?? ''), '/') . '/v1/tasks/' . $remoteId,
            'model' => $modelName,
            'provider_key' => self::normalizeKey(strval($providerRow['provider_key'] ?? 'duomiapi')),
            'provider_name' => $providerName,
        ];
        if (intval($response['status'] ?? 0) !== 1) {
            $raw = self::providerApiMessage($response, '查询生图任务失败');
            return self::failedResult(
                self::explainOpenAiImageError($providerName, $host, $modelName, $raw, intval($response['http_code'] ?? 0)),
                self::withProviderCall($existingResult, $safeResponse, $callName . '.status')
            );
        }
        $body = is_array($response['data'] ?? null) ? $response['data'] : [];
        $state = strtolower(trim(strval($body['state'] ?? '')));
        $progress = max(1, min(99, intval($body['progress'] ?? ($existingResult['progress'] ?? 20))));
        $result = self::withProviderCall(array_merge($existingResult, [
            'remote_id' => $remoteId,
            'progress' => $progress,
            'next_poll_time' => time() + 2,
            'duomi_state' => $state,
        ]), $safeResponse, $callName . '.status');

        if (in_array($state, ['pending', 'running', 'queued'], true) || $state === '') {
            if ($state === 'running' && $progress < 20) {
                $progress = 35;
            }
            return [
                'status' => 'pending',
                'message' => $providerName . ($state === 'running' ? ' 生成中' : ' 排队中'),
                'remote_id' => $remoteId,
                'progress' => $progress,
                'result' => $result,
            ];
        }
        if ($state === 'error' || $state === 'failed') {
            $raw = trim(strval($body['data']['description'] ?? ($body['message'] ?? ($body['error']['message'] ?? '生图失败'))));
            return self::failedResult(
                self::explainOpenAiImageError($providerName, $host, $modelName, $raw !== '' ? $raw : '生图失败', intval($response['http_code'] ?? 0)),
                $result
            );
        }
        if ($state !== 'succeeded' && $state !== 'success') {
            return [
                'status' => 'pending',
                'message' => $providerName . ' 任务处理中',
                'remote_id' => $remoteId,
                'progress' => $progress,
                'result' => $result,
            ];
        }

        $imageUrl = '';
        $imageItems = $body['data']['images'] ?? [];
        if (is_array($imageItems)) {
            foreach ($imageItems as $item) {
                if (is_array($item)) {
                    $imageUrl = trim(strval($item['url'] ?? ''));
                } else {
                    $imageUrl = trim(strval($item));
                }
                if ($imageUrl !== '') {
                    break;
                }
            }
        }
        if ($imageUrl === '') {
            return self::failedResult($providerName . ' 未返回图片地址', $result);
        }

        $binary = self::downloadRemoteBinary($imageUrl);
        if ($binary !== '') {
            try {
                $stored = DigitalAvatarService::storeMemberImage(
                    $aid,
                    intval($task['bid'] ?? 0),
                    intval($task['member_id'] ?? 0),
                    $binary,
                    'gpt-image-2.png',
                    false
                );
                if (trim(strval($stored['url'] ?? '')) !== '') {
                    $imageUrl = trim(strval($stored['url']));
                }
            } catch (\Throwable $e) {
                // keep remote url
            }
        }

        $done = self::withProviderCall(array_merge($existingResult, [
            'provider' => self::normalizeKey(strval($providerRow['provider_key'] ?? 'duomiapi')),
            'phase' => 'image',
            'model' => $modelName,
            'remote_id' => $remoteId,
            'gen_status' => 2,
            'progress' => 100,
            'url' => $imageUrl,
            'image_url' => $imageUrl,
            'assets' => [[
                'url' => $imageUrl,
                'asset_type' => 'image',
                'metadata' => ['source' => 'duomiapi', 'model' => $modelName],
            ]],
        ]), $safeResponse, $callName . '.done');

        return self::successResult($done, $providerName . ' 图片生成完成');
    }

    private static function duomiApiModel(string $modelCode, array $extra): string
    {
        $modelCode = trim($modelCode);
        $allowed = ['gpt-image-2', 'gpt-image-2.5', 'gpt-image-2.5-flare', 'gpt-image-2.5-sunburst'];
        if (in_array($modelCode, $allowed, true)) {
            return $modelCode;
        }
        $fallback = trim(strval($extra['default_model'] ?? 'gpt-image-2'));
        return in_array($fallback, $allowed, true) ? $fallback : 'gpt-image-2';
    }

    private static function duomiHdSize(string $size): string
    {
        $size = strtolower(trim(str_replace(['×', '*', 'X'], 'x', $size)));
        $map = [
            '4k' => '3840x2160',
            'uhd' => '3840x2160',
            '1:1' => '2880x2880',
            '1024x1024' => '2880x2880',
            '16:9' => '3840x2160',
            '1792x1024' => '3840x2160',
            '9:16' => '2160x3840',
            '1024x1792' => '2160x3840',
            '3:2' => '2880x1920',
            '1536x1024' => '2880x1920',
            '2:3' => '1920x2880',
            '1024x1536' => '1920x2880',
            '4:3' => '2880x2160',
            '3:4' => '2160x2880',
        ];
        if (isset($map[$size])) {
            return $map[$size];
        }
        if (preg_match('/^(\d+)x(\d+)$/', $size, $match)) {
            $width = intval($match[1]);
            $height = intval($match[2]);
            if ($width * $height >= 2000000) {
                return self::duomiClampSize($width, $height);
            }
            $scale = min(3840 / max($width, 1), 3840 / max($height, 1), sqrt(8294400 / max($width * $height, 1)));
            $width = intval(floor($width * $scale / 16) * 16);
            $height = intval(floor($height * $scale / 16) * 16);
            return self::duomiClampSize($width, $height);
        }
        return '2880x2880';
    }

    private static function duomiClampSize(int $width, int $height): string
    {
        $width = max(16, min(3840, intval(round($width / 16) * 16)));
        $height = max(16, min(3840, intval(round($height / 16) * 16)));
        if ($width * $height < 655360) {
            return '2880x2880';
        }
        while ($width * $height > 8294400 && ($width > 16 || $height > 16)) {
            if ($width >= $height && $width > 16) {
                $width = max(16, $width - 16);
            } else {
                $height = max(16, $height - 16);
            }
        }
        return $width . 'x' . $height;
    }

    private static function normalizeOpenAiImageSize(string $size): string
    {
        $size = strtolower(trim($size));
        $map = [
            '1:1' => '1024x1024',
            '16:9' => '1792x1024',
            '9:16' => '1024x1792',
            '3:2' => '1536x1024',
            '2:3' => '1024x1536',
            '4:3' => '1536x1024',
            'auto' => '1024x1024',
        ];
        if (isset($map[$size])) {
            return $map[$size];
        }
        if (preg_match('/^\d{3,5}x\d{3,5}$/', $size)) {
            return $size;
        }
        return '1024x1024';
    }

    private static function downloadRemoteBinary(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $body = curl_exec($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($httpCode >= 400 || !is_string($body) || $body === '') {
            return '';
        }
        return $body;
    }

    private static function executeZhuibaoImage(int $aid, array $task, array $input, array $existingResult): array
    {
        $openAiProvider = self::getOpenAiImageProvider($aid, $task, $input);
        if ($openAiProvider) {
            return self::executeOpenAiImage($aid, $task, $input, $existingResult, $openAiProvider);
        }
        $provider = self::getZhuibaoProvider($aid, 'avatar');
        if (empty($provider)) {
            $provider = self::getZhuibaoProvider($aid, 'tts');
        }
        if (empty($provider)) {
            return self::failedResult('NiwaAI 图片生成配置未启用');
        }

        $prompt = trim(strval($input['prompt'] ?? ($input['text'] ?? ($input['content'] ?? ''))));
        if ($prompt === '') {
            return self::failedResult('请先填写图片描述');
        }

        $modelCode = trim(strval($task['model_code'] ?? ($input['model_code'] ?? 'imageA')));
        $modelConfig = self::loadModelConfig($aid, 'image', $modelCode);
        $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, $modelConfig, $input);
        $provider = self::withModelEndpoints($provider, $modelConfig, $spec);

        $existingUrl = trim(strval($existingResult['url'] ?? ($existingResult['image_url'] ?? '')));
        $existingStatus = intval($existingResult['gen_status'] ?? 0);
        if ($existingUrl !== '' && $existingStatus === 2) {
            return self::successResult(array_merge($existingResult, [
                'url' => $existingUrl,
                'assets' => [[
                    'url' => $existingUrl,
                    'asset_type' => 'image',
                    'metadata' => ['source' => 'niwaai'],
                ]],
            ]), 'NiwaAI 图片已生成');
        }

        $remoteIds = self::existingImageTaskIds($existingResult);
        if ($remoteIds !== []) {
            return self::pollZhuibaoImage($provider, $existingResult, $remoteIds, $spec);
        }

        $slot = self::claimUpstreamSubmit(intval($task['id'] ?? 0), $existingResult);
        if (!$slot['ok']) {
            $existingResult = $slot['result'];
            $remoteIds = self::existingImageTaskIds($existingResult);
            if ($remoteIds !== []) {
                return self::pollZhuibaoImage($provider, $existingResult, $remoteIds, $spec);
            }
            return [
                'status' => 'skipped',
                'message' => '图片任务正在提交，请勿重复请求',
                'task_id' => intval($task['id'] ?? 0),
            ];
        }
        $existingResult = $slot['result'];

        if (!class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoAvatarVoiceApi')) {
            return self::failedResult('图片生成接口不可用');
        }

        $input['title'] = trim(strval($input['title'] ?? ($task['title'] ?? 'AI图片创作')));
        $channelSearchId = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::buildChannelSearchId(
            strval($provider['app_id'] ?? ''),
            intval($task['member_id'] ?? 0),
            strval(intval($task['id'] ?? 0))
        );
        $input['channel_search_id'] = $channelSearchId;
        $payload = DigitalAvatarNiwaCatalog::buildImagePayload($spec, $input);

        $response = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::call($provider, 'image.submit', $payload);
        if (intval($response['status'] ?? 0) !== 1) {
            return self::failedResult(
                self::providerApiMessage($response, '图片生成失败'),
                self::withProviderCall($existingResult, $response, 'image.submit')
            );
        }

        $ids = self::extractImageTaskIds($response['data'] ?? []);
        if ($ids === [] && $channelSearchId !== '') {
            $ids = [$channelSearchId];
        }
        $result = self::withProviderCall(array_merge($existingResult, [
            'provider' => 'niwaai',
            'phase' => 'image',
            'platform_code' => strval($payload['platform_code'] ?? 'imageA'),
            'model' => strval($payload['model'] ?? 'gpt-image-2'),
            'remote_id' => isset($ids[0]) ? strval($ids[0]) : '',
            'remote_ids' => $ids,
            'channel_search_id' => $channelSearchId,
            'gen_status' => 0,
            'next_poll_time' => time() + 2,
            'payload' => self::limitValue($payload),
            'data' => self::limitValue($response['data'] ?? []),
        ]), $response, 'image.submit');

        if ($ids === []) {
            return self::failedResult(self::providerApiMessage($response, '第三方未返回图片任务ID'), $result);
        }

        return [
            'status' => 'pending',
            'message' => 'NiwaAI 图片任务处理中',
            'remote_id' => strval($ids[0]),
            'progress' => 20,
            'result' => $result,
        ];
    }

    private static function pollZhuibaoImage(array $provider, array $existingResult, array $remoteIds, array $spec): array
    {
        $assets = [];
        $pending = false;
        $lastResponse = [];
        $lastData = [];
        $failedMsg = '';
        foreach ($remoteIds as $searchId) {
            $searchId = trim(strval($searchId));
            if ($searchId === '') {
                continue;
            }
            $response = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::queryImageStatus($provider, $searchId, strval($spec['family'] ?? 'imagea'));
            $lastResponse = $response;
            if (intval($response['status'] ?? 0) !== 1) {
                return self::failedResult(
                    self::providerApiMessage($response, '图片任务查询失败'),
                    self::withProviderCall($existingResult, $response, 'image.status')
                );
            }
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            if (isset($data['data']) && is_array($data['data'])) {
                $data = $data['data'];
            }
            if ($data !== [] && array_keys($data) === range(0, count($data) - 1) && is_array($data[0] ?? null)) {
                $data = $data[0];
            }
            $lastData = $data;
            $genStatus = intval($data['gen_status'] ?? ($data['status'] ?? 0));
            $imageUrl = trim(strval(
                $data['image_url']
                ?? ($data['url'] ?? ($data['result_url'] ?? ($data['oss_url'] ?? '')))
            ));
            if ($imageUrl === '' && is_array($data['images'] ?? null) && $data['images'] !== []) {
                $firstImage = $data['images'][0];
                if (is_string($firstImage)) {
                    $imageUrl = trim($firstImage);
                } elseif (is_array($firstImage)) {
                    $imageUrl = trim(strval($firstImage['image_url'] ?? ($firstImage['url'] ?? '')));
                }
            }
            if ($genStatus === 3 || (is_string($data['gen_status'] ?? null) && self::isFailedStatus($data['gen_status'])) || (is_string($data['status'] ?? null) && self::isFailedStatus($data['status']))) {
                $failedMsg = trim(strval($data['err_msg'] ?? ($data['error_msg'] ?? ($data['fail_reason'] ?? ($data['error'] ?? '')))));
                if ($failedMsg === '' || preg_match('/^(操作成功|success|ok)$/iu', $failedMsg)) {
                    $failedMsg = '图片生成失败';
                }
                break;
            }
            if (($genStatus === 2 || $imageUrl !== '') && $imageUrl !== '') {
                $assets[] = [
                    'url' => $imageUrl,
                    'asset_type' => 'image',
                    'metadata' => [
                        'source' => 'niwaai',
                        'image_id' => strval($data['image_id'] ?? $searchId),
                        'model' => strval($data['model'] ?? ($spec['model'] ?? '')),
                    ],
                ];
                continue;
            }
            $pending = true;
        }

        $firstUrl = '';
        if ($assets !== []) {
            $firstUrl = strval($assets[0]['url'] ?? '');
        }
        $result = self::withProviderCall(array_merge($existingResult, [
            'provider' => 'niwaai',
            'phase' => 'image',
            'remote_id' => strval($remoteIds[0] ?? ''),
            'remote_ids' => $remoteIds,
            'gen_status' => $failedMsg !== '' ? 3 : ($pending ? intval($lastData['gen_status'] ?? 1) : 2),
            'next_poll_time' => $pending ? time() + 3 : time(),
            'url' => $firstUrl,
            'image_url' => $firstUrl,
            'data' => self::limitValue($lastData),
        ]), $lastResponse, 'image.status');

        if ($failedMsg !== '') {
            return self::failedResult($failedMsg !== '' ? $failedMsg : '图片生成失败', $result);
        }
        if (!$pending && $assets !== []) {
            return self::successResult(array_merge($result, [
                'url' => $firstUrl,
                'assets' => $assets,
            ]), 'NiwaAI 图片生成完成');
        }

        return [
            'status' => 'pending',
            'message' => 'NiwaAI 图片任务处理中',
            'remote_id' => strval($remoteIds[0] ?? ''),
            'progress' => $pending ? 55 : 20,
            'result' => $result,
        ];
    }

    private static function existingImageTaskIds(array $existingResult): array
    {
        $ids = [];
        if (!empty($existingResult['remote_ids']) && is_array($existingResult['remote_ids'])) {
            foreach ($existingResult['remote_ids'] as $id) {
                $id = trim(strval($id));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }
        $single = trim(strval($existingResult['remote_id'] ?? ($existingResult['image_id'] ?? '')));
        if ($single !== '' && !in_array($single, $ids, true)) {
            array_unshift($ids, $single);
        }
        return array_values(array_unique($ids));
    }

    private static function extractImageTaskIds($data): array
    {
        if (is_numeric($data)) {
            return [strval(intval($data))];
        }
        if (!is_array($data)) {
            return [];
        }
        $list = $data;
        if (isset($data['data']) && (is_array($data['data']) || is_numeric($data['data']))) {
            $list = $data['data'];
        }
        if (is_numeric($list)) {
            return [strval(intval($list))];
        }
        if (!is_array($list)) {
            return [];
        }
        $ids = [];
        $isList = $list !== [] && array_keys($list) === range(0, count($list) - 1);
        if ($isList) {
            foreach ($list as $item) {
                if (is_numeric($item)) {
                    $ids[] = strval(intval($item));
                    continue;
                }
                if (is_array($item)) {
                    $id = $item['image_id'] ?? ($item['id'] ?? ($item['search_id'] ?? ''));
                    if ($id !== '' && $id !== null) {
                        $ids[] = strval($id);
                    }
                }
            }
            return array_values(array_unique($ids));
        }
        $id = $list['image_id'] ?? ($list['id'] ?? ($list['search_id'] ?? ($list['channel_search_id'] ?? '')));
        if ($id !== '' && $id !== null) {
            $ids[] = strval($id);
        }
        return array_values(array_unique($ids));
    }

    private static function executeZhuibaoVoiceClone(int $aid, array $task, array $input, array $existingResult = []): array
    {
        $provider = self::getZhuibaoProvider($aid, 'tts');
        if (empty($provider)) {
            return self::failedResult('追爆插件的 NiwaAI 配音配置未启用');
        }

        $existingVoiceId = trim(strval($existingResult['voice_id'] ?? ($existingResult['profile_id'] ?? '')));
        if ($existingVoiceId !== '') {
            return self::successResult($existingResult, 'NiwaAI 声音克隆完成');
        }
        $slot = self::claimUpstreamSubmit(intval($task['id'] ?? 0), $existingResult);
        if (!$slot['ok']) {
            $locked = is_array($slot['result'] ?? null) ? $slot['result'] : $existingResult;
            $lockedVoiceId = trim(strval($locked['voice_id'] ?? ($locked['profile_id'] ?? '')));
            if ($lockedVoiceId !== '') {
                return self::successResult($locked, 'NiwaAI 声音克隆完成');
            }
            return [
                'status' => 'skipped',
                'message' => '声音克隆正在提交，请勿重复请求',
                'task_id' => intval($task['id'] ?? 0),
            ];
        }
        $existingResult = $slot['result'];

        try {
            $modelCode = trim(strval($task['model_code'] ?? ($input['model_code'] ?? '')));
            $modelConfig = [];
            try {
                $model = DigitalAvatarModelService::resolveForTask($aid, 'voice_clone', $modelCode);
                $modelCode = trim(strval($model['model_code'] ?? $modelCode));
                $modelConfig = is_array($model['config'] ?? null) ? $model['config'] : [];
            } catch (\Throwable $e) {
                $modelConfig = [];
            }
            $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, $modelConfig, $input);
            $provider = self::withModelEndpoints($provider, $modelConfig, $spec);
            $payloadInput = array_merge($input, [
                'sample_url' => trim(strval($input['sample_url'] ?? '')),
                'name' => trim(strval($input['name'] ?? ($task['title'] ?? '我的声音'))),
                'sex' => intval($input['sex'] ?? 1),
                'language' => trim(strval($input['language'] ?? 'zh')) ?: 'zh',
                'instruction' => trim(strval($input['instruction'] ?? '')),
                'description' => trim(strval($input['description'] ?? '')),
                'clone_text' => trim(strval($input['clone_text'] ?? ($input['text'] ?? ''))),
                'minimax_model' => trim(strval($input['minimax_model'] ?? '')),
                'language_boost' => trim(strval($input['language_boost'] ?? '')),
            ]);
            $payload = DigitalAvatarNiwaCatalog::buildClonePayload($spec, $payloadInput);
            $response = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::cloneVoice(
                $provider,
                $payload,
                DigitalAvatarNiwaCatalog::cloneApi($spec)
            );
            if (intval($response['status'] ?? 0) !== 1) {
                return self::failedResult(self::providerApiMessage($response, '声音克隆失败'), self::withProviderCall([], $response, 'voice.clone'));
            }
            $clone = is_array($response['data'] ?? null) ? $response['data'] : [];
            $voiceId = trim(strval($clone['voice_id'] ?? ''));
            if ($voiceId === '') {
                return self::failedResult('NiwaAI 未返回声音ID', self::withProviderCall([], $response, 'voice.clone'));
            }
            $clone['voice_service_code'] = trim(strval($clone['voice_service_code'] ?? ($spec['voice_service_code'] ?? '')));
            $clone['target_model'] = trim(strval($spec['target_model'] ?? ''));
            $clone['model_code'] = $modelCode !== '' ? $modelCode : strval($spec['voice_service_code'] ?? 'SPro');
            return self::successResult(self::withProviderCall(array_merge($existingResult, [
                'profile_id' => $voiceId,
                'voice_id' => $voiceId,
                'data' => $clone,
                'provider' => 'niwaai',
            ]), $response, 'voice.clone'), 'NiwaAI 声音克隆完成');
        } catch (\Throwable $e) {
            return self::failedResult($e->getMessage());
        }
    }

    private static function executeZhuibaoAvatarClone(int $aid, array $task, array $input, array $existingResult = []): array
    {
        $modelCode = trim(strval($task['model_code'] ?? ($input['model_code'] ?? '')));
        if (DigitalAvatarService::isLocalImageAvatarClone($modelCode, $input)) {
            try {
                $result = DigitalAvatarService::buildLocalImageAvatarCloneResult($input);
                return self::successResult($result, 'XPro 图片形象已保存');
            } catch (\Throwable $e) {
                return self::failedResult($e->getMessage());
            }
        }

        $provider = self::getZhuibaoProvider($aid, 'avatar');
        if (empty($provider)) {
            return self::failedResult('追爆插件的 NiwaAI 数字人配置未启用');
        }

        try {
            $modelConfig = self::loadModelConfig($aid, 'avatar_clone', strval($task['model_code'] ?? ''));
            $spec = DigitalAvatarNiwaCatalog::resolve(strval($task['model_code'] ?? ''), $modelConfig, $input);
            $provider = self::withModelEndpoints($provider, $modelConfig, $spec);
            $payload = [
                'sex' => intval($input['sex'] ?? 1) === 2 ? 2 : 1,
                'name' => trim(strval($input['name'] ?? ($task['title'] ?? '我的数字人'))) ?: '我的数字人',
                'platform_code' => trim(strval($input['platform_code'] ?? ($spec['platform_code'] ?? 'VPro'))) ?: 'VPro',
                'video_oss_url' => trim(strval($input['sample_url'] ?? '')),
            ];
            if ($payload['video_oss_url'] === '') {
                return self::failedResult('请先上传数字人样本视频');
            }
            $existingModelId = trim(strval($existingResult['model_id'] ?? ($existingResult['profile_id'] ?? '')));
            if ($existingModelId !== '') {
                return self::successResult($existingResult, 'NiwaAI 数字人克隆完成');
            }
            $slot = self::claimUpstreamSubmit(intval($task['id'] ?? 0), $existingResult);
            if (!$slot['ok']) {
                $locked = is_array($slot['result'] ?? null) ? $slot['result'] : $existingResult;
                $lockedModelId = trim(strval($locked['model_id'] ?? ($locked['profile_id'] ?? '')));
                if ($lockedModelId !== '') {
                    return self::successResult($locked, 'NiwaAI 数字人克隆完成');
                }
                return [
                    'status' => 'skipped',
                    'message' => '数字人克隆正在提交，请勿重复请求',
                    'task_id' => intval($task['id'] ?? 0),
                ];
            }
            $existingResult = $slot['result'];
            $response = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::cloneAvatar($provider, $payload);
            if (intval($response['status'] ?? 0) !== 1) {
                return self::failedResult(self::providerApiMessage($response, '数字人克隆失败'), self::withProviderCall([], $response, 'avatar.clone'));
            }
            $modelId = trim(strval($response['data'] ?? ''));
            if ($modelId === '' && is_array($response['data'] ?? null)) {
                $modelId = trim(strval($response['data']['model_id'] ?? ($response['data']['id'] ?? '')));
            }
            if ($modelId === '') {
                return self::failedResult('NiwaAI 未返回数字人模型ID', self::withProviderCall([], $response, 'avatar.clone'));
            }
            return self::successResult(self::withProviderCall(array_merge($existingResult, [
                'profile_id' => $modelId,
                'model_id' => $modelId,
                'data' => [
                    'model_id' => ctype_digit($modelId) ? intval($modelId) : $modelId,
                    'avatar_name' => $payload['name'],
                    'platform_code' => $payload['platform_code'],
                    'sex' => $payload['sex'],
                ],
                'provider' => 'niwaai',
            ]), $response, 'avatar.clone'), 'NiwaAI 数字人克隆完成');
        } catch (\Throwable $e) {
            return self::failedResult($e->getMessage());
        }
    }

    private static function executeZhuibaoAudio(int $aid, array $task, array $input, array $existingResult): array
    {
        $provider = self::getZhuibaoProvider($aid, 'tts');
        if (empty($provider)) {
            return self::failedResult('追爆插件的 NiwaAI 配音配置未启用');
        }

        $profile = DigitalAvatarService::resolveVoiceForSynthesis(
            $aid,
            intval($task['bid'] ?? 0),
            intval($task['member_id'] ?? 0),
            $input
        );
        $voiceId = trim(strval($profile['voice_id'] ?? ''));
        if ($voiceId === '') {
            $voiceId = trim(strval($input['resolved_voice_id'] ?? ($input['selected_voice_id'] ?? '')));
        }
        if ($voiceId === '') {
            $voiceId = trim(strval($provider['default_voice_id'] ?? ''));
        }
        if ($voiceId === '') {
            return self::failedResult('请先选择可用音色');
        }

        $text = trim(strval($input['text'] ?? ($input['content'] ?? '')));
        if ($text === '') {
            return self::failedResult('缺少可用于生成音频的文案内容');
        }

        $audioModelCode = trim(strval($input['resolved_voice_model_code'] ?? ''));
        if ($audioModelCode === '') {
            $audioModelCode = DigitalAvatarService::audioModelCodeForVoice($profile, strval($task['model_code'] ?? ''));
        }
        $audioInput = $input;
        $voiceServiceCode = trim(strval($profile['voice_service_code'] ?? ($input['voice_service_code'] ?? '')));
        if ($voiceServiceCode !== '') {
            $audioInput['voice_service_code'] = $voiceServiceCode;
        }
        $audioModelConfig = self::loadModelConfig($aid, 'audio', $audioModelCode);
        $spec = DigitalAvatarNiwaCatalog::resolve(
            $audioModelCode,
            array_merge($audioModelConfig, is_array($profile) ? $profile : []),
            $audioInput
        );
        $provider = self::withModelEndpoints($provider, $audioModelConfig, $spec);
        $voiceFamily = strval($spec['family'] ?? '');

        $audioId = trim(strval($existingResult['audio_id'] ?? ''));
        $existingAudioUrl = trim(strval($existingResult['audio_url'] ?? ''));
        $existingMergeStatus = intval($existingResult['merge_status'] ?? 0);
        $existingVoiceId = trim(strval($existingResult['voice_id'] ?? ''));
        if ($existingVoiceId !== '' && $existingVoiceId !== $voiceId) {
            $audioId = '';
            $existingAudioUrl = '';
            $existingMergeStatus = 0;
        }
        $data = [];
        $response = [];
        if ($existingAudioUrl !== '' && $existingMergeStatus === 2) {
            $audioUrl = $existingAudioUrl;
            $data = is_array($existingResult['data'] ?? null) ? $existingResult['data'] : [];
            $data['audio_id'] = $audioId;
            $data['audio_url'] = $audioUrl;
            $data['merge_status'] = 2;
            $response = ['status' => 1, 'msg' => '已使用已完成音频结果', 'data' => $data];
        } elseif ($audioId !== '') {
            $response = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::queryVoiceMergeStatus($provider, $audioId, $voiceFamily);
            if (intval($response['status'] ?? 0) !== 1) {
                return self::failedResult(self::providerApiMessage($response, '未知错误'), self::withProviderCall($existingResult, $response, 'voice.status'));
            }
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        } else {
            $payload = self::buildNiwaAudioPayload($provider, $profile, $audioInput, $voiceId, $text, $audioModelCode);
            $mergeApi = DigitalAvatarNiwaCatalog::mergeApi($spec);
            $response = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::mergeVoice($provider, $payload, $mergeApi);
            if (intval($response['status'] ?? 0) !== 1) {
                return self::failedResult(self::providerApiMessage($response, '未知错误'), self::withProviderCall($existingResult, $response, 'voice.merge'));
            }
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            if ($data === [] && is_scalar($response['data'] ?? null)) {
                $data['audio_id'] = strval($response['data']);
            }
            $audioId = trim(strval($data['audio_id'] ?? ''));
        }

        $audioUrl = trim(strval($data['audio_url'] ?? ''));
        $mergeStatus = intval($data['merge_status'] ?? 0);
        $result = self::withProviderCall(array_merge($existingResult, [
            'provider' => 'niwaai',
            'phase' => 'audio',
            'audio_id' => $audioId,
            'audio_url' => $audioUrl,
            'voice_id' => $voiceId,
            'voice_name' => trim(strval($profile['name'] ?? '')),
            'text' => $text,
            'merge_status' => $mergeStatus,
            'data' => self::limitValue($data),
        ]), $response, $audioId !== '' && $existingAudioUrl === '' ? 'voice.merge' : 'voice.status');

        if ($mergeStatus === 3) {
            return self::failedResult(trim(strval($data['err_msg'] ?? ($data['msg'] ?? '未知错误'))), $result);
        }
        if ($audioUrl !== '' && $mergeStatus === 2) {
            return self::successResult(array_merge($result, [
                'url' => $audioUrl,
                'assets' => [[
                    'url' => $audioUrl,
                    'asset_type' => 'audio',
                    'duration' => max(0, floatval($data['audio_duration'] ?? 0)),
                    'metadata' => ['source' => 'niwaai', 'audio_id' => $audioId],
                ]],
            ]), 'NiwaAI 音频生成完成');
        }
        if ($audioId === '') {
            return self::failedResult('NiwaAI 未返回音频任务ID', $result);
        }

        return [
            'status' => 'pending',
            'message' => 'NiwaAI 音频任务处理中',
            'remote_id' => $audioId,
            'progress' => 20,
            'result' => $result,
        ];
    }

    public static function synthesizeVoiceNow(int $aid, int $bid, int $memberId, array $input): array
    {
        $provider = self::getZhuibaoProvider($aid, 'tts');
        if (empty($provider)) {
            throw new \RuntimeException('追爆插件的 NiwaAI 配音配置未启用');
        }

        $profile = DigitalAvatarService::resolveVoiceForSynthesis($aid, $bid, $memberId, $input);
        $voiceId = trim(strval($profile['voice_id'] ?? ($input['resolved_voice_id'] ?? ($input['selected_voice_id'] ?? ''))));
        if ($voiceId === '') {
            throw new \RuntimeException('请先选择可用音色');
        }
        $text = trim(strval($input['text'] ?? ($input['content'] ?? ($input['prompt'] ?? ($input['script'] ?? '')))));
        if ($text === '') {
            throw new \RuntimeException('请先填写要试听的文案');
        }
        $input['text'] = $text;
        $input['content'] = $text;

        $audioModelCode = trim(strval($input['resolved_voice_model_code'] ?? ($input['model_code'] ?? '')));
        if ($audioModelCode === '') {
            $audioModelCode = DigitalAvatarService::audioModelCodeForVoice($profile, 'SPro');
        }
        $audioInput = $input;
        $voiceServiceCode = trim(strval($profile['voice_service_code'] ?? ($input['voice_service_code'] ?? '')));
        if ($voiceServiceCode !== '') {
            $audioInput['voice_service_code'] = $voiceServiceCode;
        }
        $audioModelConfig = self::loadModelConfig($aid, 'audio', $audioModelCode);
        $spec = DigitalAvatarNiwaCatalog::resolve(
            $audioModelCode,
            array_merge($audioModelConfig, is_array($profile) ? $profile : []),
            $audioInput
        );
        $provider = self::withModelEndpoints($provider, $audioModelConfig, $spec);
        $payload = self::buildNiwaAudioPayload($provider, $profile, $audioInput, $voiceId, $text, $audioModelCode);
        $mergeApi = DigitalAvatarNiwaCatalog::mergeApi($spec);
        $response = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::mergeVoice($provider, $payload, $mergeApi);
        if (intval($response['status'] ?? 0) !== 1) {
            throw new \RuntimeException(strval($response['msg'] ?? '未知错误'));
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if ($data === [] && is_scalar($response['data'] ?? null)) {
            $data['audio_id'] = strval($response['data']);
        }
        $audioId = trim(strval($data['audio_id'] ?? ''));
        $deadline = time() + 45;
        while (time() <= $deadline) {
            $audioUrl = trim(strval($data['audio_url'] ?? ($data['audio_oss_url'] ?? ($data['url'] ?? ($data['oss_url'] ?? '')))));
            $mergeStatus = intval($data['merge_status'] ?? 0);
            if ($mergeStatus === 3) {
                throw new \RuntimeException(trim(strval($data['err_msg'] ?? ($data['msg'] ?? '未知错误'))));
            }
            if ($audioUrl !== '') {
                return [
                    'audio_url' => $audioUrl,
                    'audio_id' => $audioId,
                    'voice_id' => $voiceId,
                    'voice_name' => trim(strval($profile['name'] ?? '')),
                    'duration' => max(0, floatval($data['audio_duration'] ?? 0)),
                    'model_code' => $audioModelCode,
                    'preview_text' => $text,
                ];
            }
            if ($audioId === '') {
                break;
            }
            usleep(500000);
            $response = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::queryVoiceMergeStatus($provider, $audioId, strval($spec['family'] ?? ''));
            if (intval($response['status'] ?? 0) !== 1) {
                throw new \RuntimeException(strval($response['msg'] ?? '未知错误'));
            }
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        }

        throw new \RuntimeException('配音仍在处理中，请稍后重试试听');
    }

    private static function claimUpstreamSubmit(int $taskId, array $existingResult): array
    {
        if ($taskId <= 0) {
            return ['ok' => true, 'result' => $existingResult];
        }
        Db::startTrans();
        try {
            $row = Db::name('digitalavatar_task')->where('id', $taskId)->lock(true)->find();
            $result = self::decodeJson($row['result_json'] ?? '');
            if (!is_array($result) || $result === []) {
                $result = $existingResult;
            }
            $hasRemote = trim(strval($result['video_id'] ?? '')) !== ''
                || self::existingImageTaskIds($result) !== [];
            $lockedAt = intval($result['submit_lock'] ?? 0);
            if ($hasRemote || ($lockedAt > 0 && time() - $lockedAt < 180)) {
                Db::commit();
                return ['ok' => false, 'result' => $result];
            }
            $result['submit_lock'] = time();
            Db::name('digitalavatar_task')->where('id', $taskId)->update([
                'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'update_time' => time(),
            ]);
            Db::commit();
            return ['ok' => true, 'result' => $result];
        } catch (\Throwable $e) {
            Db::rollback();
            return ['ok' => false, 'result' => $existingResult];
        }
    }

    private static function executeZhuibaoAvatarVideo(int $aid, array $task, array $input, array $existingResult): array
    {
        $presetAudioUrl = trim(strval($input['audio_url'] ?? ($input['existing_audio_url'] ?? ($input['audio_oss_url'] ?? ''))));
        $audioResult = [];
        $audioUrl = '';
        if ($presetAudioUrl !== '') {
            $audioUrl = $presetAudioUrl;
            $audioResult = [
                'audio_url' => $audioUrl,
                'merge_status' => 2,
                'source' => 'preset_audio',
                'phase' => 'audio',
            ];
        } else {
            $voiceExecution = self::executeZhuibaoAudio($aid, $task, $input, $existingResult);
            if (strval($voiceExecution['status'] ?? '') === 'failed') {
                return $voiceExecution;
            }
            $audioResult = is_array($voiceExecution['result'] ?? null) ? $voiceExecution['result'] : [];
            $audioUrl = trim(strval($audioResult['audio_url'] ?? ''));
            if (strval($voiceExecution['status'] ?? '') === 'pending' || $audioUrl === '') {
                $audioResult['phase'] = 'audio';
                return [
                    'status' => 'pending',
                    'message' => 'NiwaAI 音频任务处理中',
                    'remote_id' => trim(strval($audioResult['audio_id'] ?? ($voiceExecution['remote_id'] ?? ''))),
                    'progress' => 20,
                    'result' => $audioResult,
                ];
            }
        }

        $provider = self::getZhuibaoProvider($aid, 'avatar');
        if (empty($provider)) {
            return self::failedResult('追爆插件的 NiwaAI 数字人配置未启用', $audioResult);
        }

        $avatarProfile = DigitalAvatarService::resolveAvatarSelection(
            $aid,
            intval($task['bid'] ?? 0),
            intval($task['member_id'] ?? 0),
            $input
        );
        $modelId = trim(strval($avatarProfile['model_id'] ?? ''));
        $modelConfig = self::loadModelConfig($aid, 'avatar_video', strval($task['model_code'] ?? ''));
        $videoSpec = DigitalAvatarNiwaCatalog::resolve(strval($task['model_code'] ?? ''), $modelConfig, $input);
        $family = strval($videoSpec['family'] ?? 'vpro');
        $isXpro = $family === 'xpro' || DigitalAvatarService::isXproAvatar($avatarProfile);
        if ($modelId === '' && !$isXpro) {
            $modelId = trim(strval($provider['default_model_id'] ?? ''));
        }
        if ($modelId === '' && !$isXpro) {
            return self::failedResult('所选数字人尚未克隆成功（缺少拟娲形象ID）。请选择「默认数字人」或克隆成功的形象后再生成。');
        }
        $provider = self::withModelEndpoints($provider, $modelConfig, $videoSpec);
        $videoId = trim(strval($existingResult['video_id'] ?? ''));
        $searchId = trim(strval($existingResult['channel_search_id'] ?? ''));
        if ($videoId === '') {
            $slot = self::claimUpstreamSubmit(intval($task['id'] ?? 0), $existingResult);
            if (!$slot['ok']) {
                $existingResult = $slot['result'];
                $videoId = trim(strval($existingResult['video_id'] ?? ''));
                if ($videoId === '') {
                    return [
                        'status' => 'skipped',
                        'message' => '数字人视频正在提交，请勿重复请求',
                        'task_id' => intval($task['id'] ?? 0),
                    ];
                }
            } else {
                $existingResult = $slot['result'];
            }
            if ($searchId === '') {
                $searchId = trim(strval($existingResult['channel_search_id'] ?? ''));
            }
        }
        if ($searchId === '') {
            $searchId = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::buildChannelSearchId(
                trim(strval($provider['app_id'] ?? '')),
                intval($task['member_id'] ?? 0)
            );
        }

        $modelType = intval($avatarProfile['model_type'] ?? ($input['model_type'] ?? 0));
        if ($modelType !== 1 && $modelType !== 2) {
            $modelType = 2;
        }

        if ($videoId === '') {
            $mergeResult = ['status' => 0, 'msg' => '未提交数字人视频'];
            $submitted = false;
            if ($family === 'xpro') {
                $imgUrl = trim(strval($input['img_url'] ?? ($input['cover_url'] ?? ($avatarProfile['cover_url'] ?? ($avatarProfile['img'] ?? '')))));
                if ($imgUrl === '') {
                    return self::failedResult('XPro 数字人需要上传参考图', $audioResult);
                }
                $mergeResult = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::call($provider, 'avatar.xpro', [
                    'platform_code' => 'XPro',
                    'prompt' => trim(strval($input['prompt'] ?? ($input['text'] ?? ''))) ?: '数字人口播',
                    'img_url' => $imgUrl,
                    'audio_url' => $audioUrl,
                    'model' => trim(strval($input['xpro_model'] ?? ($videoSpec['model'] ?? 'XPro1.0'))) ?: 'XPro1.0',
                    'channel_search_id' => $searchId,
                ]);
                $submitted = true;
            } elseif ($family === 'max_avatar' && self::avatarSupportsMaxSubmit($avatarProfile)) {
                $mergeResult = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::call($provider, 'avatar.max', [
                    'platform_code' => 'Max',
                    'model' => trim(strval($input['max_avatar_model'] ?? ($videoSpec['model'] ?? 'Max1.0'))) ?: 'Max1.0',
                    'audio_url' => $audioUrl,
                    'model_id' => ctype_digit(strval($modelId)) ? intval($modelId) : $modelId,
                    'model_type' => $modelType,
                    'channel_search_id' => $searchId,
                ]);
                $submitted = true;
                if (intval($mergeResult['status'] ?? 0) !== 1 && self::isMissingNiwaAvatarError(strval($mergeResult['msg'] ?? ''))) {
                    $audioResult['max_submit_error'] = strval($mergeResult['msg'] ?? '');
                    $submitted = false;
                }
            }
            if (!$submitted) {
                $platformCode = trim(strval($avatarProfile['platform_code'] ?? ''));
                if ($platformCode === '' || strcasecmp($platformCode, 'Max') === 0) {
                    $platformCode = trim(strval($videoSpec['platform_code'] ?? 'VPro')) ?: 'VPro';
                }
                if (strcasecmp($platformCode, 'Max') === 0) {
                    $platformCode = 'VPro';
                }
                $mergeResult = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::mergeAvatarVideo($provider, [
                    'voice_type' => 2,
                    'model_id' => ctype_digit($modelId) ? intval($modelId) : $modelId,
                    'audio_oss_url' => $audioUrl,
                    'model_type' => $modelType,
                    'channel_search_id' => $searchId,
                    'platform_code' => $platformCode,
                ]);
            }
            if (intval($mergeResult['status'] ?? 0) !== 1) {
                return self::failedResult(self::providerApiMessage($mergeResult, '未知错误'), self::withProviderCall($audioResult, $mergeResult, 'avatar.merge'));
            }
            $videoId = trim(strval($mergeResult['data'] ?? ''));
            if ($videoId === '' && is_array($mergeResult['data'] ?? null)) {
                $videoId = trim(strval($mergeResult['data']['video_id'] ?? ($mergeResult['data']['id'] ?? '')));
            }
            if ($videoId === '') {
                return self::failedResult('NiwaAI 未返回数字人视频任务ID', self::withProviderCall($audioResult, $mergeResult, 'avatar.merge'));
            }
            $audioResult = self::withProviderCall($audioResult, $mergeResult, 'avatar.merge');
        }

        $statusResult = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::queryAvatarVideoStatus($provider, $videoId, $family);
        if (intval($statusResult['status'] ?? 0) !== 1 && $searchId !== '') {
            $statusResult = \app\plugin\zhuibaoagent\common\ZhuibaoAvatarVoiceApi::queryAvatarVideoStatus($provider, $searchId, $family);
        }
        if (intval($statusResult['status'] ?? 0) !== 1) {
            return self::failedResult(self::providerApiMessage($statusResult, '未知错误'), self::withProviderCall($audioResult, $statusResult, 'avatar.status'));
        }

        $data = is_array($statusResult['data'] ?? null) ? $statusResult['data'] : [];
        $videoUrl = trim(strval($data['video_url'] ?? ''));
        $coverUrl = trim(strval($data['cover_url'] ?? ''));
        $mergeStatus = intval($data['merge_status'] ?? 0);
        $result = self::withProviderCall(array_merge($audioResult, [
            'provider' => 'niwaai',
            'phase' => 'video',
            'video_id' => trim(strval($data['video_id'] ?? $videoId)),
            'video_url' => $videoUrl,
            'cover_url' => $coverUrl,
            'model_id' => $modelId,
            'avatar_name' => trim(strval($avatarProfile['name'] ?? '')),
            'channel_search_id' => $searchId,
            'merge_status' => $mergeStatus,
            'data' => self::limitValue($data),
        ]), $statusResult, 'avatar.status');

        if ($mergeStatus === 3) {
            return self::failedResult(trim(strval($data['fail_reason'] ?? ($data['msg'] ?? '未知错误'))), $result);
        }
        if ($videoUrl !== '' && $mergeStatus === 2) {
            return self::successResult(array_merge($result, [
                'url' => $videoUrl,
                'assets' => [[
                    'url' => $videoUrl,
                    'asset_type' => 'video',
                    'metadata' => ['source' => 'niwaai', 'video_id' => $videoId, 'cover_url' => $coverUrl],
                ]],
            ]), 'NiwaAI 数字人视频生成完成');
        }

        return [
            'status' => 'pending',
            'message' => 'NiwaAI 数字人视频处理中',
            'remote_id' => $videoId,
            'progress' => 60,
            'result' => $result,
        ];
    }

    private static function avatarSupportsMaxSubmit(array $avatar): bool
    {
        $modelId = trim(strval($avatar['model_id'] ?? ($avatar['resource_id'] ?? '')));
        if ($modelId === '' || $modelId === '25010') {
            return false;
        }
        $owner = strtolower(trim(strval($avatar['owner_type'] ?? '')));
        if ($owner === 'platform') {
            return false;
        }
        return true;
    }

    private static function isMissingNiwaAvatarError(string $message): bool
    {
        return $message !== '' && (strpos($message, '模型不存在') !== false || strpos($message, '公模不存在') !== false);
    }

    private static function buildNiwaAudioPayload(array $provider, array $profile, array $input, string $voiceId, string $text, string $modelCode = ''): array
    {
        if (class_exists('app\\plugin\\digitalavatar\\common\\DigitalAvatarNiwaCatalog')) {
            $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, is_array($profile) ? $profile : [], $input);
            if (in_array(strval($spec['family'] ?? ''), ['vpro', 'pro_avatar', 'max_avatar', 'xpro'], true)) {
                $spec = DigitalAvatarNiwaCatalog::resolve(
                    trim(strval($input['tts_model_code'] ?? 'SPro')),
                    [],
                    $input
                );
            }
            return DigitalAvatarNiwaCatalog::buildAudioPayload($spec, $profile, $input, $voiceId, $text);
        }

        $language = trim(strval($profile['language'] ?? ($input['language'] ?? 'zh'))) ?: 'zh';
        $instruction = trim(strval($profile['instruction'] ?? ($input['instruction'] ?? '请用自然平稳的语气说话。'))) ?: '请用自然平稳的语气说话。';
        $speechRate = floatval($input['speech_rate'] ?? ($input['speed'] ?? 1.0));
        $pitchRate = floatval($input['pitch_rate'] ?? ($input['pitch'] ?? 1.0));
        $profileModelType = intval($profile['model_type'] ?? 0);
        $modelType = $profileModelType === 2 ? 2 : ($profileModelType === 1 ? 1 : intval($input['voice_model_type'] ?? 1));
        $modelType = $modelType === 2 ? 2 : 1;
        $volume = floatval($input['volume'] ?? 60);
        if ($volume > 0 && $volume <= 3) {
            $volume = intval(round($volume * 50));
        }
        return [
            'voice_id' => ctype_digit($voiceId) ? intval($voiceId) : $voiceId,
            'model_type' => $modelType,
            'text' => $text,
            'format' => trim(strval($input['format'] ?? 'WAV_16000HZ_MONO_16BIT')) ?: 'WAV_16000HZ_MONO_16BIT',
            'volume' => max(0, min(100, intval($volume ?: 60))),
            'speech_rate' => $speechRate > 0 ? min(3, max(0.5, $speechRate)) : 1.0,
            'pitch_rate' => $pitchRate > 0 ? min(3, max(0.5, $pitchRate)) : 1.0,
            'bit_rate' => max(1, intval($input['bit_rate'] ?? 32)),
            'enable_word_timestamp' => false,
            'seed' => 0,
            'language_hints' => [$language],
            'instruction' => $instruction,
            'enable_aigc_tag' => false,
            'aigc_propagator' => '',
            'aigc_propagate_id' => '',
            'hot_fix' => '',
            'enable_markdown_filter' => false,
        ];
    }

    private static function successResult(array $result, string $message): array
    {
        return [
            'status' => 'success',
            'message' => $message,
            'result' => $result,
        ];
    }

    private static function withProviderCall(array $result, array $response, string $action = ''): array
    {
        $request = is_array($response['request'] ?? null) ? $response['request'] : [];
        if ($request === [] && trim(strval($response['msg'] ?? '')) === '已使用已完成音频结果') {
            return $result;
        }
        $apiBody = self::providerApiBody($response);
        $call = [
            'action' => $action,
            'time' => date('Y-m-d H:i:s'),
            'http_code' => intval($response['http_code'] ?? 0),
            'request' => $request,
            'response' => $apiBody,
        ];
        $calls = is_array($result['provider_calls'] ?? null) ? $result['provider_calls'] : [];
        $calls[] = $call;
        $result['provider_calls'] = $calls;
        $result['provider_request'] = $request;
        $result['provider_response'] = $apiBody;
        return $result;
    }

    private static function providerApiBody(array $response)
    {
        $raw = trim(strval($response['raw'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return mb_substr($raw, 0, 4000);
        }
        if (is_array($response['data'] ?? null)) {
            $data = $response['data'];
            if (array_key_exists('code', $data) || array_key_exists('msg', $data) || array_key_exists('data', $data)) {
                return $data;
            }
        }
        if (array_key_exists('code', $response) && (array_key_exists('msg', $response) || array_key_exists('data', $response))) {
            return [
                'code' => $response['code'],
                'msg' => $response['msg'] ?? '',
                'data' => $response['data'] ?? [],
            ];
        }
        if (is_array($response['data'] ?? null)) {
            return $response['data'];
        }
        $copy = $response;
        unset($copy['request'], $copy['raw']);
        return $copy;
    }

    private static function explainOpenAiImageError(string $providerName, string $host, string $modelName, string $raw, int $httpCode = 0): string
    {
        $prefix = $providerName . '（' . $host . ' / ' . $modelName . '）';
        $raw = trim($raw);
        if (preg_match('/剩余额度[:：]?\s*[＄$]?\s*([0-9.]+).*需要预扣费额度[:：]?\s*[＄$]?\s*([0-9.]+)/u', $raw, $match)
            || preg_match('/insufficient_user_quota|预扣费额度失败/u', $raw)) {
            $remain = isset($match[1]) ? '$' . number_format((float) $match[1], 2, '.', '') : '';
            $need = isset($match[2]) ? '$' . number_format((float) $match[2], 2, '.', '') : '';
            $wallet = ($remain !== '' && $need !== '')
                ? ('用户钱包剩余 ' . $remain . '，本张预扣 ' . $need)
                : '用户钱包额度不足';
            return $prefix . $wallet . '。扣的是「用户额度/钱包」，不是令牌页上的剩余；请到对应平台钱包充值后再试';
        }
        if ($httpCode === 0 || preg_match('/timed out|timeout|无响应/i', $raw)) {
            return $prefix . '生图接口超时无响应，请稍后重试';
        }
        return $prefix . $raw;
    }

    private static function providerApiMessage(array $response, string $fallback = '接口返回失败'): string
    {
        $body = self::providerApiBody($response);
        if (is_array($body)) {
            foreach (['msg', 'message', 'error_msg', 'fail_reason', 'error'] as $key) {
                $value = $body[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
                if (is_array($value) && is_string($value['message'] ?? null) && trim($value['message']) !== '') {
                    return trim($value['message']);
                }
            }
        } elseif (is_string($body) && trim($body) !== '') {
            return trim($body);
        }
        $msg = trim(strval($response['msg'] ?? ''));
        if ($msg !== '' && !in_array(strtolower($msg), ['success', 'ok', 'true'], true)) {
            return $msg;
        }
        return $fallback;
    }

    public static function testConnection(int $aid, string $providerKey): array
    {
        $provider = self::getRawConfig($aid, $providerKey);
        if (!$provider) {
            throw new \RuntimeException('提供商未配置或已停用');
        }

        $extra = self::decodeExtra($provider['extra_json'] ?? '');
        $path = trim(strval($extra['health_endpoint'] ?? ''));
        if ($path === '') {
            $path = '/health';
        }
        return self::requestJson($provider, $path, [], 'GET', $extra);
    }

    public static function formatConfig(array $row, bool $maskSecrets = true): array
    {
        $extra = self::decodeExtra($row['extra_json'] ?? '');
        $extra['model_endpoints'] = DigitalAvatarNiwaCatalog::mergeEndpointConfig($extra['model_endpoints'] ?? []);
        $result = [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'provider_key' => strval($row['provider_key'] ?? ''),
            'provider_name' => strval($row['provider_name'] ?? ''),
            'base_url' => strval($row['base_url'] ?? ''),
            'status' => intval($row['status'] ?? 0),
            'extra' => $extra,
            'endpoint_rows' => DigitalAvatarNiwaCatalog::endpointRows(),
            'has_api_key' => trim(strval($row['api_key'] ?? '')) !== '' ? 1 : 0,
            'has_api_secret' => trim(strval($row['api_secret'] ?? '')) !== '' ? 1 : 0,
            'has_callback_secret' => trim(strval($row['callback_secret'] ?? '')) !== '' ? 1 : 0,
            'create_time' => intval($row['create_time'] ?? 0),
            'update_time' => intval($row['update_time'] ?? 0),
        ];

        if ($maskSecrets) {
            $result['api_key'] = self::maskSecret(strval($row['api_key'] ?? ''));
            $result['api_secret'] = self::maskSecret(strval($row['api_secret'] ?? ''));
            $result['callback_secret'] = self::maskSecret(strval($row['callback_secret'] ?? ''));
        } else {
            $result['api_key'] = strval($row['api_key'] ?? '');
            $result['api_secret'] = strval($row['api_secret'] ?? '');
            $result['callback_secret'] = strval($row['callback_secret'] ?? '');
        }

        return $result;
    }

    public static function decodeExtra($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function encodeExtra($value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            $value = [];
        }
        $value['model_endpoints'] = DigitalAvatarNiwaCatalog::mergeEndpointConfig($value['model_endpoints'] ?? []);
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function normalizeKey(string $value): string
    {
        return strtolower(trim($value));
    }

    private static function defaultModelForTask(string $taskType): string
    {
        $map = [
            'avatar_video' => 'default-avatar-video',
            'video' => 'default-video',
            'voice_clone' => 'default-voice-clone',
            'avatar_clone' => 'default-avatar-clone',
            'audio' => 'default-tts',
            'image' => 'imageA',
        ];
        return $map[$taskType] ?? 'default-model';
    }

    private static function maskSecret(string $value): string
    {
        $value = trim($value);
        $length = strlen($value);
        if ($length <= 4) {
            return $length > 0 ? '****' : '';
        }
        if ($length <= 8) {
            return substr($value, 0, 2) . '****' . substr($value, -2);
        }
        return substr($value, 0, 3) . '****' . substr($value, -3);
    }

    private static function withEndpointOverrides(int $aid, array $provider, string $hintKey = ''): array
    {
        $row = self::getDigitalProviderRow($aid, $hintKey);
        if (!$row) {
            return $provider;
        }
        $extra = self::decodeExtra($row['extra_json'] ?? '');
        $provider['endpoint_overrides'] = DigitalAvatarNiwaCatalog::buildEndpointOverrides($extra);
        return $provider;
    }

    private static function getDigitalProviderRow(int $aid, string $hintKey = ''): array
    {
        $keys = [];
        $hintKey = self::normalizeKey($hintKey);
        if ($hintKey !== '') {
            $keys[] = $hintKey;
        }
        foreach (['niwaai', 'zhuibao', 'tts', 'avatar'] as $key) {
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }
        foreach ($keys as $key) {
            $row = self::getRawConfig($aid, $key);
            if ($row) {
                return $row;
            }
        }
        $row = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('status', 1)
            ->order('id asc')
            ->find();
        return is_array($row) ? $row : [];
    }

    private static function getRawConfig(int $aid, string $providerKey): array
    {
        $providerKey = self::normalizeKey($providerKey);
        if ($providerKey === '') {
            return [];
        }
        $row = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('provider_key', $providerKey)
            ->where('status', 1)
            ->find();
        return is_array($row) ? $row : [];
    }

    private static function resolveEndpoint($endpoints, string $taskType, string $fallback): string
    {
        if (is_array($endpoints) && isset($endpoints[$taskType])) {
            return trim(strval($endpoints[$taskType]));
        }
        if (is_string($endpoints) && trim($endpoints) !== '') {
            return trim($endpoints);
        }
        return trim($fallback);
    }

    private static function requestJson(array $provider, string $path, array $payload, string $method, array $extra = []): array
    {
        $baseUrl = trim(strval($provider['base_url'] ?? ''));
        if ($baseUrl === '' || !preg_match('#^https?://#i', $baseUrl)) {
            return ['status' => 0, 'msg' => '提供商接口地址必须是 http(s) URL'];
        }
        if (preg_match('#^https?://#i', $path)) {
            $url = $path;
        } else {
            $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        }

        $method = strtoupper(trim($method)) ?: 'POST';
        $timeout = max(1, min(600, intval($extra['timeout'] ?? self::DEFAULT_TIMEOUT)));
        $connectTimeout = max(1, min(60, intval($extra['connect_timeout'] ?? 15)));
        $headers = ['Accept: application/json'];
        $authType = strtolower(trim(strval($extra['auth_type'] ?? 'bearer')));
        $apiKey = trim(strval($provider['api_key'] ?? ''));
        $apiSecret = trim(strval($provider['api_secret'] ?? ''));

        if ($authType === 'bearer' && $apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        } elseif ($authType === 'x-api-key' && $apiKey !== '') {
            $headers[] = 'X-API-Key: ' . $apiKey;
        } elseif ($authType === 'header' && $apiKey !== '') {
            $headerName = trim(strval($extra['auth_header'] ?? 'Authorization'));
            $headers[] = ($headerName !== '' ? $headerName : 'Authorization') . ': ' . $apiKey;
        } elseif ($authType === 'basic' && ($apiKey !== '' || $apiSecret !== '')) {
            $headers[] = 'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret);
        }

        $customHeaders = $extra['headers'] ?? [];
        if (is_array($customHeaders)) {
            foreach ($customHeaders as $name => $value) {
                if (is_int($name)) {
                    $headers[] = trim(strval($value));
                } elseif (trim(strval($name)) !== '') {
                    $headers[] = trim(strval($name)) . ': ' . trim(strval($value));
                }
            }
        }

        $body = '';
        if ($method === 'GET' || $method === 'HEAD') {
            if (!empty($payload)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($payload);
            }
        } else {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!function_exists('curl_init')) {
            return ['status' => 0, 'msg' => '服务器未启用 cURL'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($method !== 'GET' && $method !== 'HEAD') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($errno) {
            return [
                'status' => 0,
                'msg' => $error !== '' ? $error : '提供商请求失败',
                'http_code' => $httpCode,
                'raw' => is_string($raw) ? mb_substr($raw, 0, 4000) : '',
            ];
        }

        $raw = strval($raw);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [
                'status' => 0,
                'msg' => $raw !== '' ? mb_substr($raw, 0, 2000) : '提供商返回非 JSON',
                'http_code' => $httpCode,
                'raw' => mb_substr($raw, 0, 4000),
            ];
        }
        $apiMsg = trim(strval($data['msg'] ?? ($data['message'] ?? ($data['error_msg'] ?? ''))));
        if ($apiMsg === '' && is_string($data['error'] ?? null)) {
            $apiMsg = trim(strval($data['error']));
        }
        if ($httpCode >= 400) {
            return [
                'status' => 0,
                'msg' => $apiMsg !== '' ? $apiMsg : (self::readFirstPath($data, ['error.message', 'error', 'message']) ?: $raw),
                'code' => $data['code'] ?? $data['status'] ?? $httpCode,
                'http_code' => $httpCode,
                'data' => $data,
                'raw' => $raw,
            ];
        }
        return [
            'status' => 1,
            'msg' => $apiMsg !== '' ? $apiMsg : 'success',
            'code' => $data['code'] ?? $data['status'] ?? 1,
            'http_code' => $httpCode,
            'data' => $data,
            'raw' => $raw,
        ];
    }

    private static function readFirstPath(array $data, array $paths)
    {
        foreach ($paths as $path) {
            $path = trim(strval($path));
            if ($path === '') {
                continue;
            }
            $value = self::readPath($data, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    private static function readPath($data, string $path)
    {
        if ($path === '') {
            return null;
        }
        $current = $data;
        foreach (explode('.', $path) as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                return null;
            }
        }
        return $current;
    }

    private static function isSuccessStatus($value): bool
    {
        if ($value === true) {
            return true;
        }
        if (is_numeric($value) && in_array(intval($value), [2, 200], true)) {
            return true;
        }
        return in_array(strtolower(trim(strval($value))), [
            'success',
            'succeeded',
            'completed',
            'complete',
            'done',
            'finished',
            'successed',
        ], true);
    }

    private static function isFailedStatus($value): bool
    {
        if ($value === false) {
            return true;
        }
        if (is_numeric($value) && in_array(intval($value), [0, 3, 400, 401, 403, 404, 500], true)) {
            return true;
        }
        return in_array(strtolower(trim(strval($value))), [
            'failed',
            'failure',
            'error',
            'canceled',
            'cancelled',
            'rejected',
        ], true);
    }

    private static function normalizeProgress($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $progress = floatval($value);
        if ($progress > 0 && $progress <= 1) {
            $progress *= 100;
        }
        return max(0, min(99, intval(round($progress))));
    }

    private static function failedResult(string $message, array $result = []): array
    {
        return [
            'status' => 'failed',
            'message' => $message,
            'error' => $message,
            'result' => $result,
        ];
    }

    private static function limitResponse(array $response): array
    {
        $result = $response;
        if (isset($result['raw'])) {
            $result['raw'] = mb_substr(strval($result['raw']), 0, 4000);
        }
        return $result;
    }

    private static function extractAssets($body, string $taskType): array
    {
        $candidates = [];
        if (is_array($body)) {
            foreach (['assets', 'files', 'outputs', 'images', 'videos', 'audios'] as $key) {
                if (isset($body[$key]) && is_array($body[$key])) {
                    $candidates = array_merge($candidates, $body[$key]);
                }
            }
            if (isset($body['data']) && is_array($body['data'])) {
                foreach (['assets', 'files', 'outputs', 'images', 'videos', 'audios'] as $key) {
                    if (isset($body['data'][$key]) && is_array($body['data'][$key])) {
                        $candidates = array_merge($candidates, $body['data'][$key]);
                    }
                }
            }
        }

        $assets = [];
        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $url = trim($candidate);
                if ($url !== '') {
                    $assets[] = [
                        'url' => $url,
                        'asset_type' => self::assetTypeForTask($taskType),
                        'metadata' => ['source' => 'provider'],
                    ];
                }
                continue;
            }
            if (!is_array($candidate)) {
                continue;
            }
            $url = trim(strval($candidate['url'] ?? ($candidate['src'] ?? ($candidate['file_url'] ?? ($candidate['download_url'] ?? '')))));
            if ($url === '') {
                continue;
            }
            $assets[] = [
                'url' => $url,
                'asset_type' => trim(strval($candidate['asset_type'] ?? $candidate['type'] ?? self::assetTypeForTask($taskType))),
                'name' => trim(strval($candidate['name'] ?? $candidate['filename'] ?? '')),
                'mime_type' => trim(strval($candidate['mime_type'] ?? $candidate['mime'] ?? '')),
                'size' => max(0, intval($candidate['size'] ?? 0)),
                'duration' => max(0, floatval($candidate['duration'] ?? 0)),
                'metadata' => self::limitValue($candidate),
            ];
        }
        return $assets;
    }

    private static function assetTypeForTask(string $taskType): string
    {
        if ($taskType === 'audio' || $taskType === 'voice_clone') {
            return 'audio';
        }
        if ($taskType === 'image') {
            return 'image';
        }
        return 'video';
    }

    private static function limitValue($value, int $depth = 0)
    {
        if ($depth > 5) {
            return null;
        }
        if (is_array($value)) {
            $result = [];
            foreach (array_slice($value, 0, 100, true) as $key => $item) {
                $result[is_int($key) ? $key : mb_substr(strval($key), 0, 80)] = self::limitValue($item, $depth + 1);
            }
            return $result;
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 12000);
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        return strval($value);
    }

    private static function decodeJson($value): array
    {
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }
}
