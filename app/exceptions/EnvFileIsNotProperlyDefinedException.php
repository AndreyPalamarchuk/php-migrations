<?php

namespace App\Exceptions;

class EnvFileIsNotProperlyDefinedException extends AmendableException
{
    /**
     * EnvFileIsNotProperlyDefinedException constructor.
     *
     * @param string         $addition
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($addition = '', $message = '.env file is not properly defined', $code = 0, Throwable $previous = null)
    {
        parent::__construct($addition, $message, $code, $previous);
    }
}
