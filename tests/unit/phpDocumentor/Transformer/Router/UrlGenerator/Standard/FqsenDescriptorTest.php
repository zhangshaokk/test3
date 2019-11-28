<?php
/**
 * phpDocumentor
 *
 * PHP Version 5.3
 *
 * @copyright 2010-2018 Mike van Riel / Naenius (http://www.naenius.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace phpDocumentor\Transformer\Router\UrlGenerator\Standard;

use Mockery as m;
use phpDocumentor\Reflection\DocBlock\Tags\Reference\Fqsen;
use phpDocumentor\Reflection\Fqsen as RealFqsen;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Test for the MethodDescriptor URL Generator with the Standard Router
 */
class FqsenDescriptorTest extends \Mockery\Adapter\Phpunit\MockeryTestCase
{
    /**
     * @covers \phpDocumentor\Transformer\Router\UrlGenerator\Standard\FqsenDescriptor::__invoke
     * @covers \phpDocumentor\Transformer\Router\UrlGenerator\Standard\QualifiedNameToUrlConverter::fromClass
     * @dataProvider provideFqsens
     */
    public function testGenerateUrlForFqsenDescriptor($fromFqsen, $toPath) : void
    {
        // Arrange
        $urlGenerator = m::mock(UrlGeneratorInterface::class);
        $urlGenerator->shouldReceive('generate')->andReturn($toPath);
        $converter = new QualifiedNameToUrlConverter();
        $realFqsen = new RealFqsen($fromFqsen);
        $fqsen = new Fqsen($realFqsen);
        $fixture = new FqsenDescriptor($urlGenerator, $converter);

        // Act
        $result = $fixture($fqsen);

        // Assert
        $this->assertSame($toPath, $result);
    }

    public function provideFqsens() : array
    {
        return [
            ['\\My\\Space\\Class', '/classes/My.Space.Class.html'],
            ['\\My\\Space\\Class::$property', '/classes/My.Space.Class.html#property_property'],
            ['\\My\\Space\\Class::method()', '/classes/My.Space.Class.html#method_method'],
            ['\\My\\Space\\Class::CONSTANT', '/classes/My.Space.Class.html#constant_CONSTANT'],
        ];
    }
}
