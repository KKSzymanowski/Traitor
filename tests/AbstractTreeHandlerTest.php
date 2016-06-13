<?php

use Traitor\Foo;
use Traitor\Handlers\AbstractTreeHandler;

class AbstractTreeHandlerTest extends PHPUnit_Framework_TestCase
{

    public function test_normal_behavior()
    {
        // Get all the files in OriginalFiles directory
        $files = new FilesystemIterator(__DIR__ . '/OriginalFiles', FilesystemIterator::SKIP_DOTS);

        // Foreach file add a Trait and compare output against expected file
        /** @var SplFileInfo $file */
        foreach($files as $file)
        {

            $pathOriginal = $file->getRealPath();
            $pathExpected = str_replace("OriginalFiles", "ExpectedFiles", $pathOriginal);

            $handler = new AbstractTreeHandler(file($pathOriginal), 'Baz\FooTrait', 'Foo\Bar');

            $newContent = $handler->handle()->toString();

            $this->assertEquals($newContent, implode($handler->toArray()));

            $this->assertStringEqualsFile($pathExpected, $newContent, "Assertion failed for " . $file->getFilename());
        }

    }

    

    public function test_exception_is_thrown_when_class_is_not_found()
    {
        $this->setExpectedException(Exception::class, "Class Bar not found");

        $pathOriginal = __DIR__ . '/Other/' . __FUNCTION__;

        $handler = new AbstractTreeHandler(file($pathOriginal), 'Baz\FooTrait', 'Foo\Bar');

        $handler->handle();
    }

}