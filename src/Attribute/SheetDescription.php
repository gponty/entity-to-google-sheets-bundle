<?php

namespace Gponty\EntityToGoogleSheetsBundle\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class SheetDescription
{
    public function __construct(
        public readonly string $description = ''
    ) {}
}