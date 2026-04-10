<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class XmlValue
{
    public function __construct(
        public ?string $format = null,
    ) {}
}
