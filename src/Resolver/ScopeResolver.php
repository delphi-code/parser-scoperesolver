<?php

namespace delphi\Parser\Scope\Resolver;

use delphi\Parser\File\Node\File;
use delphi\Parser\Scope\Helper\Scope;
use delphi\ParserUtils\Exception\UnexpectedNodeType;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\NodeVisitorAbstract;
use SplStack;

/**
 * Sets current scope object to attributes
 * Opens new scopes as needed
 * Tracks variables in scope (and where they came from)
 */
class ScopeResolver extends NodeVisitorAbstract
{
    public const ATTRIBUTE_KEY = __CLASS__;

    public const SCOPE_NODES = [
        Node\Stmt\Class_::class,
        Node\Stmt\Interface_::class,
        Node\Stmt\Trait_::class,
        Node\Stmt\ClassMethod::class,
        Node\FunctionLike::class,
        File::class,
    ];

    protected Scope $globalScope;

    protected SplStack $scopeStack;

    public function __construct()
    {
        $this->scopeStack  = new SplStack();
        $this->globalScope = new Scope('Global', null);
        $this->scopeStack->push($this->globalScope);
    }

    public function enterNode(Node $node)
    {
        // Every node gets the current scope set as an attribute (may be overridden)
        $currentScope = $this->currentScope();
        $node->setAttribute(self::ATTRIBUTE_KEY, $currentScope);

        /**
         * Handle Variables
         */

        if ($node instanceof Node\Expr\Assign) {
            if ($node->var instanceof ArrayDimFetch) {
                // TODO Handle pushing to an array
                return;
            }

            if ($node->var instanceof Node\Expr\List_) {
                foreach ($node->var->items as $item) {
                    // TODO: Handle destructuring better
                    /** @var Node\Expr\ArrayItem $item */
                    $currentScope->addVariable($item->value->name, $node->expr);
                }
                return;
            }

            if ($node->var instanceof Node\Expr\Array_) {
                // TODO: Unsure about what this means
                return;
            }

            if ($node->var->name instanceof Node\Expr\Variable) {
                throw new UnexpectedNodeType($node);
            }

            if ($node->var->name instanceof Node\Scalar\String_) {
                $varName = $node->var->name->value;
            }
            else {
                $varName = (string) $node->var->name;
            }
            $currentScope->addVariable($varName, $node->expr);
            return;
        }

        if ($node instanceof Node\Stmt\Unset_) {
            foreach ($node->vars as $var) {
                if ($var instanceof ArrayDimFetch) {
                    // TODO Not sure how to handle scope for arrays
                    continue;
                }
                $currentScope->removeVariable($var->name);
            }
            return;
        }

        if ($node instanceof Node\Stmt\PropertyProperty) {
            $value = null;
            if ($node->default) {
                $value = $node->default;
            }
            $currentScope->addVariable($node->name->name, $value);
        }

        /**
         * Open New Scopes
         */

        if ($node instanceof File) {
            $scope = new Scope('File', $node, $currentScope);
            $this->scopeStack->push($scope);
            $node->setAttribute(self::ATTRIBUTE_KEY, $scope);
            return;
        }

        if ($node instanceof Node\Stmt\ClassLike) {
            $scopeName = 'ClassLike';
            if ($node instanceof Node\Stmt\Class_) {
                $scopeName = 'Class';
            }
            elseif ($node instanceof Node\Stmt\Trait_) {
                $scopeName = 'Trait';
            }
            elseif ($node instanceof Node\Stmt\Interface_) {
                $scopeName = 'Interface';
            }

            $scope = new Scope($scopeName, $node, $currentScope);

            if (!($node instanceof Node\Stmt\Interface_)) {
                $scope->addVariable('this', $node);
            }

            $this->scopeStack->push($scope);
            $node->setAttribute(self::ATTRIBUTE_KEY, $scope);
            return;
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $scope = new Scope('Method', $node, $currentScope);
            $scope->addVariable('this', $currentScope->getVariable('this'));

            // TODO: Inherit properties from class scope
            foreach ($node->getParams() as $param) {
                $scope->addVariable($param->var->name, $param);
            }
            $this->scopeStack->push($scope);
            $node->setAttribute(self::ATTRIBUTE_KEY, $scope);
            return;
        }

        if ($node instanceof Node\FunctionLike) {
            $scope = new Scope('Function', $node, $currentScope);

            if ($node instanceof Node\Expr\Closure) {
                $scope = new Scope('Closure', $node, $currentScope);
                foreach ($node->uses as $use) {
                    $v = $currentScope->getVariable($use->var->name);
                    if (!$use->byRef) {
                        $v = clone $v;
                    }
                    $scope->addVariable($use->var->name, $v);
                }
            }

            $this->scopeStack->push($scope);
            $node->setAttribute(self::ATTRIBUTE_KEY, $scope);
            return;
        }
    }

    public function currentScope(): Scope
    {
        return $this->scopeStack->top();
    }

    public function leaveNode(Node $node)
    {
        foreach (self::SCOPE_NODES as $scopeNode) {
            if ($node instanceof $scopeNode) {
                $this->scopeStack->pop();
                return;
            }
        }
    }

}
