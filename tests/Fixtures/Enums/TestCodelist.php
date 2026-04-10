<?php declare(strict_types=1);

namespace Xterr\UBL\Tests\Fixtures\Enums;

use Xterr\UBL\Xml\Mapping\CodelistMeta;

#[CodelistMeta(listID: 'test-list', listAgencyID: 'TEST', listVersionID: '1.0')]
enum TestCodelist: string
{
    case Alpha = 'ALPHA';
    case Beta = 'BETA';
}
