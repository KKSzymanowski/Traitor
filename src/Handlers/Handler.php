<?php

namespace Traitor\Handlers;

interface Handler 
{
    public function handle();

    public function toString();

    public function toArray();
    
}