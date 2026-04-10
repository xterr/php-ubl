<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Metadata;

use Xterr\UBL\Xml\Mapping\XmlAny;
use Xterr\UBL\Xml\Mapping\XmlAttribute;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlValue;

final readonly class PropertyMetadata
{
    public function __construct(
        public string $name,
        public string $phpType,
        public ?string $innerType,
        public bool $isNullable,
        public bool $isArray,
        public ?XmlElement $xmlElement,
        public ?XmlAttribute $xmlAttribute,
        public ?XmlValue $xmlValue,
        public ?XmlAny $xmlAny,
    ) {}

    public function isElement(): bool
    {
        return $this->xmlElement !== null;
    }

    public function isAttribute(): bool
    {
        return $this->xmlAttribute !== null;
    }

    public function isValue(): bool
    {
        return $this->xmlValue !== null;
    }

    public function isAny(): bool
    {
        return $this->xmlAny !== null;
    }
}
