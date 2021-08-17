<?php

namespace delphi\Tests\Parser\Scope\Helper\Exception;

use delphi\Parser\Scope\Helper\Exception\UndefinedVariable;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \delphi\Parser\Scope\Helper\Exception\UndefinedVariable
 */
class UndefinedVariableTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testConstructMessage()
    {
        $name = 'namey';
        $sut  = new UndefinedVariable($name);
        $this->assertEquals('Variable "namey" has not been defined in scope', $sut->getMessage());
    }

    /**
     * @covers ::__construct
     */
    public function testConstructProxies()
    {
        $name     = 'namey';
        $previous = new \Exception();
        $code     = 1337;
        $sut      = new UndefinedVariable($name, $code, $previous);
        $this->assertEquals($code, $sut->getCode());
        $this->assertSame($previous, $sut->getPrevious());
    }

    /**
     * @covers ::getName
     */
    public function testGetName()
    {
        $name = 'namey';
        $sut  = new UndefinedVariable($name);
        $this->assertEquals($name, $sut->getName());
    }
}
