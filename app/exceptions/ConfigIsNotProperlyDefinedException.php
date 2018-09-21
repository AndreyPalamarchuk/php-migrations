<?php

namespace App\Exceptions;

class ConfigIsNotProperlyDefinedException extends AmendableException
{
    /**
     * ConfigIsNotProperlyDefinedException constructor.
     *
     * @param string         $addition
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($addition = '', $message = 'Config file is not properly defined', $code = 0, Throwable $previous = null)
    {
        parent::__construct($addition, $message, $code, $previous);
    }
}
