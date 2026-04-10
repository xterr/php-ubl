<?php declare(strict_types=1);

namespace Xterr\UBL\Tests\Fixtures\Enums;

use Xterr\UBL\Xml\Mapping\CodelistMeta;

#[CodelistMeta(listID: 'test-list-a', listAgencyID: 'TEST')]
enum TestEnumA: string
{
    case Foo = 'FOO';
    case Bar = 'BAR';
}
