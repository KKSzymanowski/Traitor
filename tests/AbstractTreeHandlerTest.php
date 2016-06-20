<?php

use Traitor\Handlers\AbstractTreeHandler;

class AbstractTreeHandlerTest extends TestCase
{
    /**
     * @dataProvider testCaseDataProvider
     * @param SplFileInfo $file
     */
    public function test_normal_behavior($file)
    {
        $pathOriginal = $file->getRealPath();
        $pathExpected = str_replace('OriginalFiles', 'ExpectedFiles', $pathOriginal);

        $handler = new AbstractTreeHandler(file($pathOriginal), 'Baz\FooTrait', 'Foo\Bar');

        $newContent = $handler->handle()->toString();

        $this->assertEquals($newContent, implode($handler->toArray()));

        $expectedContent = file_get_contents($pathExpected);

        $expectedContent = str_replace("\r\n", "\n", $expectedContent);

        $newContent = str_replace("\r\n", "\n", $newContent);

        $this->assertEquals($expectedContent, $newContent, 'Assertion failed for ' . $file->getFilename());

    }

    public function test_exception_is_thrown_when_class_is_not_found()
    {
        $this->setExpectedException('Exception', 'Class Bar not found');

        $pathOriginal = __DIR__ . '/Other/' . __FUNCTION__;

        $handler = new AbstractTreeHandler(file($pathOriginal), 'Baz\FooTrait', 'Foo\Bar');

        $handler->handle();
    }

    public function test_exception_is_thrown_on_parsing_error()
    {
        $this->setExpectedException(
            'Exception',
            "Error on parsing Bar class\nSyntax error, unexpected '}', expecting T_FUNCTION on line 7"
        );

        $pathOriginal = __DIR__ . '/Other/' . __FUNCTION__;

        $handler = new AbstractTreeHandler(file($pathOriginal), 'Baz\FooTrait', 'Foo\Bar');

        $handler->handle();
    }

    public function test_exception_is_thrown_when_no_namespace_is_found()
    {
        $this->setExpectedException(
            'Exception',
            "Could not locate namespace definition for class 'Bar'"
        );

        $pathOriginal = __DIR__ . '/Other/' . __FUNCTION__;

        $handler = new AbstractTreeHandler(file($pathOriginal), 'Baz\FooTrait', 'Foo\Bar');

        $handler->handle();
    }

    public function testCaseDataProvider()
    {
        $files = new FilesystemIterator(__DIR__ . '/OriginalFiles', FilesystemIterator::SKIP_DOTS);

        $result = [];

        foreach ($files as $file) {
            $result[] = [$file];
        }

        return $result;
    }
}
