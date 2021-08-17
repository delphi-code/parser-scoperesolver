# ScopeResolver

Tracks the currently active scope to maintain a registry of variables


# Usage
```php
$scopeResolver = new ScopeResolver();

$t = new \PhpParser\NodeTraverser();
$t->addVisitor($scopeResolver);

/** @var \delphi\Parser\Scope\Helper\Scope $scope */
$scope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);
if ($scope->hasVariable('foo')) {
    /** @var \PhpParser\Node\Expr $value The expression that defined the variable itself */
    $value = $scope->getVariable('foo');
}

/** @var \PhpParser\Node $definingNode The node that defined this scope (class, function, etc) */
$definingNode = $scope->getDefiningNode();

/** 
 * Scope that contains the given scope, or null if already the global scope 
 * @var \delphi\Parser\Scope\Helper\Scope | null $parentScope 
 */
$parentScope = $scope->getParentScope();
```
