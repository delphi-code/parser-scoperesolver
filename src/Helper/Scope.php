<?php

namespace delphi\Parser\Scope\Helper;

use delphi\Parser\Scope\Helper\Exception\UndefinedVariable;
use delphi\ParserUtils\Traits\UseAttributes;
use Exception;
use PhpParser\Node;

class Scope
{
    use UseAttributes;

    /** @var array */
    protected $variables = [];

    /** @var string */
    protected $name;

    /** @var Node */
    protected $definingNode;

    /** @var Scope */
    protected $parentScope;

    public function __construct($name, $definingNode, $parentScope = null)
    {
        $this->name         = $name;
        $this->definingNode = $definingNode;
        $this->parentScope  = $parentScope;
    }

    public function addVariable($name, $stmt)
    {
        $this->variables[$name] = $stmt;
    }

    public function getDefiningNode()
    {
        return $this->definingNode;
    }

    public function getParentScope(): Scope
    {
        return $this->parentScope;
    }

    public function getVariable(string $name)
    {
        if (!isset($this->variables[$name])) {
            throw new UndefinedVariable($name);
        }
        return $this->variables[$name];
    }

    public function removeVariable(string $name)
    {
        unset($this->variables[$name]);
    }

    public function hasVariable(string $name): bool
    {
        return isset($this->variables[$name]);
    }

    public function __toString()
    {
        $name = $this->name;
        if ($this->parentScope) {
            $name = (string) $this->parentScope;
            $name .= '|' . $this->name;
        }
        if (isset($this->definingNode->name)) {
            $name .= ':' . ((string) $this->definingNode->name);
        }
        return $name;
    }

}
