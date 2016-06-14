<?php

use Traitor\Traitor;
use Traitor\TraitUseAdder;

/** @runTestsInSeparateProcesses */
class TraitUseAdderTest extends PHPUnit_Framework_TestCase
{

    protected function copy($src, $dst)
    {
        copy(
            __DIR__ . '/TestingClasses/'.$src,
            __DIR__ . '/TestingClasses/'.$dst
        );
    }

    protected function replaceInFile($search, $replace, $subject)
    {
        file_put_contents(
            __DIR__ . '/TestingClasses/'.$subject,
            str_replace($search, $replace, file_get_contents( __DIR__ . '/TestingClasses/'.$subject))
        );
    }

    protected function includeFile($file)
    {
        include __DIR__ . '/TestingClasses/'. $file;
    }

    public function test_normal_behavior()
    {

        $this->copy('BarClass.stub', 'BarClass.php');

        $this->includeFile('Trait1.php');
        $this->includeFile('Trait2.php');
        $this->includeFile('Trait3.php');
        $this->includeFile('BarClass.php');

        $adder = Traitor::addTraits(['Trait1', 'Some\Long\Trait3\Name\Space\Trait3']);
        $adder->addTrait('Trait2Namespace\Trait2')->toClass('\Baz\BarClass');

        $this->copy('BarClass.php', 'NewBarClass.php');

        $this->replaceInFile("BarClass", "NewBarClass", "NewBarClass.php");

        $this->includeFile('NewBarClass.php');

        $classUses = class_uses('\Baz\NewBarClass');
        
        $this->assertArrayHasKey('Trait1', $classUses);
        $this->assertArrayHasKey('Trait2Namespace\Trait2', $classUses);
        $this->assertArrayHasKey('Some\Long\Trait3\Name\Space\Trait3', $classUses);

        unlink(__DIR__ . '/TestingClasses/NewBarClass.php');

        $this->copy('BarClass.stub', 'BarClass.php');

    }

    public function test_bad_method_call_exception_is_thrown_when_trying_to_call_toClass_before_calling_addTrait()
    {
        $this->includeFile('BarClass.php');

        $this->setExpectedException('BadMethodCallException');

        (new TraitUseAdder())->toClass('\Baz\BarClass');
    }

    public function test_reflection_exception_is_thrown_when_class_does_not_exist()
    {
        $className = 'Baz\BarClass';

        $this->setExpectedException('ReflectionException', "Class ${className} does not exist");
        
        $this->includeFile('Trait1.php');

        Traitor::addTrait('Trait1')->toClass($className);
        
    }

    public function test_reflection_exception_is_thrown_when_trait_does_not_exist()
    {
        $traitName = 'Trait1';

        $this->setExpectedException('ReflectionException', "Class ${traitName} does not exist");

        $this->includeFile('BarClass.php');

        Traitor::addTrait('Trait1');

    }

}