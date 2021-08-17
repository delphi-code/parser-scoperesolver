<?php

namespace delphi\Parser\Scope\Helper\Exception;

use Throwable;

class UndefinedVariable extends \Exception
{
    protected string $name;

    public function __construct(string $name, $code = 0, Throwable $previous = null)
    {
        $this->name = $name;
        $message    = sprintf('Variable "%s" has not been defined in scope', $name);
        parent::__construct($message, $code, $previous);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
