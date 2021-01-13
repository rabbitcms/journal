<?php

declare(strict_types=1);

namespace RabbitCMS\Journal\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Journal
{
    public ?array $only;
    public array $except;

    public function __construct(array $only = null, array $except = [])
    {
        $this->only = $only;
        $this->except = $except;
    }
}
