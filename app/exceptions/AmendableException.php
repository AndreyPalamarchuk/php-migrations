<?php

namespace App\Exceptions;

use Throwable;

abstract class AmendableException extends \Exception
{
    public function __construct($addition, $message, $code = 0, Throwable $previous = null)
    {
        $message .= " {{$addition}}";
        parent::__construct($message, $code, $previous);
    }
}
