<?php declare(strict_types=1);

namespace Xterr\UBL\Xml;

use Xterr\UBL\Exception\DeserializationException;
use Xterr\UBL\Xml\Mapping\CodelistMeta;
use Xterr\UBL\Xml\Metadata\ClassMetadata;
use Xterr\UBL\Xml\Metadata\MetadataFactory;
use Xterr\UBL\Xml\Metadata\PropertyMetadata;

final class XmlDeserializer
{
    private MetadataFactory $metadataFactory;
    private int $maxXmlSize;
    /** @var array<string, string> */
    private array $listIdToEnumCache = [];

    public function __construct(
        ?MetadataFactory $metadataFactory = null,
        int $maxXmlSize = 52_428_800, // 50 MB
    ) {
        $this->metadataFactory = $metadataFactory ?? new MetadataFactory();
        $this->maxXmlSize = $maxXmlSize;
    }

    /**
     * @template T of object
     * @param class-string<T>|null $targetClass Auto-detect from root element if null
     * @return T
     */
    public function deserialize(string $xml, ?string $targetClass = null): object
    {
        if (\strlen($xml) > $this->maxXmlSize) {
            throw new DeserializationException(\sprintf('XML exceeds maximum size of %d bytes.', $this->maxXmlSize));
        }

        // Strip DOCTYPE to prevent XXE
        $xml = (string) preg_replace('/<!DOCTYPE[^>]*>/i', '', $xml);

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, \LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            $msg = $errors !== [] ? $errors[0]->message : 'Unknown XML parse error';
            throw new DeserializationException('Failed to parse XML: ' . trim($msg));
        }

        // Additional XXE check: reject documents with entity definitions
        if ($dom->doctype !== null && $dom->doctype->entities->length > 0) {
            throw new DeserializationException('XML with entity declarations is not allowed (XXE prevention).');
        }

        $root = $dom->documentElement;
        if ($root === null) {
            throw new DeserializationException('XML has no root element.');
        }

        if ($targetClass === null) {
            throw new DeserializationException('Target class must be specified (auto-detection requires DocumentRegistry).');
        }

        /** @var T */
        return $this->hydrateObject($root, $targetClass);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function hydrateObject(\DOMElement $element, string $className): object
    {
        $meta = $this->metadataFactory->getMetadata($className);
        $object = new $className();

        // 1. Set XML attributes
        foreach ($meta->getAttributeProperties() as $prop) {
            if ($prop->xmlAttribute === null) {
                continue;
            }
            $attrValue = $element->getAttribute($prop->xmlAttribute->name);
            if ($attrValue !== '') {
                $this->setProperty($object, $prop->name, $attrValue);
            }
        }

        // 2. Set XML text value (for Cbc leaf types)
        $valueProp = $meta->getValueProperty();
        if ($valueProp !== null) {
            $textContent = $this->getDirectTextContent($element);
            if ($textContent !== '') {
                $value = $this->convertValue($textContent, $valueProp);
                $this->setProperty($object, $valueProp->name, $value);
            }
        }

        // 3. Set XML child elements
        $mappedElements = $this->buildMappedElementIndex($meta);
        $anyFragments = [];

        foreach ($this->childElements($element) as $child) {
            $key = ($child->namespaceURI ?? '') . ':' . $child->localName;

            if (isset($mappedElements[$key])) {
                $prop = $mappedElements[$key];
                $value = $this->resolveChildValue($child, $prop);

                if ($prop->isArray) {
                    $adder = 'addTo' . ucfirst($prop->name);
                    if (method_exists($object, $adder)) {
                        $object->$adder($value);
                    }
                } else {
                    $this->setProperty($object, $prop->name, $value);
                }
            } else {
                // Unmapped element — collect for XmlAny
                $anyFragments[] = $element->ownerDocument?->saveXML($child);
            }
        }

        // 4. Set XmlAny content
        if ($anyFragments !== []) {
            foreach ($meta->getAnyProperties() as $anyProp) {
                $this->setProperty($object, $anyProp->name, $anyFragments);
                break; // Only one XmlAny property expected
            }
        }

        /** @var T */
        return $object;
    }

    private function resolveChildValue(\DOMElement $child, PropertyMetadata $prop): mixed
    {
        $targetType = $prop->isArray ? $prop->innerType : $prop->phpType;

        if ($targetType === null || \in_array($targetType, ['string', 'int', 'float', 'bool'], true)) {
            return $this->convertValue($this->getDirectTextContent($child), $prop);
        }

        if ($prop->isEnum && $prop->enumClasses !== []) {
            $textContent = $this->getDirectTextContent($child);

            if (\count($prop->enumClasses) === 1) {
                /** @var class-string<\BackedEnum> $enumClass */
                $enumClass = $prop->enumClasses[0];

                return $enumClass::tryFrom($textContent);
            }

            $listID = $child->getAttribute('listID');
            if ($listID !== '') {
                /** @var class-string<\BackedEnum>|null $resolved */
                $resolved = $this->resolveEnumByListID($listID, $prop->enumClasses);
                if ($resolved !== null) {
                    return $resolved::tryFrom($textContent);
                }
            }

            foreach ($prop->enumClasses as $candidate) {
                /** @var class-string<\BackedEnum> $candidate */
                $result = $candidate::tryFrom($textContent);
                if ($result !== null) {
                    return $result;
                }
            }

            return null;
        }

        if ($targetType === \DateTimeImmutable::class || $targetType === 'DateTimeImmutable') {
            return $this->parseDateTime($this->getDirectTextContent($child), $prop);
        }

        // Complex type — recurse
        if (class_exists($targetType)) {
            return $this->hydrateObject($child, $targetType);
        }

        return $this->getDirectTextContent($child);
    }

    private function convertValue(string $text, PropertyMetadata $prop): mixed
    {
        $targetType = $prop->isArray ? ($prop->innerType ?? 'string') : $prop->phpType;

        return match ($targetType) {
            'bool' => \in_array(strtolower($text), ['true', '1'], true),
            'int' => (int) $text,
            'float' => (float) $text,
            default => $text,
        };
    }

    private function parseDateTime(string $text, PropertyMetadata $prop): \DateTimeImmutable
    {
        $format = $prop->xmlElement->format ?? $prop->xmlValue?->format;

        if ($format !== null) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $format, $text, new \DateTimeZone('UTC'));
        } else {
            try {
                $dt = new \DateTimeImmutable($text);
            } catch (\Exception) {
                $dt = false;
            }
        }

        if ($dt === false) {
            throw new DeserializationException(\sprintf('Cannot parse date "%s" with format "%s"', $text, $format ?? 'auto'));
        }

        return $dt;
    }

    private function setProperty(object $object, string $propertyName, mixed $value): void
    {
        $setter = 'set' . ucfirst($propertyName);
        if (method_exists($object, $setter)) {
            $object->$setter($value);
        }
    }

    private function getDirectTextContent(\DOMElement $element): string
    {
        $text = '';
        foreach ($element->childNodes as $node) {
            if ($node instanceof \DOMText) {
                $text .= $node->nodeValue;
            }
        }

        return trim($text);
    }

    /** @return list<\DOMElement> */
    private function childElements(\DOMElement $parent): array
    {
        $elements = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /** @param list<class-string<\UnitEnum>> $enumClasses */
    private function resolveEnumByListID(string $listID, array $enumClasses): ?string
    {
        if (isset($this->listIdToEnumCache[$listID])) {
            return $this->listIdToEnumCache[$listID];
        }

        foreach ($enumClasses as $enumClass) {
            $ref = new \ReflectionEnum($enumClass);
            $attrs = $ref->getAttributes(CodelistMeta::class);
            if ($attrs !== []) {
                $meta = $attrs[0]->newInstance();
                if ($meta->listID === $listID) {
                    $this->listIdToEnumCache[$listID] = $enumClass;

                    return $enumClass;
                }
            }
        }

        return null;
    }

    /** @return array<string, PropertyMetadata> keyed by "namespace:localName" */
    private function buildMappedElementIndex(ClassMetadata $meta): array
    {
        $index = [];
        foreach ($meta->getElementProperties() as $prop) {
            if ($prop->xmlElement === null) {
                continue;
            }
            $key = $prop->xmlElement->namespace . ':' . $prop->xmlElement->name;
            $index[$key] = $prop;
        }

        return $index;
    }
}
