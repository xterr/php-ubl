<?php declare(strict_types=1);

namespace Xterr\UBL\Tests\Fixtures\Classes;

use Xterr\UBL\Tests\Fixtures\Enums\TestCodelist;
use Xterr\UBL\Tests\Fixtures\Enums\TestCodelistBare;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlRoot;
use Xterr\UBL\Xml\Mapping\XmlType;

#[XmlType(localName: 'TestWithEnumType', namespace: 'urn:test')]
#[XmlRoot(localName: 'TestWithEnum', namespace: 'urn:test')]
final class TestWithEnum
{
    #[XmlElement(name: 'TypeCode', namespace: 'urn:test:cbc')]
    private ?TestCodelist $typeCode = null;

    #[XmlElement(name: 'BareCode', namespace: 'urn:test:cbc')]
    private ?TestCodelistBare $bareCode = null;

    public function getTypeCode(): ?TestCodelist
    {
        return $this->typeCode;
    }

    public function setTypeCode(?TestCodelist $typeCode): void
    {
        $this->typeCode = $typeCode;
    }

    public function getBareCode(): ?TestCodelistBare
    {
        return $this->bareCode;
    }

    public function setBareCode(?TestCodelistBare $bareCode): void
    {
        $this->bareCode = $bareCode;
    }
}
