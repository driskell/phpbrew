<?php

namespace PhpBrew;

class CommandBuilder
{
    /* process nice value */
    public $nice;

    /* script */
    public $script;

    /* arguments */
    public $args = array();

    /* environment variables */
    public $env = array();

    public $stdout;

    public $stderr;

    public $append = true;

    public $logPath;

    private $originalEnv = array();

    public function __construct($script)
    {
        $this->script = $script;
    }

    public function args($args)
    {
        $this->args = $args;
    }

    public function addArg($arg)
    {
        $this->args[] = $arg;
    }

    public function arg($arg)
    {
        $this->args[] = $arg;
    }

    public function nice($nice)
    {
        $this->nice = $nice;
    }

    public function env($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->env[$k] = $v;
            }
        } else {
            $this->env[$key] = $value;
        }
    }

    public function passthru(&$lastline = null)
    {
        $ret = null;
        $command = $this->buildCommand(false);
        $this->setEnvironment();
        $lastline = passthru($command, $ret);
        $this->restoreEnvironment();
        if ($lastline === false) {
            return $ret;
        }

        return $ret;
    }

    public function execute(&$lastline = null)
    {
        $ret = null;
        $command = $this->buildCommand();
        $this->setEnvironment();
        $lastline = system($command, $ret);
        $this->restoreEnvironment();
        if ($lastline === false) {
            return $ret;
        }

        return $ret;
    }

    public function __toString()
    {
        return $this->buildCommand();
    }

    public function setStdout($stdout = true)
    {
        $this->stdout = $stdout;
    }

    public function setAppendLog($append = true)
    {
        $this->append = $append;
    }

    public function setLogPath($logPath)
    {
        $this->logPath = $logPath;
    }

    public function buildCommand($handleRedirect = true)
    {
        $cmd = array();

        if ($this->nice) {
            $cmd[] = 'nice';
            $cmd[] = '-n';
            $cmd[] = $this->nice;
        }
        $cmd[] = $this->script;

        if ($this->args) {
            foreach ($this->args as $arg) {
                $cmd[] = escapeshellarg($arg);
            }
        }

        // redirect stderr to stdout and pipe to the file.
        if ($handleRedirect) {
            if ($this->stdout) {
                // XXX: tee is disabled here because the exit status won't be
                // correct when using pipe.
                /*
                $cmd[] = '| tee';
                if ($this->append) {
                    $cmd[] = '-a';
                }
                $cmd[] = $this->logPath;
                 */
                $cmd[] = '2>&1';
            } elseif ($this->logPath) {
                $cmd[] = $this->append ? '>>' : '>';
                $cmd[] = escapeshellarg($this->logPath);
                $cmd[] = '2>&1';
            }
        }

        return implode(' ', $cmd);
    }

    private function setEnvironment()
    {
        if (empty($this->env)) {
            return;
        }

        $this->originalEnv = getenv();
        foreach ($this->env as $key => $value) {
            if (isset($value)) {
                putenv("$key=$value");
            } else {
                putenv($key);
            }
        }
    }

    private function restoreEnvironment()
    {
        if (empty($this->env)) {
            return;
        }

        $this->originalEnv = array();
        foreach ($this->env as $key => $value) {
            if (array_key_exists($key, $this->originalEnv)) {
                putenv("$key={$this->originalEnv[$key]}");
            } else {
                putenv($key);
            }
        }
    }
}
