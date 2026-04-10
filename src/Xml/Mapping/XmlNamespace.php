<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Mapping;

final class XmlNamespace
{
    public const string CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    public const string CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    public const string EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    public const string SIG = 'urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2';
    public const string SAC = 'urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2';
    public const string SBC = 'urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2';
    public const string DS = 'http://www.w3.org/2000/09/xmldsig#';
    public const string CCTS = 'urn:un:unece:uncefact:data:specification:CoreComponentTypeSchemaModule:2';
    public const string UDT = 'urn:oasis:names:specification:ubl:schema:xsd:UnqualifiedDataTypes-2';
    public const string QDT = 'urn:oasis:names:specification:ubl:schema:xsd:QualifiedDataTypes-2';

    private const array PREFIX_MAP = [
        'cbc' => self::CBC,
        'cac' => self::CAC,
        'ext' => self::EXT,
        'sig' => self::SIG,
        'sac' => self::SAC,
        'sbc' => self::SBC,
        'ds' => self::DS,
        'ccts' => self::CCTS,
        'udt' => self::UDT,
        'qdt' => self::QDT,
    ];

    private const array NAMESPACE_MAP = [
        self::CBC => 'cbc',
        self::CAC => 'cac',
        self::EXT => 'ext',
        self::SIG => 'sig',
        self::SAC => 'sac',
        self::SBC => 'sbc',
        self::DS => 'ds',
        self::CCTS => 'ccts',
        self::UDT => 'udt',
        self::QDT => 'qdt',
    ];

    private function __construct() {}

    public static function prefixFor(string $namespace): ?string
    {
        return self::NAMESPACE_MAP[$namespace] ?? null;
    }

    public static function namespaceFor(string $prefix): ?string
    {
        return self::PREFIX_MAP[$prefix] ?? null;
    }
}
