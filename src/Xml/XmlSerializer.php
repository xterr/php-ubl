<?php declare(strict_types=1);

namespace Xterr\UBL\Xml;

use Xterr\UBL\Exception\SerializationException;
use Xterr\UBL\Xml\Mapping\CodelistMeta;
use Xterr\UBL\Xml\Mapping\XmlNamespace;
use Xterr\UBL\Xml\Metadata\MetadataFactory;
use Xterr\UBL\Xml\Metadata\PropertyMetadata;

final class XmlSerializer
{
    private MetadataFactory $metadataFactory;

    public function __construct(?MetadataFactory $metadataFactory = null)
    {
        $this->metadataFactory = $metadataFactory ?? new MetadataFactory();
    }

    public function serialize(object $object): string
    {
        $meta = $this->metadataFactory->getMetadata($object::class);

        if (!$meta->isRootDocument()) {
            throw new SerializationException(\sprintf('Class %s is not a document root (missing #[XmlRoot]).', $object::class));
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rootNs = $meta->xmlRoot->namespace;
        $rootPrefix = XmlNamespace::prefixFor($rootNs);
        $rootName = $rootPrefix !== null
            ? $rootPrefix . ':' . $meta->xmlRoot->localName
            : $meta->xmlRoot->localName;

        $root = $dom->createElementNS($rootNs, $rootName);
        $dom->appendChild($root);

        // Collect and declare all namespaces used in the object tree
        /** @var array<string, string|null> $namespaces */
        $namespaces = [];
        $this->collectNamespaces($object, $namespaces);
        foreach ($namespaces as $ns => $prefix) {
            $nsUri = (string) $ns;
            if ($prefix !== null && $nsUri !== $rootNs) {
                $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, $nsUri);
            }
        }

        // Serialize the object into the root element
        $this->serializeObject($dom, $root, $object);

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new SerializationException('Failed to serialize XML.');
        }

        return $xml;
    }

    private function serializeObject(\DOMDocument $dom, \DOMElement $parent, object $object): void
    {
        $meta = $this->metadataFactory->getMetadata($object::class);

        // 1. Write XML attributes
        foreach ($meta->getAttributeProperties() as $prop) {
            $value = $this->getProperty($object, $prop->name);
            if ($value !== null) {
                $parent->setAttribute($prop->xmlAttribute->name, (string) $value);
            }
        }

        // 2. Write text value (for Cbc leaf types)
        $valueProp = $meta->getValueProperty();
        if ($valueProp !== null) {
            $value = $this->getProperty($object, $valueProp->name);
            if ($value !== null) {
                if ($value instanceof \DateTimeImmutable) {
                    $format = $valueProp->xmlValue?->format ?? 'Y-m-d\TH:i:sP';
                    $value = $value->format($format);
                }
                $parent->appendChild($dom->createTextNode((string) $value));
            }
        }

        // 3. Write child elements (in property order)
        foreach ($meta->getElementProperties() as $prop) {
            $value = $this->getProperty($object, $prop->name);

            if ($value === null) {
                continue;
            }

            if ($prop->isArray) {
                foreach ($value as $item) {
                    $this->serializeChildElement($dom, $parent, $prop, $item);
                }
            } else {
                $this->serializeChildElement($dom, $parent, $prop, $value);
            }
        }

        // 4. Write XmlAny raw fragments
        foreach ($meta->getAnyProperties() as $anyProp) {
            $fragments = $this->getProperty($object, $anyProp->name);
            if (\is_array($fragments)) {
                foreach ($fragments as $fragment) {
                    $this->injectRawXml($dom, $parent, $fragment);
                }
            }
        }
    }

    private function serializeChildElement(\DOMDocument $dom, \DOMElement $parent, PropertyMetadata $prop, mixed $value): void
    {
        $ns = $prop->xmlElement->namespace;
        $prefix = XmlNamespace::prefixFor($ns);
        $qualifiedName = $prefix !== null ? $prefix . ':' . $prop->xmlElement->name : $prop->xmlElement->name;

        $child = $dom->createElementNS($ns, $qualifiedName);
        $parent->appendChild($child);

        if ($value instanceof \BackedEnum) {
            $child->appendChild($dom->createTextNode((string) $value->value));

            $enumRef = new \ReflectionEnum($value);
            $metaAttrs = $enumRef->getAttributes(CodelistMeta::class);
            if ($metaAttrs !== []) {
                $meta = $metaAttrs[0]->newInstance();
                $child->setAttribute('listID', $meta->listID);
                if ($meta->listAgencyID !== null) {
                    $child->setAttribute('listAgencyID', $meta->listAgencyID);
                }
                if ($meta->listVersionID !== null) {
                    $child->setAttribute('listVersionID', $meta->listVersionID);
                }
                if ($meta->listName !== null) {
                    $child->setAttribute('listName', $meta->listName);
                }
            }
        } elseif (\is_object($value)) {
            if ($value instanceof \DateTimeImmutable) {
                $format = $prop->xmlElement->format ?? 'Y-m-d';
                $child->appendChild($dom->createTextNode($value->format($format)));
            } else {
                $this->serializeObject($dom, $child, $value);
            }
        } elseif (\is_bool($value)) {
            $child->appendChild($dom->createTextNode($value ? 'true' : 'false'));
        } else {
            $child->appendChild($dom->createTextNode((string) $value));
        }
    }

    private function injectRawXml(\DOMDocument $dom, \DOMElement $parent, string $fragment): void
    {
        // Strip DOCTYPE for XXE prevention
        $fragment = (string) preg_replace('/<!DOCTYPE[^>]*>/i', '', $fragment);

        $wrapper = new \DOMDocument();
        $loaded = $wrapper->loadXML('<root>' . $fragment . '</root>', \LIBXML_NONET);
        if (!$loaded) {
            return;
        }

        // Check for entities (XXE in fragments)
        if ($wrapper->doctype !== null && $wrapper->doctype->entities->length > 0) {
            return;
        }

        $rootEl = $wrapper->documentElement;
        if ($rootEl === null) {
            return;
        }

        foreach ($rootEl->childNodes as $node) {
            $imported = $dom->importNode($node, true);
            $parent->appendChild($imported);
        }
    }

    private function getProperty(object $object, string $propertyName): mixed
    {
        $getter = 'get' . ucfirst($propertyName);
        if (method_exists($object, $getter)) {
            return $object->$getter();
        }

        return null;
    }

    /** @param array<string, string|null> $namespaces */
    private function collectNamespaces(object $object, array &$namespaces): void
    {
        $meta = $this->metadataFactory->getMetadata($object::class);

        if ($meta->xmlRoot !== null) {
            $ns = $meta->xmlRoot->namespace;
            $namespaces[$ns] = XmlNamespace::prefixFor($ns);
        }
        if ($meta->xmlType !== null) {
            $ns = $meta->xmlType->namespace;
            $namespaces[$ns] = XmlNamespace::prefixFor($ns);
        }

        foreach ($meta->getElementProperties() as $prop) {
            $ns = $prop->xmlElement->namespace;
            if (!isset($namespaces[$ns])) {
                $namespaces[$ns] = XmlNamespace::prefixFor($ns);
            }

            $value = $this->getProperty($object, $prop->name);
            if ($value === null) {
                continue;
            }

            if ($prop->isArray) {
                foreach ($value as $item) {
                    if (\is_object($item)) {
                        $this->collectNamespaces($item, $namespaces);
                    }
                }
            } elseif (\is_object($value) && !$value instanceof \DateTimeImmutable && !$value instanceof \BackedEnum) {
                $this->collectNamespaces($value, $namespaces);
            }
        }
    }
}
