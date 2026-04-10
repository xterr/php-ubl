<?php declare(strict_types=1);

namespace Xterr\UBL\Xml\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CodelistMeta
{
    public function __construct(
        public string $listID,
        public ?string $listAgencyID = null,
        public ?string $listVersionID = null,
        public ?string $listName = null,
    ) {}
}
