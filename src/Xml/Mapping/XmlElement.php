<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class XmlElement
{
    public function __construct(
        public string $name,
        public string $namespace,
        public ?string $type = null,
        public ?string $format = null,
        public bool $required = false,
        public ?string $choiceGroup = null,
    ) {}
}
