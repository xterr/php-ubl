<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Metadata;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Xterr\UBL\Xml\Mapping\XmlAny;
use Xterr\UBL\Xml\Mapping\XmlAttribute;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlRoot;
use Xterr\UBL\Xml\Mapping\XmlType;
use Xterr\UBL\Xml\Mapping\XmlValue;

final class MetadataFactory
{
    /** @var array<class-string, ClassMetadata> */
    private array $cache = [];

    /** @param class-string $className */
    public function getMetadata(string $className): ClassMetadata
    {
        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        $ref = new ReflectionClass($className);

        $xmlType = null;
        $xmlRoot = null;

        foreach ($ref->getAttributes() as $attr) {
            $instance = $attr->newInstance();

            if ($instance instanceof XmlType) {
                $xmlType = $instance;
            }

            if ($instance instanceof XmlRoot) {
                $xmlRoot = $instance;
            }
        }

        $properties = [];

        foreach ($ref->getProperties() as $property) {
            $propMeta = $this->buildPropertyMetadata($property);

            if ($propMeta !== null) {
                $properties[] = $propMeta;
            }
        }

        $meta = new ClassMetadata($className, $xmlType, $xmlRoot, $properties);
        $this->cache[$className] = $meta;

        return $meta;
    }

    private function buildPropertyMetadata(ReflectionProperty $property): ?PropertyMetadata
    {
        $xmlElement = null;
        $xmlAttribute = null;
        $xmlValue = null;
        $xmlAny = null;

        foreach ($property->getAttributes() as $attr) {
            $instance = $attr->newInstance();

            if ($instance instanceof XmlElement) {
                $xmlElement = $instance;
            }

            if ($instance instanceof XmlAttribute) {
                $xmlAttribute = $instance;
            }

            if ($instance instanceof XmlValue) {
                $xmlValue = $instance;
            }

            if ($instance instanceof XmlAny) {
                $xmlAny = $instance;
            }
        }

        if ($xmlElement === null && $xmlAttribute === null && $xmlValue === null && $xmlAny === null) {
            return null;
        }

        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $phpType = $type->getName();
        } elseif ($type instanceof \ReflectionUnionType) {
            $phpType = 'mixed';
        } else {
            $phpType = 'mixed';
        }
        $isNullable = $type !== null && $type->allowsNull();
        $isArray = $phpType === 'array';

        $isEnum = false;
        $enumClasses = [];

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            if (!$isArray && $typeName !== 'mixed' && enum_exists($typeName)) {
                $enumRef = new \ReflectionEnum($typeName);
                if ($enumRef->isBacked()) {
                    $isEnum = true;
                    $enumClasses[] = $typeName;
                }
            }
        } elseif ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionMember) {
                if ($unionMember instanceof ReflectionNamedType) {
                    $memberName = $unionMember->getName();
                    if (enum_exists($memberName)) {
                        $enumRef = new \ReflectionEnum($memberName);
                        if ($enumRef->isBacked()) {
                            $enumClasses[] = $memberName;
                        }
                    }
                }
            }
            if ($enumClasses !== []) {
                $isEnum = true;
            }
        }

        $innerType = null;

        if ($isArray) {
            if ($xmlElement !== null && $xmlElement->type !== null) {
                $innerType = $xmlElement->type;
            } else {
                $innerType = $this->extractArrayInnerTypeFromDoc($property);
            }
        }

        return new PropertyMetadata(
            name: $property->getName(),
            phpType: $phpType,
            innerType: $innerType,
            isNullable: $isNullable,
            isArray: $isArray,
            xmlElement: $xmlElement,
            xmlAttribute: $xmlAttribute,
            xmlValue: $xmlValue,
            xmlAny: $xmlAny,
            isEnum: $isEnum,
            enumClasses: $enumClasses,
        );
    }

    private function extractArrayInnerTypeFromDoc(ReflectionProperty $property): ?string
    {
        $doc = $property->getDocComment();

        if ($doc === false) {
            return null;
        }

        if (preg_match('/@var\s+list<([^>]+)>/', $doc, $m)) {
            $innerName = trim($m[1]);

            if (!str_contains($innerName, '\\')) {
                $ns = $property->getDeclaringClass()->getNamespaceName();

                return $ns !== '' ? $ns . '\\' . $innerName : $innerName;
            }

            return ltrim($innerName, '\\');
        }

        return null;
    }
}
