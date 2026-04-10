<?php declare(strict_types=1);

namespace Xterr\UBL\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ChoiceGroupConstraint
{
    public function __construct(
        public readonly string $group,
        public readonly string $message = 'Only one property in choice group "{{ group }}" may be set.',
    ) {}
}
