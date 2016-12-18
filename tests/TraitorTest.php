<?php

use Traitor\Traitor;
use Traitor\Handlers\AbstractTreeHandler;

/** @runTestsInSeparateProcesses */
class TraitorTest extends TestCase
{
    public function test_class_already_uses_trait_returns_false_when_not()
    {
        $this->copy('BarClass.stub', 'BarClass.php');

        $this->includeFile('Trait1.php');
        $this->includeFile('BarClass.php');

        $result = Traitor::alreadyUses('Baz\BarClass', 'Trait1');

        $this->assertFalse($result);
    }

    public function test_class_already_uses_trait_returns_true_when_it_does()
    {
        $this->copy('BarClass.stub', 'BarClass.php');

        $path = __DIR__.'/TestingClasses/BarClass.php';

        $handler = new AbstractTreeHandler(file($path), 'Trait1', 'Baz\BarClass');

        $newContent = $handler->handle()->toString();

        file_put_contents($path, $newContent);

        $this->includeFile('Trait1.php');
        $this->includeFile('BarClass.php');

        $result = Traitor::alreadyUses('Baz\BarClass', 'Trait1');

        $this->assertTrue($result);
    }
}
