<?php
namespace app\command;

use app\admin\common\AsyncWorkerLogService;
use app\plugin\digitalavatar\common\DigitalAvatarInstaller;
use app\plugin\digitalavatar\common\DigitalAvatarTaskService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;

/**
 * Community-CE slim async worker.
 * Only runs digitalavatar database queue. Full mall AsyncWork is intentionally not shipped.
 */
class AsyncWork extends Command
{
    private const IDLE_LOG_INTERVAL = 600;

    protected function configure()
    {
        $this->setName('async:work')
            ->addOption('once', null, Option::VALUE_NONE, 'Process one loop and exit')
            ->addOption('sleep', null, Option::VALUE_REQUIRED, 'Idle sleep seconds', '1')
            ->addOption('digitalavatar-interval', null, Option::VALUE_REQUIRED, 'Digital avatar database queue interval seconds', '3')
            ->addOption('digitalavatar-limit', null, Option::VALUE_REQUIRED, 'Digital avatar database queue batch size', '5')
            ->setDescription('Community-CE digitalavatar async worker');
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $once = (bool)$input->getOption('once');
        $sleep = max(1, min(30, intval($input->getOption('sleep') ?? 1)));
        $interval = max(2, min(300, intval($input->getOption('digitalavatar-interval') ?? 3)));
        $limit = max(1, min(50, intval($input->getOption('digitalavatar-limit') ?? 5)));

        $output->writeln('<info>Community-CE async:work started (digitalavatar only)</info>');
        $lastAt = 0;
        $lastIdleLogAt = 0;

        do {
            $processed = 0;
            $now = time();
            if ($now - $lastAt >= $interval) {
                $lastAt = $now;
                $processed += $this->processDigitalAvatarQueue($limit, $output);
            }

            if ($processed <= 0) {
                if ($now - $lastIdleLogAt >= self::IDLE_LOG_INTERVAL) {
                    $lastIdleLogAt = $now;
                    $output->writeln('<comment>idle: waiting for digitalavatar tasks...</comment>');
                }
                if (!$once) {
                    sleep($sleep);
                }
            }
        } while (!$once);

        return 0;
    }

    private function processDigitalAvatarQueue(int $limit, Output $output): int
    {
        if (!class_exists('\\app\\plugin\\digitalavatar\\common\\DigitalAvatarTaskService')) {
            $output->writeln('<error>DigitalAvatarTaskService missing</error>');
            return 0;
        }

        $startedAt = microtime(true);
        try {
            if (class_exists('\\app\\plugin\\digitalavatar\\common\\DigitalAvatarInstaller')) {
                DigitalAvatarInstaller::ensureInstalled();
            }
            $result = DigitalAvatarTaskService::processQueue($limit);
            $count = intval($result['total'] ?? $result['processed'] ?? $result['count'] ?? 0);
            if ($count > 0) {
                $output->writeln(sprintf('<info>digitalavatar processed=%d</info>', $count));
            }
            if (class_exists('\\app\\admin\\common\\AsyncWorkerLogService')) {
                AsyncWorkerLogService::record([
                    'status' => 'success',
                    'message' => 'digitalavatar_task',
                    'task_type' => 'digitalavatar_task',
                    'queue_key' => 'database:digitalavatar_task',
                    'processed' => $count,
                    'duration_ms' => intval((microtime(true) - $startedAt) * 1000),
                    'result' => $result,
                ]);
            }
            return max(0, $count);
        } catch (\Throwable $e) {
            $output->writeln('<error>digitalavatar queue error: ' . $e->getMessage() . '</error>');
            Log::write([
                'cmd' => 'async:work',
                'queue' => 'database:digitalavatar_task',
                'error' => $e->getMessage(),
            ]);
            if (class_exists('\\app\\admin\\common\\AsyncWorkerLogService')) {
                AsyncWorkerLogService::record([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'task_type' => 'digitalavatar_task',
                    'queue_key' => 'database:digitalavatar_task',
                    'duration_ms' => intval((microtime(true) - $startedAt) * 1000),
                ]);
            }
            return 0;
        }
    }
}
