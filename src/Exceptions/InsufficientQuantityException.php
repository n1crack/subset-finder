<?php

namespace Ozdemir\SubsetFinder\Exceptions;

class InsufficientQuantityException extends SubsetFinderException
{
    public function __construct(string $message = 'Insufficient quantity to create subsets', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
