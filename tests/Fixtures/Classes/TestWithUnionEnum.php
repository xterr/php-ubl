<?php declare(strict_types=1);

namespace Xterr\UBL\Tests\Fixtures\Classes;

use Xterr\UBL\Tests\Fixtures\Enums\TestEnumA;
use Xterr\UBL\Tests\Fixtures\Enums\TestEnumB;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlRoot;
use Xterr\UBL\Xml\Mapping\XmlType;

#[XmlType(localName: 'TestWithUnionEnumType', namespace: 'urn:test')]
#[XmlRoot(localName: 'TestWithUnionEnum', namespace: 'urn:test')]
final class TestWithUnionEnum
{
    #[XmlElement(name: 'Code', namespace: 'urn:test:cbc')]
    private TestEnumA|TestEnumB|null $code = null;

    public function getCode(): TestEnumA|TestEnumB|null
    {
        return $this->code;
    }

    public function setCode(TestEnumA|TestEnumB|null $code): void
    {
        $this->code = $code;
    }
}
