<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Exception;

use function call_user_func_array;
use function func_get_args;

class InvalidArgumentException extends LinkedInException
{
    /**
     * Treat this constructor as sprintf().
     */
    public function __construct()
    {
        parent::__construct(call_user_func_array('sprintf', func_get_args()));
    }
}
