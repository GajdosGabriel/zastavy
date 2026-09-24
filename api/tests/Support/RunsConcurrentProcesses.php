<?php

namespace Tests\Support;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

trait RunsConcurrentProcesses
{
    protected function race(array ...$jobs): array
    {
        $cfg = config('database.connections.mysql');
        $env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $cfg['host'], 'DB_PORT' => (string) $cfg['port'], 'DB_DATABASE' => $cfg['database'],
            'DB_USERNAME' => $cfg['username'], 'DB_PASSWORD' => $cfg['password'] ?? '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        $processes = [];
        $directory = sys_get_temp_dir().'/stock-race-'.Str::uuid();
        mkdir($directory);
        $release = $directory.'/go';
        try {
            foreach ($jobs as $index => $job) {
                $job += ['ready' => $directory.'/ready-'.$index, 'release' => $release, 'result' => $directory.'/result-'.$index];
                $process = new Process([PHP_BINARY, base_path($job['worker'] ?? 'tests/Support/stock-worker.php'), json_encode($job)], base_path(), $env, null, 25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            foreach ($processes as $index => $process) {
                while (! file_exists($directory.'/ready-'.$index)) {
                    if (! $process->isRunning() || microtime(true) > $deadline) {
                        $this->fail('Worker did not reach barrier: '.$process->getErrorOutput().$process->getOutput());
                    }
                    usleep(10000);
                    clearstatcache();
                }
            }
            file_put_contents($release, 'go');
            $results = [];
            foreach ($processes as $index => $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $this->assertFileExists($directory.'/result-'.$index, $process->getErrorOutput().$process->getOutput());
                $results[] = json_decode(file_get_contents($directory.'/result-'.$index), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
