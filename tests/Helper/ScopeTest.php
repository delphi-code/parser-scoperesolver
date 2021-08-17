<?php

namespace delphi\Tests\Parser\Scope\Helper;

use delphi\Parser\Scope\Helper\Exception\UndefinedVariable;
use delphi\Parser\Scope\Helper\Scope;
use PhpParser\Node;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \delphi\Parser\Scope\Helper\Scope
 */
class ScopeTest extends TestCase
{
    private string $scopeName;

    private Node $definingNode;

    public function setUp(): void
    {
        $this->scopeName    = 'name';
        $this->definingNode = $this->createStub(Node::class);
        $this->sut          = new Scope($this->scopeName, $this->definingNode);
    }

    /**
     * @covers ::addVariable
     */
    public function testAddVariable()
    {
        $name = 'var_name';
        $stmt = 'stmt';

        $this->assertFalse($this->sut->hasVariable($name));
        $this->sut->addVariable($name, $stmt);
        $this->assertTrue($this->sut->hasVariable($name));
        $this->assertEquals($stmt, $this->sut->getVariable($name));
    }

    /**
     * @covers ::hasVariable
     */
    public function testHasVariable()
    {
        $name = 'var_name';
        $this->assertFalse($this->sut->hasVariable($name));
        $this->sut->addVariable($name, 'stmt');
        $this->assertTrue($this->sut->hasVariable($name));
    }

    /**
     * @covers ::getVariable
     */
    public function testGetVariable()
    {
        $name = 'var_name';
        $stmt = 'stmt';

        $this->sut->addVariable($name, $stmt);
        $this->assertEquals($stmt, $this->sut->getVariable($name));
    }

    /**
     * @covers ::getVariable
     */
    public function testGetVariableFailure()
    {
        $name = 'var_name';
        $stmt = 'stmt';

        $this->expectExceptionObject(new UndefinedVariable($name));
        $this->sut->getVariable($name);
    }

    /**
     * @covers ::removeVariable
     */
    public function testRemoveVariable()
    {
        $name = 'var_name';
        $stmt = 'stmt';

        $this->assertFalse($this->sut->hasVariable($name));
        $this->sut->addVariable($name, $stmt);
        $this->assertTrue($this->sut->hasVariable($name));
        $this->sut->removeVariable($name);
        $this->assertFalse($this->sut->hasVariable($name));
    }

    /**
     * @covers ::getDefiningNode
     */
    public function testGetDefiningNode()
    {
        $this->assertSame($this->definingNode, $this->sut->getDefiningNode());
    }

    /**
     * @covers ::getParentScope
     */
    public function testGetParentScopeWithoutScope()
    {
        $this->assertEquals(null, $this->sut->getParentScope());
    }

    /**
     * @covers ::getParentScope
     * @covers ::__construct
     */
    public function testGetParentScopeWithScope()
    {
        $parent = $this->sut;
        $sut    = new Scope($this->scopeName, $this->definingNode, $parent);
        $this->assertSame($parent, $sut->getParentScope());
    }

    /**
     * @covers ::__toString
     */
    public function testToStringBasic()
    {
        $this->assertEquals($this->scopeName, $this->sut->__toString());
    }

    /**
     * @covers ::__toString
     * @covers ::__construct
     */
    public function testToStringWithNamedDefiningNode()
    {
        $str = new Node\Stmt\Function_('funkname');
        $sut = new Scope('custom', $str);
        $this->assertEquals('custom:funkname', $sut->__toString());
    }

    /**
     * @covers ::__toString
     * @covers ::__construct
     */
    public function testToStringWithParentScope()
    {
        $sut = new Scope('custom', null, $this->sut);
        $this->assertEquals('name|custom', $sut->__toString());
    }

    /**
     * @covers ::__toString
     * @covers ::__construct
     */
    public function testToStringWithParentScopeAndNamedDefiningNode()
    {
        $str = new Node\Stmt\Function_('funkname');
        $sut = new Scope('custom', $str, $this->sut);
        $this->assertEquals('name|custom:funkname', $sut->__toString());
    }
}
