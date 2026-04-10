<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class XmlAttribute
{
    public function __construct(
        public string $name,
        public bool $required = false,
    ) {}
}
