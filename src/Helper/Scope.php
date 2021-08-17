<?php

namespace delphi\Parser\Scope\Helper;

use delphi\Parser\Scope\Helper\Exception\UndefinedVariable;
use Exception;
use PhpParser\Node;

class Scope
{
    /** @var array */
    protected $variables = [];

    /** @var string */
    protected $name;

    /** @var Node */
    protected $definingNode;

    /** @var array */
    protected $attributes = [];

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

    public function getAttribute($name)
    {
        if (!array_key_exists($name, $this->attributes)) {
            throw new Exception($name . ' was not set on node');
        }
        return $this->attributes[$name];
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
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

    public function setAttribute($name, $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
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
