<?php

/**
 * Content watcher
 * @package iqomp/watcher
 * @version 1.1.2
 */

namespace Iqomp\Watcher;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Composer\Command\BaseCommand;

class Watcher extends BaseCommand
{
    protected $script;
    protected $runner;
    protected $files = [];
    protected $ignores = [];

    protected function compareFiles(): ?string
    {
        clearstatcache();
        $files  = $this->scanFiles(getcwd(), '.');
        $result = null;

        if (!$this->files) {
            $this->files = $files;
            return 'First run';
        }

        // compare old -> new
        // - removed
        // - updated
        foreach ($this->files as $file => $time) {
            if (!isset($files[$file])) {
                unset($this->files[$file]);
                $result = 'File removed `' . $file . '`';
            } elseif ($files[$file] != $time) {
                $this->files[$file] = $files[$file];
                $result = 'File updated `' . $file . '`';
            }
        }

        // compare new -> old
        // - created
        foreach ($files as $file => $time) {
            if (!isset($this->files[$file])) {
                $this->files[$file] = $time;
                $result = 'File created `' . $file . '`';
            }
        }

        return $result;
    }

    protected function killPID($pid): void
    {
        $pids = preg_split('/\s+/', `ps -o pid --no-heading --ppid $pid`);
        foreach ($pids as $spid) {
            $spid = trim($spid);
            if (!$spid) {
                continue;
            }

            posix_kill($spid, 9);
            // $this->killPID($spid);
        }

        exec('pkill -P ' . $pid);
    }

    protected function restartScript(OutputInterface $out): void
    {
        $this->stopScript($out);
        $this->startScript($out);
    }

    protected function scanFiles(string $base, string $path): array
    {
        $d_files = array_diff(scandir($base), ['.','..']);
        $result  = [];

        foreach ($d_files as $file) {
            $file_abs = $base . '/' . $file;
            $file_rel = $path . '/' . $file;

            if (in_array($file_abs, $this->ignores)) {
                continue;
            }

            if (is_dir($file_abs)) {
                $files = $this->scanFiles($file_abs, $file_rel);
                $result = array_merge($result, $files);
                continue;
            }

            $result[$file_rel] = filemtime($file_abs);
        }

        return $result;
    }

    protected function startScript(OutputInterface $out): void
    {
        $proc_cwd  = getcwd();
        $proc_desc = [STDIN, ['pipe','w'], STDERR];
        $proc_cmd  = $this->script;

        $this->runner = proc_open($proc_cmd, $proc_desc, $pipes, $proc_cwd);
        $info = proc_get_status($this->runner);
        $pid  = $info['pid'];

        $out->writeln('Script started PID: ' . $pid);
    }

    protected function stopScript(OutputInterface $out): void
    {
        if (!$this->runner) {
            return;
        }

        $info = proc_get_status($this->runner);

        if ($info['running']) {
            $pid  = $info['pid'];

            $out->writeln('Killing process PID: ' . $pid);
            $this->killPID($pid);
        }

        proc_terminate($this->runner, 2);
        proc_close($this->runner);

        $this->runner = null;
    }

    protected function configure()
    {
        $this->setName('watch')
            ->setDescription('Watch for file changes and run a script')
            ->addArgument(
                'script',
                InputArgument::REQUIRED,
                'What script do you want to run?'
            )
            ->addOption(
                'ignore',
                'i',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Ignore file/directory relative to current directory'
            );
    }

    protected function execute(InputInterface $in, OutputInterface $out)
    {
        $this->script = $in->getArgument('script');

        $ignores = $in->getOption('ignore');
        if ($ignores) {
            foreach ($ignores as $ignore) {
                $ignore = ltrim($ignore, '/');
                $ignore = getcwd() . '/' . $ignore;
                $ignore = realpath($ignore);

                if ($ignore) {
                    $this->ignores[] = $ignore;
                }
            }
        }

        $out->writeln('Watching current directory for file changes');

        while (true) {
            $diff = $this->compareFiles();
            if ($diff) {
                $msg = '[' . date('H:i:s') . '] ';
                $out->writeln($msg . $diff);
                $this->restartScript($out);
            }
            sleep(1);
        }
    }
}
