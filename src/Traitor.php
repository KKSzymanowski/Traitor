<?php

namespace Traitor;

class Traitor
{
    /**
     * @param string $trait
     *
     * @return TraitUseAdder
     */
    public static function addTrait($trait)
    {
        $instance = new TraitUseAdder();

        return $instance->addTraits([$trait]);
    }

    /**
     * @param array $traits
     *
     * @return TraitUseAdder
     */
    public static function addTraits($traits)
    {
        $instance = new TraitUseAdder();

        return $instance->addTraits($traits);
    }
}
