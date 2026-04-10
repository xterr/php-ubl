<?php declare(strict_types=1);

namespace Xterr\UBL\Tests\Unit\Xml;

use PHPUnit\Framework\TestCase;
use Xterr\UBL\Tests\Fixtures\Classes\TestWithUnionEnum;
use Xterr\UBL\Tests\Fixtures\Enums\TestEnumA;
use Xterr\UBL\Tests\Fixtures\Enums\TestEnumB;
use Xterr\UBL\Xml\Metadata\MetadataFactory;
use Xterr\UBL\Xml\XmlDeserializer;
use Xterr\UBL\Xml\XmlSerializer;

final class UnionEnumSupportTest extends TestCase
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

    public function testMetadataDetectsUnionEnumProperty(): void
    {
        $meta = $this->metadataFactory->getMetadata(TestWithUnionEnum::class);
        $props = $meta->getElementProperties();

        $codeProp = null;
        foreach ($props as $prop) {
            if ($prop->name === 'code') {
                $codeProp = $prop;
                break;
            }
        }

        $this->assertNotNull($codeProp);
        $this->assertTrue($codeProp->isEnum);
        $this->assertCount(2, $codeProp->enumClasses);
        $this->assertContains(TestEnumA::class, $codeProp->enumClasses);
        $this->assertContains(TestEnumB::class, $codeProp->enumClasses);
    }

    public function testDeserializeUnionEnumWithListIdA(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TestWithUnionEnum xmlns="urn:test">
  <Code xmlns="urn:test:cbc" listID="test-list-a" listAgencyID="TEST">FOO</Code>
</TestWithUnionEnum>
XML;

        $obj = $this->deserializer->deserialize($xml, TestWithUnionEnum::class);

        $this->assertSame(TestEnumA::Foo, $obj->getCode());
    }

    public function testDeserializeUnionEnumWithListIdB(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TestWithUnionEnum xmlns="urn:test">
  <Code xmlns="urn:test:cbc" listID="test-list-b" listAgencyID="TEST">QUX</Code>
</TestWithUnionEnum>
XML;

        $obj = $this->deserializer->deserialize($xml, TestWithUnionEnum::class);

        $this->assertSame(TestEnumB::Qux, $obj->getCode());
    }

    public function testDeserializeUnionEnumWithUnknownListIdFallsBackToTryFrom(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TestWithUnionEnum xmlns="urn:test">
  <Code xmlns="urn:test:cbc" listID="unknown-list">BAZ</Code>
</TestWithUnionEnum>
XML;

        $obj = $this->deserializer->deserialize($xml, TestWithUnionEnum::class);

        $this->assertSame(TestEnumB::Baz, $obj->getCode());
    }

    public function testDeserializeUnionEnumWithNoListIdFallsBackToTryFrom(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TestWithUnionEnum xmlns="urn:test">
  <Code xmlns="urn:test:cbc">BAR</Code>
</TestWithUnionEnum>
XML;

        $obj = $this->deserializer->deserialize($xml, TestWithUnionEnum::class);

        $this->assertSame(TestEnumA::Bar, $obj->getCode());
    }

    public function testDeserializeUnionEnumWithUnknownValueReturnsNull(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TestWithUnionEnum xmlns="urn:test">
  <Code xmlns="urn:test:cbc" listID="test-list-a">UNKNOWN</Code>
</TestWithUnionEnum>
XML;

        $obj = $this->deserializer->deserialize($xml, TestWithUnionEnum::class);

        $this->assertNull($obj->getCode());
    }

    public function testSerializeUnionEnumA(): void
    {
        $obj = new TestWithUnionEnum();
        $obj->setCode(TestEnumA::Foo);

        $xml = $this->serializer->serialize($obj);

        $this->assertStringContainsString('FOO', $xml);
        $this->assertStringContainsString('listID="test-list-a"', $xml);
        $this->assertStringContainsString('listAgencyID="TEST"', $xml);
    }

    public function testSerializeUnionEnumB(): void
    {
        $obj = new TestWithUnionEnum();
        $obj->setCode(TestEnumB::Qux);

        $xml = $this->serializer->serialize($obj);

        $this->assertStringContainsString('QUX', $xml);
        $this->assertStringContainsString('listID="test-list-b"', $xml);
    }

    public function testRoundTripUnionEnumA(): void
    {
        $original = new TestWithUnionEnum();
        $original->setCode(TestEnumA::Bar);

        $xml = $this->serializer->serialize($original);
        $restored = $this->deserializer->deserialize($xml, TestWithUnionEnum::class);

        $this->assertSame($original->getCode(), $restored->getCode());
    }

    public function testRoundTripUnionEnumB(): void
    {
        $original = new TestWithUnionEnum();
        $original->setCode(TestEnumB::Baz);

        $xml = $this->serializer->serialize($original);
        $restored = $this->deserializer->deserialize($xml, TestWithUnionEnum::class);

        $this->assertSame($original->getCode(), $restored->getCode());
    }
}
