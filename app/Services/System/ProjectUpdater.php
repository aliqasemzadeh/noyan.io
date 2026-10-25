<?php

namespace App\Services\System;

use App\Enums\System\UpdateMode;
use App\Support\System\AppVersion;
use Closure;

class ProjectUpdater
{
    public function __construct(
        public SystemCommandRunner $runner,
        public VersionUpdateRegistry $versionUpdates,
    ) {}

    /**
     * @param  Closure(string): void  $onOutput
     */
    public function run(UpdateMode $mode, Closure $onOutput): int
    {
        $target = AppVersion::current();
        $installed = AppVersion::installed();

        $onOutput('=== '.__('general.system_update_started', ['mode' => __('general.'.$mode->value.'_update')]).' ==='.PHP_EOL);
        $onOutput(__('general.system_update_version_line', [
            'installed' => $installed,
            'target' => $target,
        ]).PHP_EOL);

        if ($this->runner->runProcess(['git', 'pull'], $onOutput, 'git pull') !== 0) {
            return $this->fail($onOutput);
        }

        if ($mode === UpdateMode::Full) {
            $composerExit = $this->runner->runProcess(
                [$this->composerCommand(), 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
                $onOutput,
                $this->composerCommand().' install --no-dev --optimize-autoloader --no-interaction',
            );

            if ($composerExit !== 0) {
                return $this->fail($onOutput, $composerExit);
            }
        }

        // Always keep schema + permissions current.
        if ($this->artisan($onOutput, ['migrate', '--force']) !== 0) {
            return $this->fail($onOutput);
        }

        if ($this->artisan($onOutput, ['permissions:sync']) !== 0) {
            return $this->fail($onOutput);
        }

        if ($this->runPendingVersionUpdates($onOutput) !== 0) {
            return $this->fail($onOutput);
        }

        if ($this->artisan($onOutput, ['optimize:clear']) !== 0) {
            return $this->fail($onOutput);
        }

        $npmExit = $this->runner->runProcess(
            [$this->npmCommand(), 'run', 'build'],
            $onOutput,
            $this->npmCommand().' run build',
        );

        if ($npmExit !== 0) {
            return $this->fail($onOutput, $npmExit);
        }

        if ($this->artisan($onOutput, ['queue:restart']) !== 0) {
            return $this->fail($onOutput);
        }

        $onOutput(PHP_EOL.'=== '.__('general.system_update_finished').' ==='.PHP_EOL);
        $onOutput(__('general.system_update_version_line', [
            'installed' => AppVersion::installed(),
            'target' => AppVersion::current(),
        ]).PHP_EOL);

        return 0;
    }

    /**
     * @param  Closure(string): void  $onOutput
     */
    public function runPendingVersionUpdates(Closure $onOutput): int
    {
        $pending = $this->versionUpdates->pending();

        if ($pending === []) {
            $onOutput(__('general.system_update_no_pending_releases').PHP_EOL);

            // Keep installed marker aligned with the packaged target when already applied.
            if (version_compare(AppVersion::installed(), AppVersion::current(), '<')) {
                AppVersion::markInstalled(AppVersion::current());
            }

            return 0;
        }

        foreach ($pending as $update) {
            $onOutput(PHP_EOL.'--- '.$update->description().' ---'.PHP_EOL);

            foreach ($update->artisanCommands() as $command) {
                $exitCode = $this->artisan($onOutput, $command);

                if ($exitCode !== 0) {
                    return $exitCode;
                }
            }

            AppVersion::markInstalled($update::version());
            $onOutput(__('general.system_update_release_applied', [
                'version' => $update::version(),
            ]).PHP_EOL);
        }

        if (version_compare(AppVersion::installed(), AppVersion::current(), '<')) {
            AppVersion::markInstalled(AppVersion::current());
        }

        return 0;
    }

    /**
     * @param  Closure(string): void  $onOutput
     * @param  list<string>  $arguments
     */
    protected function artisan(Closure $onOutput, array $arguments): int
    {
        return $this->runner->artisan(
            $arguments,
            $onOutput,
            'php artisan '.implode(' ', $arguments),
        );
    }

    /**
     * @param  Closure(string): void  $onOutput
     */
    protected function fail(Closure $onOutput, int $exitCode = 1): int
    {
        $onOutput(PHP_EOL.__('general.system_update_failed').PHP_EOL);

        return $exitCode;
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
