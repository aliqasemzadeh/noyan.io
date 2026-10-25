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

        if ($this->artisan($onOutput, ['migrate', '--force']) !== 0) {
            return $this->fail($onOutput);
        }

        if ($this->artisan($onOutput, ['permissions:sync']) !== 0) {
            return $this->fail($onOutput);
        }

        // Idempotent accounting bootstrap — safe on every Quick/Full update.
        if ($this->runAccountingBootstrap($onOutput) !== 0) {
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

        return 0;
    }

    /**
     * Post-migrate accounting data that every business needs after a deploy.
     * All commands are upsert / fill-empty only — no duplicate rows.
     *
     * @param  Closure(string): void  $onOutput
     */
    public function runAccountingBootstrap(Closure $onOutput): int
    {
        foreach ($this->accountingBootstrapCommands() as $command) {
            $exitCode = $this->artisan($onOutput, $command);

            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        return 0;
    }

    /**
     * @return list<list<string>>
     */
    public function accountingBootstrapCommands(): array
    {
        return [
            ['accounting:seed-coa', '--all'],
            ['accounting:sync-gl-details', '--all'],
            ['accounting:migrate-categories-to-gl', '--all'],
        ];
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
