<?php

declare(strict_types=1);

namespace RabbitCMS\Journal\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class NoJournal
{
}
