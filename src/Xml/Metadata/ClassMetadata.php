<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Metadata;

use Xterr\UBL\Xml\Mapping\XmlRoot;
use Xterr\UBL\Xml\Mapping\XmlType;

final readonly class ClassMetadata
{
    /**
     * @param class-string $className
     * @param list<PropertyMetadata> $properties
     */
    public function __construct(
        public string $className,
        public ?XmlType $xmlType,
        public ?XmlRoot $xmlRoot,
        public array $properties,
    ) {}

    public function isRootDocument(): bool
    {
        return $this->xmlRoot !== null;
    }

    public function getValueProperty(): ?PropertyMetadata
    {
        foreach ($this->properties as $prop) {
            if ($prop->isValue()) {
                return $prop;
            }
        }

        return null;
    }

    /** @return list<PropertyMetadata> */
    public function getAttributeProperties(): array
    {
        return array_values(array_filter($this->properties, fn(PropertyMetadata $p) => $p->isAttribute()));
    }

    /** @return list<PropertyMetadata> */
    public function getElementProperties(): array
    {
        return array_values(array_filter($this->properties, fn(PropertyMetadata $p) => $p->isElement()));
    }

    /** @return list<PropertyMetadata> */
    public function getAnyProperties(): array
    {
        return array_values(array_filter($this->properties, fn(PropertyMetadata $p) => $p->isAny()));
    }
}
