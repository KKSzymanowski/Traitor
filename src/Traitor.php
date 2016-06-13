<?php

namespace Traitor;

class Traitor
{

    public static function __callStatic($name, $arguments)
    {
        $instance = new TraitUseAdder;

        return call_user_func_array([$instance, $name], $arguments);
    }

}