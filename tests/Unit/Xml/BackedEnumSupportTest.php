<?php declare(strict_types=1);

namespace Xterr\UBL\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Xterr\UBL\Tests\Fixtures\Classes\TestWithEnum;
use Xterr\UBL\Tests\Fixtures\Enums\TestCodelist;
use Xterr\UBL\Tests\Fixtures\Enums\TestCodelistBare;
use Xterr\UBL\Xml\Metadata\MetadataFactory;
use Xterr\UBL\Xml\XmlDeserializer;
use Xterr\UBL\Xml\XmlSerializer;

final class BackedEnumSupportTest extends TestCase
{
    private MetadataFactory $metadataFactory;
    private XmlSerializer $serializer;
    private XmlDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->metadataFactory = new MetadataFactory();
        $this->serializer = new XmlSerializer($this->metadataFactory);
        $this->deserializer = new XmlDeserializer($this->metadataFactory);
    }

    public function testMetadataDetectsEnumProperty(): void
    {
        $meta = $this->metadataFactory->getMetadata(TestWithEnum::class);
        $props = $meta->getElementProperties();

        $typeCodeProp = null;
        foreach ($props as $prop) {
            if ($prop->name === 'typeCode') {
                $typeCodeProp = $prop;
                break;
            }
        }

        $this->assertNotNull($typeCodeProp);
        $this->assertTrue($typeCodeProp->isEnum);
        $this->assertSame([TestCodelist::class], $typeCodeProp->enumClasses);
    }

    public function testMetadataDetectsBareEnumProperty(): void
    {
        $meta = $this->metadataFactory->getMetadata(TestWithEnum::class);
        $props = $meta->getElementProperties();

        $bareCodeProp = null;
        foreach ($props as $prop) {
            if ($prop->name === 'bareCode') {
                $bareCodeProp = $prop;
                break;
            }
        }

        $this->assertNotNull($bareCodeProp);
        $this->assertTrue($bareCodeProp->isEnum);
        $this->assertSame([TestCodelistBare::class], $bareCodeProp->enumClasses);
    }

    public function testSerializeEnumWithCodelistMeta(): void
    {
        $obj = new TestWithEnum();
        $obj->setTypeCode(TestCodelist::Alpha);

        $xml = $this->serializer->serialize($obj);

        $this->assertStringContainsString('ALPHA', $xml);
        $this->assertStringContainsString('listID="test-list"', $xml);
        $this->assertStringContainsString('listAgencyID="TEST"', $xml);
        $this->assertStringContainsString('listVersionID="1.0"', $xml);
    }

    public function testSerializeEnumWithoutCodelistMeta(): void
    {
        $obj = new TestWithEnum();
        $obj->setBareCode(TestCodelistBare::Foo);

        $xml = $this->serializer->serialize($obj);

        $this->assertStringContainsString('FOO', $xml);
        $this->assertStringNotContainsString('listID', $xml);
        $this->assertStringNotContainsString('listAgencyID', $xml);
    }

    public function testSerializeNullEnumOmitsElement(): void
    {
        $obj = new TestWithEnum();

        $xml = $this->serializer->serialize($obj);

        $this->assertStringNotContainsString('TypeCode', $xml);
        $this->assertStringNotContainsString('BareCode', $xml);
    }

    public function testDeserializeEnumFromXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TestWithEnum xmlns="urn:test">
  <TypeCode xmlns="urn:test:cbc" listID="test-list" listAgencyID="TEST" listVersionID="1.0">BETA</TypeCode>
</TestWithEnum>
XML;

        $obj = $this->deserializer->deserialize($xml, TestWithEnum::class);

        $this->assertSame(TestCodelist::Beta, $obj->getTypeCode());
    }

    public function testDeserializeBareEnumFromXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TestWithEnum xmlns="urn:test">
  <BareCode xmlns="urn:test:cbc">BAR</BareCode>
</TestWithEnum>
XML;

        $obj = $this->deserializer->deserialize($xml, TestWithEnum::class);

        $this->assertSame(TestCodelistBare::Bar, $obj->getBareCode());
    }

    public function testDeserializeUnknownEnumValueReturnsNull(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TestWithEnum xmlns="urn:test">
  <TypeCode xmlns="urn:test:cbc">UNKNOWN_VALUE</TypeCode>
</TestWithEnum>
XML;

        $obj = $this->deserializer->deserialize($xml, TestWithEnum::class);

        $this->assertNull($obj->getTypeCode());
    }

    public function testRoundTripWithCodelistMeta(): void
    {
        $original = new TestWithEnum();
        $original->setTypeCode(TestCodelist::Beta);

        $xml = $this->serializer->serialize($original);
        $restored = $this->deserializer->deserialize($xml, TestWithEnum::class);

        $this->assertSame($original->getTypeCode(), $restored->getTypeCode());
    }

    public function testRoundTripWithBareEnum(): void
    {
        $original = new TestWithEnum();
        $original->setBareCode(TestCodelistBare::Bar);

        $xml = $this->serializer->serialize($original);
        $restored = $this->deserializer->deserialize($xml, TestWithEnum::class);

        $this->assertSame($original->getBareCode(), $restored->getBareCode());
    }

    public function testRoundTripWithBothEnums(): void
    {
        $original = new TestWithEnum();
        $original->setTypeCode(TestCodelist::Alpha);
        $original->setBareCode(TestCodelistBare::Foo);

        $xml = $this->serializer->serialize($original);
        $restored = $this->deserializer->deserialize($xml, TestWithEnum::class);

        $this->assertSame($original->getTypeCode(), $restored->getTypeCode());
        $this->assertSame($original->getBareCode(), $restored->getBareCode());
    }
}
