<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class XmlRoot
{
    public function __construct(
        public string $localName,
        public string $namespace,
    ) {}
}
