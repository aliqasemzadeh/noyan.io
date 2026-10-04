<?php

namespace App\Services\System;

use App\Enums\System\UpdateMode;
use Closure;

class ProjectUpdater
{
    public function __construct(
        public SystemCommandRunner $runner,
    ) {}

    /**
     * @param  Closure(string): void  $onOutput
     */
    public function run(UpdateMode $mode, Closure $onOutput): int
    {
        $onOutput('=== '.__('general.system_update_started', ['mode' => __('general.'.$mode->value.'_update')]).' ==='.PHP_EOL);

        if ($this->runner->runProcess(['git', 'pull'], $onOutput, 'git pull') !== 0) {
            $onOutput(PHP_EOL.__('general.system_update_failed').PHP_EOL);

            return 1;
        }

        if ($mode === UpdateMode::Full) {
            $composerExit = $this->runner->runProcess(
                [$this->composerCommand(), 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
                $onOutput,
                $this->composerCommand().' install --no-dev --optimize-autoloader --no-interaction',
            );

            if ($composerExit !== 0) {
                $onOutput(PHP_EOL.__('general.system_update_failed').PHP_EOL);

                return $composerExit;
            }
        }

        $migrateExit = $this->runner->artisan(
            ['migrate', '--force'],
            $onOutput,
            'php artisan migrate --force',
        );

        if ($migrateExit !== 0) {
            $onOutput(PHP_EOL.__('general.system_update_failed').PHP_EOL);

            return $migrateExit;
        }

        foreach (['cache:clear', 'route:clear', 'view:clear', 'config:clear'] as $command) {
            $exitCode = $this->runner->artisan(
                [$command],
                $onOutput,
                'php artisan '.$command,
            );

            if ($exitCode !== 0) {
                $onOutput(PHP_EOL.__('general.system_update_failed').PHP_EOL);

                return $exitCode;
            }
        }

        if ($mode === UpdateMode::Full) {
            $npmExit = $this->runner->runProcess(
                [$this->npmCommand(), 'run', 'build'],
                $onOutput,
                $this->npmCommand().' run build',
            );

            if ($npmExit !== 0) {
                $onOutput(PHP_EOL.__('general.system_update_failed').PHP_EOL);

                return $npmExit;
            }
        }

        $queueExit = $this->runner->artisan(
            ['queue:restart'],
            $onOutput,
            'php artisan queue:restart',
        );

        if ($queueExit !== 0) {
            $onOutput(PHP_EOL.__('general.system_update_failed').PHP_EOL);

            return $queueExit;
        }

        $onOutput(PHP_EOL.'=== '.__('general.system_update_finished').' ==='.PHP_EOL);

        return 0;
    }

    protected function npmCommand(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
    }

    protected function composerCommand(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'composer.bat' : 'composer';
    }
}
