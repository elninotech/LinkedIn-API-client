<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Storage;

use const PHP_SAPI;
use function session_id;
use function session_start;

/**
 * Store data in the global session.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SessionStorage extends BaseDataStorage
{
    public function __construct()
    {
        // start the session if it not already been started
        if (PHP_SAPI !== 'cli') {
            if (session_id() === '') {
                session_start();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value): void
    {
        $this->validateKey($key);

        $name            = $this->getStorageKeyId($key);
        $_SESSION[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    public function get($key)
    {
        $this->validateKey($key);
        $name = $this->getStorageKeyId($key);

        return $_SESSION[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function clear($key): void
    {
        $this->validateKey($key);

        $name = $this->getStorageKeyId($key);

        if (isset($_SESSION[$name])) {
            unset($_SESSION[$name]);
        }
    }
}
