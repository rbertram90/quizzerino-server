<?php

namespace rbwebdesigns\quizzerino\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ClientEvent
{
    public function __construct(string $eventName)
    {
        
    }
}