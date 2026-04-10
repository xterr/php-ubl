<?php declare(strict_types=1);

namespace Xterr\UBL\Tests\Fixtures\Enums;

use Xterr\UBL\Xml\Mapping\CodelistMeta;

#[CodelistMeta(listID: 'test-list-b', listAgencyID: 'TEST')]
enum TestEnumB: string
{
    case Baz = 'BAZ';
    case Qux = 'QUX';
}
