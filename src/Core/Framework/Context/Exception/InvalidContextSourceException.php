<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Context\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class InvalidContextSourceException extends ShopwareHttpException
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct(
            'Expected ContextSource of "{{expected}}", but got "{{actual}}".',
            ['expected' => $expected, 'actual' => $actual]
        );
        parent::__construct('Store actions not available outside of AdminApiContext.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONTEXT_SOURCE';
    }
}
