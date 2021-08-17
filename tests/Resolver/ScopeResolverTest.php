<?php

namespace delphi\Tests\Parser\Scope\Resolver;

use delphi\Parser\File\Node\File;
use delphi\Parser\Scope\Helper\Scope;
use delphi\Parser\Scope\Resolver\ScopeResolver;
use delphi\ParserUtils\Exception\UnexpectedNodeType;
use PhpParser\Node;
use PhpParser\NodeAbstract;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \delphi\Parser\Scope\Resolver\ScopeResolver
 */
class ScopeResolverTest extends TestCase
{
    protected ScopeResolver $sut;

    public function setUp(): void
    {
        $this->sut = new ScopeResolver();
    }

    /**
     * @covers ::currentScope
     * @covers ::__construct
     */
    public function testCurrentScopeFresh()
    {
        $scope = new Scope('Global', null);
        $this->assertEquals($scope, $this->sut->currentScope());
    }

    /**
     * @covers ::currentScope
     */
    public function testCurrentScopeLater()
    {
        $node = new File('filename');

        $this->sut->enterNode($node);

        $scope = $this->sut->currentScope();
        $this->assertSame($node, $scope->getDefiningNode(), 'Defining node should be the file');
        $this->assertEquals('Global|File:filename', (string) $scope);

        $this->sut->leaveNode($node);

        $this->assertEquals(new Scope('Global', null), $this->sut->currentScope());
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeSetsScope()
    {
        $node = $this->createPartialMock(NodeAbstract::class, ['getType', 'getSubNodeNames']);

        $out = $this->sut->enterNode($node);
        $this->assertNull($out);

        $this->assertSame($this->sut->currentScope(), $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY), 'Should set node scope to current scope');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithFunction()
    {
        $node = $this->createPartialMock(Node\Stmt\Function_::class, ['getType', 'getSubNodeNames']);

        $globalScope = $this->sut->currentScope();

        $out = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        $this->assertSame($this->sut->currentScope(), $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY), 'Should set node scope to current scope');
        $scope = $this->sut->currentScope();
        $this->assertEquals(new Scope('Function', $node, $globalScope), $scope);
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithClosure()
    {
        $node = new Node\Expr\Closure([
            'uses' => [
                new Node\Expr\ClosureUse(new Node\Expr\Variable('usedVar')),
            ],
        ]);

        // Establish var in scope
        $stmt = $this->createStub(Node\Expr\MethodCall::class);
        $this->sut->currentScope()->addVariable('usedVar', $stmt);

        $out = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);
        $var         = $activeScope->getVariable('usedVar');
        $this->assertEquals($stmt, $var, 'Should reference a clone of the variable');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithClosureByRef()
    {
        $node = new Node\Expr\Closure([
            'uses' => [
                new Node\Expr\ClosureUse(new Node\Expr\Variable('usedVar'), true),
            ],
        ]);

        // Establish var in scope
        $stmt = $this->createStub(Node\Expr\MethodCall::class);
        $this->sut->currentScope()->addVariable('usedVar', $stmt);

        $out = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);
        $var         = $activeScope->getVariable('usedVar');
        $this->assertSame($stmt, $var, 'Should reference a reference to the variable');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithClassMethod()
    {
        $param1 = new Node\Param(new Node\Expr\Variable('param1'));
        $param2 = new Node\Param(new Node\Expr\Variable('param2'), new Node\Scalar\String_('default'), 'tiptype');
        $node   = new Node\Stmt\ClassMethod('methodName', [
            'params' => [
                $param1,
                $param2,
            ],
        ]);

        // Establish var in scope
        $stmt = $this->createStub(Node\Stmt\Class_::class);
        $this->sut->currentScope()->addVariable('this', $stmt);

        $parentScope = $this->sut->currentScope();
        $out         = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);

        // Ensure $this is defined
        $this->assertTrue($activeScope->hasVariable('this'), 'Should have $this defined');
        $this->assertSame($stmt, $activeScope->getVariable('this'), 'Should have $this defined to the class');

        // Ensure scope is correct definition
        $this->assertSame($node, $activeScope->getDefiningNode(), 'Scope should have correct defining node');
        $this->assertSame($parentScope, $activeScope->getParentScope(), 'Scope should have correct parent scope');

        // Ensure updated scope for resolver
        $this->assertSame($activeScope, $this->sut->currentScope(), 'Resolver should have updated scope');

        // Ensure params have been added to the scope
        $this->assertTrue($activeScope->hasVariable('param1'), 'Should have param1 defined');
        $this->assertEquals($param1, $activeScope->getVariable('param1'), 'Should have param1 defined properly');
        $this->assertTrue($activeScope->hasVariable('param2'), 'Should have param2 defined');
        $this->assertEquals($param2, $activeScope->getVariable('param2'), 'Should have param2 defined properly');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithTrait()
    {
        $node = new Node\Stmt\Trait_('traitName');

        // Establish var in scope
        $this->sut->currentScope()->addVariable('this', $node);

        $parentScope = $this->sut->currentScope();
        $out         = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);

        // Ensure $this is defined
        $this->assertTrue($activeScope->hasVariable('this'), 'Should have $this defined');
        $this->assertSame($node, $activeScope->getVariable('this'), 'Should have $this defined to the class');

        // Ensure scope is correct definition
        $this->assertSame($node, $activeScope->getDefiningNode(), 'Scope should have correct defining node');
        $this->assertSame($parentScope, $activeScope->getParentScope(), 'Scope should have correct parent scope');

        // Ensure updated scope for resolver
        $this->assertSame($activeScope, $this->sut->currentScope(), 'Resolver should have updated scope');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithClass()
    {
        $node = new Node\Stmt\Class_('className');

        // Establish var in scope
        $this->sut->currentScope()->addVariable('this', $node);

        $parentScope = $this->sut->currentScope();
        $out         = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);

        // Ensure $this is defined
        $this->assertTrue($activeScope->hasVariable('this'), 'Should have $this defined');
        $this->assertSame($node, $activeScope->getVariable('this'), 'Should have $this defined to the class');

        // Ensure scope is correct definition
        $this->assertSame($node, $activeScope->getDefiningNode(), 'Scope should have correct defining node');
        $this->assertSame($parentScope, $activeScope->getParentScope(), 'Scope should have correct parent scope');

        // Ensure updated scope for resolver
        $this->assertSame($activeScope, $this->sut->currentScope(), 'Resolver should have updated scope');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithInterface()
    {
        $node = new Node\Stmt\Interface_('interfaceName');

        $parentScope = $this->sut->currentScope();
        $out         = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);

        // Ensure $this is defined
        $this->assertFalse($activeScope->hasVariable('this'), 'Should not have $this defined');

        // Ensure scope is correct definition
        $this->assertSame($node, $activeScope->getDefiningNode(), 'Scope should have correct defining node');
        $this->assertSame($parentScope, $activeScope->getParentScope(), 'Scope should have correct parent scope');

        // Ensure updated scope for resolver
        $this->assertSame($activeScope, $this->sut->currentScope(), 'Resolver should have updated scope');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithFile()
    {
        $node = new File('fileName');

        $parentScope = $this->sut->currentScope();
        $out         = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);

        // Ensure $this is defined
        $this->assertFalse($activeScope->hasVariable('this'), 'Should not have $this defined');

        // Ensure scope is correct definition
        $this->assertSame($node, $activeScope->getDefiningNode(), 'Scope should have correct defining node');
        $this->assertSame($parentScope, $activeScope->getParentScope(), 'Scope should have correct parent scope');

        // Ensure updated scope for resolver
        $this->assertSame($activeScope, $this->sut->currentScope(), 'Resolver should have updated scope');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithPropertyWithoutDefault()
    {
        $node = new Node\Stmt\PropertyProperty('propertyName');

        $parentScope = $this->sut->currentScope();
        $out         = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);

        $this->assertSame($parentScope, $activeScope, 'Scope should not be different');
        $this->assertSame($parentScope, $this->sut->currentScope(), 'Scope should not have changed');

        // Ensure property is defined properly
        $this->assertNull($activeScope->getVariable('propertyName'));
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithPropertyWithDefault()
    {
        $default = new Node\Scalar\String_('defaultVal');
        $node    = new Node\Stmt\PropertyProperty('propertyName', $default);

        $parentScope = $this->sut->currentScope();
        $out         = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);

        $this->assertSame($parentScope, $activeScope, 'Scope should not be different');
        $this->assertSame($parentScope, $this->sut->currentScope(), 'Scope should not have changed');

        // Ensure property is defined properly
        $this->assertSame($default, $activeScope->getVariable('propertyName'));
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithUnset()
    {
        $var1 = new Node\Expr\Variable('var1');
        $var2 = new Node\Expr\Variable('var2');
        // TODO Not sure how to handle scope for arrays
        $var3 = new Node\Expr\ArrayDimFetch(new Node\Expr\Variable('oof'));

        $node = new Node\Stmt\Unset_([
            $var1,
            $var2,
            $var3,
        ]);

        $parentScope = $this->sut->currentScope();

        // Add only one to scope
        $parentScope->addVariable('var1', $var1);

        // Preconditions
        $this->assertTrue($parentScope->hasVariable('var1'), 'Should have var1 defined');
        $this->assertFalse($parentScope->hasVariable('var2'), 'Should not have var2 defined');

        $out = $this->sut->enterNode($node);

        $this->assertNull($out, 'enterNode should return null');

        /** @var Scope $activeScope */
        $activeScope = $node->getAttribute(ScopeResolver::ATTRIBUTE_KEY);

        $this->assertFalse($activeScope->hasVariable('var1'), 'Should not have var1 defined any more');
        $this->assertFalse($activeScope->hasVariable('var2'), 'Should not have var2 defined any more');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithAssignToArrayItem()
    {
        // TODO Handle pushing to an array
        $var  = new Node\Expr\ArrayDimFetch(new Node\Expr\Variable('oof'));
        $expr = new Node\Scalar\String_('foo');

        $node = new Node\Expr\Assign($var, $expr);
        $out  = $this->sut->enterNode($node);
        $this->assertNull($out, 'enterNode should return null');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithAssignToArray()
    {
        // TODO Unsure about what this means
        $var  = new Node\Expr\Array_();
        $expr = new Node\Scalar\String_('foo');

        $node = new Node\Expr\Assign($var, $expr);
        $out  = $this->sut->enterNode($node);
        $this->assertNull($out, 'enterNode should return null');
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithAssignToVariableVariable()
    {
        $var  = new Node\Expr\Variable(new Node\Expr\Variable('varVar'));
        $expr = new Node\Scalar\String_('foo');

        $node = new Node\Expr\Assign($var, $expr);

        $this->expectExceptionObject(new UnexpectedNodeType($node));
        $this->sut->enterNode($node);
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithAssign()
    {
        $var  = new Node\Expr\Variable('bar');
        $expr = new Node\Scalar\String_('foo');

        $node = new Node\Expr\Assign($var, $expr);
        $out  = $this->sut->enterNode($node);
        $this->assertNull($out, 'enterNode should return null');

        $this->assertSame($expr, $this->sut->currentScope()->getVariable('bar'));
    }

    /**
     * @covers ::enterNode
     */
    public function testEnterNodeWithAssignStringy()
    {
        $var  = new Node\Expr\Variable(new Node\Scalar\String_('bar'));
        $expr = new Node\Scalar\String_('foo');

        $node = new Node\Expr\Assign($var, $expr);
        $out  = $this->sut->enterNode($node);
        $this->assertNull($out, 'enterNode should return null');

        $this->assertSame($expr, $this->sut->currentScope()->getVariable('bar'));
    }

    public function scopeNodesProvider()
    {
        $r   = [];
        $r[] = [new Node\Stmt\Class_('OtherClassName')];
        $r[] = [new Node\Stmt\Interface_('InterfaceName')];
        $r[] = [new Node\Stmt\Trait_('TraitName')];
        $r[] = [new Node\Stmt\ClassMethod('ClassMethod')];
        $r[] = [new Node\Expr\Closure()];
        $r[] = [new Node\Expr\ArrowFunction()];
        $r[] = [new Node\Stmt\Function_('func')];
        $r[] = [new File('filename.php')];
        return $r;
    }

    /**
     * @dataProvider scopeNodesProvider
     */
    public function testLeaveNodePopsSuccessfully($node)
    {
        $initialScope = $this->sut->currentScope();

        // Push an item on the scope stack
        $classNode = new Node\Stmt\Class_('ClassName');
        $this->sut->enterNode($classNode);
        $this->assertNotSame($initialScope, $this->sut->currentScope());

        $this->sut->leaveNode($node);

        $this->assertSame($initialScope, $this->sut->currentScope(), 'Scope should have been popped off');
    }

    public function nonscopeNodesProvider()
    {
        $r   = [];
        $r[] = [new Node\Expr\Array_()];
        $r[] = [new Node\Expr\ArrayDimFetch(new Node\Expr\Variable('oof'))];
        $r[] = [new Node\Expr\Assign(new Node\Expr\Variable('asdf'), new Node\Scalar\String_('foo'))];
        $r[] = [new Node\Expr\ClosureUse(new Node\Expr\Variable('usedVar')),];
        $r[] = [new Node\Expr\ClosureUse(new Node\Expr\Variable('usedVar'), true),];
        $r[] = [new Node\Expr\Variable('bar')];
        $r[] = [new Node\Expr\Variable('name')];
        $r[] = [new Node\Expr\Variable('var1')];
        $r[] = [new Node\Expr\Variable('var2')];
        $r[] = [new Node\Expr\Variable(new Node\Expr\Variable('varVar'))];
        $r[] = [new Node\Expr\Variable(new Node\Scalar\String_('bar'))];
        $r[] = [new Node\Param(new Node\Expr\Variable('param1'))];
        $r[] = [new Node\Param(new Node\Expr\Variable('param2'), new Node\Scalar\String_('default'), 'tiptype')];
        $r[] = [new Node\Scalar\String_('defaultVal')];
        $r[] = [new Node\Scalar\String_('foo')];
        $r[] = [new Node\Stmt\PropertyProperty('propertyName')];
        $r[] = [new Node\Stmt\Unset_([])];
        return $r;
    }

    /**
     * @dataProvider nonscopeNodesProvider
     */
    public function testLeaveNodeDoesNotPop($node)
    {
        $initialScope = $this->sut->currentScope();

        // Push an item on the scope stack
        $classNode = new Node\Stmt\Class_('ClassName');
        $this->sut->enterNode($classNode);
        $this->assertNotSame($initialScope, $this->sut->currentScope());

        $this->sut->leaveNode($node);

        $this->assertNotSame($initialScope, $this->sut->currentScope(), 'Scope should not have been popped off');
    }
}
