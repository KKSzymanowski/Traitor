<?php

abstract class TestCase extends PHPUnit_Framework_TestCase
{

    protected function copy($src, $dst)
    {
        copy(
            __DIR__ . '/TestingClasses/' . $src,
            __DIR__ . '/TestingClasses/' . $dst
        );
    }

    protected function replaceInFile($search, $replace, $subject)
    {
        file_put_contents(
            __DIR__ . '/TestingClasses/' . $subject,
            str_replace($search, $replace, file_get_contents(__DIR__ . '/TestingClasses/' . $subject))
        );
    }

    protected function includeFile($file)
    {
        include __DIR__ . '/TestingClasses/' . $file;
    }
}
