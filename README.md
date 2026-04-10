# PHP UBL

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![CI](https://github.com/xterr/php-ubl/actions/workflows/ci.yml/badge.svg)](https://github.com/xterr/php-ubl/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/xterr/php-ubl)](https://packagist.org/packages/xterr/php-ubl)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://phpstan.org/)

Attribute-driven XML serializer/deserializer for UBL 2.x documents in PHP 8.2+.

Map PHP classes to UBL XML elements using native PHP attributes, then serialize and deserialize without writing a single line of DOM code. Built-in XXE prevention, namespace management, and `DateTimeImmutable` support out of the box.

## Features

- **PHP 8 Attributes** - Declarative XML mapping via `#[XmlRoot]`, `#[XmlElement]`, `#[XmlAttribute]`, `#[XmlValue]`, `#[XmlType]`, `#[XmlAny]`
- **Full UBL 2.x Namespace Support** - CBC, CAC, EXT, SIG, SAC, SBC, DS, CCTS, UDT, QDT
- **Serialization** - PHP objects to well-formed UBL XML with automatic namespace declarations
- **Deserialization** - UBL XML to typed PHP objects with recursive hydration
- **XXE Prevention** - DOCTYPE stripping and entity rejection on both serialize and deserialize paths
- **DateTimeImmutable** - Configurable date/time format support for date elements and values
- **Choice Groups** - XSD `xs:choice` constraint validation via `#[ChoiceGroupConstraint]`
- **Raw XML Passthrough** - `#[XmlAny]` for extension elements and untyped XML fragments
- **Metadata Caching** - Reflection-based metadata is built once and cached per class
- **Zero Dependencies** - Only requires `ext-dom` and `ext-libxml`

## Installation

```bash
composer require xterr/php-ubl
```

**Requirements:**
- PHP 8.2 or higher
- `ext-dom`
- `ext-libxml`

## Quick Start

### Define a UBL document class

```php
use Xterr\UBL\Xml\Mapping\XmlRoot;
use Xterr\UBL\Xml\Mapping\XmlElement;
use Xterr\UBL\Xml\Mapping\XmlAttribute;
use Xterr\UBL\Xml\Mapping\XmlValue;
use Xterr\UBL\Xml\Mapping\XmlNamespace;

#[XmlRoot(localName: 'Invoice', namespace: 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2')]
class Invoice
{
    #[XmlElement(name: 'ID', namespace: XmlNamespace::CBC)]
    private ?string $id = null;

    #[XmlElement(name: 'IssueDate', namespace: XmlNamespace::CBC, format: 'Y-m-d')]
    private ?\DateTimeImmutable $issueDate = null;

    #[XmlElement(name: 'InvoiceTypeCode', namespace: XmlNamespace::CBC)]
    private ?string $invoiceTypeCode = null;

    // Getters and setters...
    public function getId(): ?string { return $this->id; }
    public function setId(?string $id): void { $this->id = $id; }
    public function getIssueDate(): ?\DateTimeImmutable { return $this->issueDate; }
    public function setIssueDate(?\DateTimeImmutable $issueDate): void { $this->issueDate = $issueDate; }
    public function getInvoiceTypeCode(): ?string { return $this->invoiceTypeCode; }
    public function setInvoiceTypeCode(?string $invoiceTypeCode): void { $this->invoiceTypeCode = $invoiceTypeCode; }
}
```

### Serialize to XML

```php
use Xterr\UBL\Xml\XmlSerializer;

$invoice = new Invoice();
$invoice->setId('INV-001');
$invoice->setIssueDate(new \DateTimeImmutable('2025-01-15'));
$invoice->setInvoiceTypeCode('380');

$serializer = new XmlSerializer();
$xml = $serializer->serialize($invoice);
```

### Deserialize from XML

```php
use Xterr\UBL\Xml\XmlDeserializer;

$deserializer = new XmlDeserializer();
$invoice = $deserializer->deserialize($xml, Invoice::class);

echo $invoice->getId(); // "INV-001"
```

## Mapping Attributes

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[XmlRoot]` | Class | Marks class as a document root element with local name and namespace |
| `#[XmlType]` | Class | Declares the XSD complex type name and namespace |
| `#[XmlElement]` | Property | Maps property to an XML child element |
| `#[XmlAttribute]` | Property | Maps property to an XML attribute |
| `#[XmlValue]` | Property | Maps property to the text content of the element |
| `#[XmlAny]` | Property | Captures unmapped child elements as raw XML fragments |

### `#[XmlElement]` options

```php
#[XmlElement(
    name: 'ID',                         // XML element local name
    namespace: XmlNamespace::CBC,       // XML namespace URI
    type: MyType::class,                // Inner type for array properties
    format: 'Y-m-d',                   // Date format (for DateTimeImmutable)
    required: true,                     // Whether the element is required
    choiceGroup: 'address',            // XSD choice group name
)]
```

## UBL Namespaces

All standard UBL 2.x namespaces are available as constants on `XmlNamespace`:

| Constant | Prefix | Namespace URI |
|----------|--------|---------------|
| `CBC` | `cbc` | `urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2` |
| `CAC` | `cac` | `urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2` |
| `EXT` | `ext` | `urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2` |
| `SIG` | `sig` | `urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2` |
| `SAC` | `sac` | `urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2` |
| `SBC` | `sbc` | `urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2` |
| `DS` | `ds` | `http://www.w3.org/2000/09/xmldsig#` |
| `CCTS` | `ccts` | `urn:un:unece:uncefact:data:specification:CoreComponentTypeSchemaModule:2` |
| `UDT` | `udt` | `urn:oasis:names:specification:ubl:schema:xsd:UnqualifiedDataTypes-2` |
| `QDT` | `qdt` | `urn:oasis:names:specification:ubl:schema:xsd:QualifiedDataTypes-2` |

## Working with Collections

For array properties, define the inner type via the `type` parameter or a `@var` docblock:

```php
/** @var list<InvoiceLine> */
#[XmlElement(name: 'InvoiceLine', namespace: XmlNamespace::CAC, type: InvoiceLine::class)]
private array $invoiceLines = [];

public function getInvoiceLines(): array { return $this->invoiceLines; }
public function addToInvoiceLines(InvoiceLine $line): void { $this->invoiceLines[] = $line; }
```

The serializer iterates the array and writes one element per item. The deserializer calls `addTo{PropertyName}()` for each occurrence.

## Error Handling

All exceptions implement `Xterr\UBL\Exception\ExceptionInterface`:

| Exception | When |
|-----------|------|
| `SerializationException` | Object is not a root document, or DOM serialization fails |
| `DeserializationException` | XML parse error, XXE detected, size limit exceeded, or date parsing fails |
| `SchemaParseException` | XSD schema parsing fails |
| `GeneratorException` | Code generation fails |

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyze
```

### CI

Tests run on PHP 8.2, 8.3, and 8.4 via GitHub Actions. Tagged releases are automatically notified to Packagist.

## Repository Structure

```
php-ubl/
├── src/
│   ├── Exception/              # Exception hierarchy
│   │   ├── ExceptionInterface.php
│   │   ├── DeserializationException.php
│   │   ├── SerializationException.php
│   │   ├── GeneratorException.php
│   │   └── SchemaParseException.php
│   ├── Validation/             # XSD constraint support
│   │   └── ChoiceGroupConstraint.php
│   └── Xml/
│       ├── Mapping/            # PHP 8 attributes for XML mapping
│       │   ├── XmlAny.php
│       │   ├── XmlAttribute.php
│       │   ├── XmlElement.php
│       │   ├── XmlNamespace.php
│       │   ├── XmlRoot.php
│       │   ├── XmlType.php
│       │   └── XmlValue.php
│       ├── Metadata/           # Reflection-based metadata extraction
│       │   ├── ClassMetadata.php
│       │   ├── MetadataFactory.php
│       │   └── PropertyMetadata.php
│       ├── XmlDeserializer.php
│       └── XmlSerializer.php
├── tests/
│   └── Unit/
├── composer.json
├── phpstan.neon.dist
├── phpunit.xml.dist
└── LICENSE
```

## License

[MIT](LICENSE) - Copyright (c) 2022 Ceana Razvan
