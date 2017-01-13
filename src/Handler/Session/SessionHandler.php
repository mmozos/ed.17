<?php

namespace App\Handler\Session;

use App\Handler;
use Symfony\Component\HttpFoundation;

/**
 * Class SessionHandler
 * Each time the framework handles a Request, a Session is created/managed.
 */
class SessionHandler
{
    /** @var int */
    private $expireTime;

    /** @var string */
    private $savePath;

    /** @var HttpFoundation\Session\Session */
    private $session;

    /**
     * SessionHandler constructor.
     *
     * @param int    $expireTime
     * @param string $savePath   path to SQLite database file itself
     * @param array  $options    Session configuration options:
     *                           cache_limiter, "" (use "0" to prevent headers from being sent entirely).
     *                           cookie_domain, ""
     *                           cookie_httponly, ""
     *                           cookie_lifetime, "0"
     *                           cookie_path, "/"
     *                           cookie_secure, ""
     *                           entropy_file, ""
     *                           entropy_length, "0"
     *                           gc_divisor, "100"
     *                           gc_maxlifetime, "1440"
     *                           gc_probability, "1"
     *                           hash_bits_per_character, "4"
     *                           hash_function, "0"
     *                           name, "PHPSESSID"
     *                           referer_check, ""
     *                           serialize_handler, "php"
     *                           use_cookies, "1"
     *                           use_only_cookies, "1"
     *                           use_trans_sid, "0"
     *                           upload_progress.enabled, "1"
     *                           upload_progress.cleanup, "1"
     *                           upload_progress.prefix, "upload_progress_"
     *                           upload_progress.name, "PHP_SESSION_UPLOAD_PROGRESS"
     *                           upload_progress.freq, "1%"
     *                           upload_progress.min-freq, "1"
     *                           url_rewriter.tags, "a=href,area=href,frame=src,form=,fieldset="
     */
    public function __construct($expireTime = 10, $savePath = '/app/cache/sessions', array $options = [])
    {
        $this->expireTime = $expireTime;
        // Get session save-path.
        if (is_null($savePath)) {
            $savePath = ini_get('session.save_path');
        } else {
            $savePath = ROOT_DIR.$savePath;
        }
        if (!is_writable($savePath)) {
            throw new \RuntimeException('Couldn\'t save to Sessions\' default path because write access isn\'t granted');
        }
        $this->savePath = $savePath;
        $this->session = null;
    }

    /**
     * @return HttpFoundation\Session\Session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Starts session.
     *
     * @param bool $debug
     *
     * @return bool
     */
    public function startSession($debug = DEBUG)
    {
        return $debug ? $this->startNativeFileSession() : $this->startMemcacheSession();
    }

    /**
     * Checks if any error was thrown.
     *
     * @return bool
     */
    public function hasError()
    {
        return $this->session instanceof HttpFoundation\Session\SessionInterface && $this->session->isStarted() && $this->session->has('ErrorData');
    }

    /**
     * @param string $handler
     */
    private function setSessionConfig($handler = 'files')
    {
        // Set any ini values.
        ini_set('session.save_handler', $handler);
        ini_set('session.save_path', $this->savePath);
        // Set session lifetime.
        /* @see http://stackoverflow.com/a/19597247 */
        ini_set('session.cookie_lifetime', $this->expireTime);
        ini_set('session.gc_maxlifetime', $this->expireTime / 2);
    }

    /**
     * Driver for the native filesystem session save handler.
     *
     * @param array $options
     *
     * @return bool
     */
    private function startNativeFileSession(array $options = [])
    {
        $this->setSessionConfig();
        $storage = new HttpFoundation\Session\Storage\NativeSessionStorage(
            array_merge($options, ['cache_limiter' => session_cache_limiter()]),
            new HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler());
        $this->session = new HttpFoundation\Session\Session($storage);

        return $this->session->start();
    }

    /**
     * Driver for the memcache session save handler provided by the Memcache PHP extension.
     *
     * @see memcache-monitor: https://github.com/andrefigueira/memcache-monitor
     * @see memcadmin: https://github.com/rewi/memcadmin
     *
     * @param string $host
     * @param int    $port
     * @param array  $options
     * @param bool   $lock
     * @param int    $lockWait
     * @param int    $maxWait
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function startMemcacheSession($host = 'localhost', $port = 11211, array $options = [], $lock = true, $lockWait = 250, $maxWait = 2)
    {
        if (!extension_loaded('memcache')) {
            throw new \RuntimeException('PHP does not have "memcache" extension enabled');
        }
        $memcache = new \MemcachePool();
        if ($memcache->connect($host, $port) === false) {
            throw new \Exception('Couldn\'t connect to Sessions\' default server');
        }
        $handler = new Handler\Session\LockingSessionHandler($memcache, [
            'expiretime' => $this->expireTime,
            'locking' => $lock,
            'spin_lock_wait' => $lockWait,
            'lock_max_wait' => $maxWait, ]);
        $storage = new HttpFoundation\Session\Storage\NativeSessionStorage($options, $handler);
        $this->session = new HttpFoundation\Session\Session($storage);

        return $this->session->start();
    }

    /**
     * No more supported: https://bugs.php.net/bug.php?id=53713&edit=1
     * Driver for the sqlite session save handler provided by the SQLite PHP extension.
     *
     * @see https://github.com/zikula/NativeSession/blob/4992c11f7b832f05561b98b0c192ce852e6ed602/Drak/NativeSession/NativeSqliteSessionHandler.php
     *
     * @param array $options
     *
     * @return bool
     */
    private function startSQLiteSession(array $options = [])
    {
        if (!extension_loaded('sqlite')) {
            throw new \RuntimeException('PHP does not have "sqlite" extension enabled');
        }
        $this->savePath = '/app/Resources/sessions.db';
        $this->setSessionConfig('sqlite');

        // Set rest of session related ini values.
        foreach ($options as $key => $value) {
            if (in_array($key, ['sqlite.assoc_case'])) {
                ini_set($key, $value);
            }
        }
        /** @var HttpFoundation\Session\Storage\NativeSessionStorage $storage */
        $storage = new HttpFoundation\Session\Storage\NativeSessionStorage($options, new HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler());
        $this->session = new HttpFoundation\Session\Session($storage);

        return $this->session->start();
    }
}