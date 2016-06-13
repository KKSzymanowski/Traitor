<?php

use Traitor\MyFacade;
use Traitor\Traitor;
use Traitor\TraitUseAdder;

if(class_exists('PHPUnit_Util_Configuration') == false)
    require_once "C:/Users/Jakub/Code/Traitor/vendor/autoload.php";

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

    public function test()
    {

        $this->copy('BarClass.stub', 'BarClass.php');

        $this->includeFile('Trait1.php');
        $this->includeFile('Trait2.php');
        $this->includeFile('Trait3.php');
        $this->includeFile('BarClass.php');

        $adder = Traitor::addTraits(['Trait1', 'Some\Long\Trait3\Name\Space\Trait3']);
        $adder = $adder->addTrait('Trait2Namespace\Trait2')->toClass(\Baz\BarClass::class);

        $this->copy('BarClass.php', 'NewBarClass.php');

        $this->replaceInFile("BarClass", "NewBarClass", "NewBarClass.php");

        $this->includeFile('NewBarClass.php');

        $classUses = class_uses('\Baz\NewBarClass');
        
        $this->assertArrayHasKey('Trait1', $classUses);
        $this->assertArrayHasKey('Trait2Namespace\Trait2', $classUses);
        $this->assertArrayHasKey('Some\Long\Trait3\Name\Space\Trait3', $classUses);

        unlink(__DIR__ . '/TestingClasses/NewBarClass.php');

    }

}