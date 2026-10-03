<?php

namespace ProofAge\PrestaShop\Tests\Unit\Verification;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Verification\StatusMapper;

final class StatusMapperTest extends TestCase
{
    /** @return iterable<array{string,string}> */
    public static function states(): iterable
    {
        yield ['created', 'pending'];
        yield ['started', 'pending'];
        yield ['submitted', 'pending'];
        yield ['documents_required', 'pending'];
        yield ['review', 'pending'];
        yield ['resubmission_requested', 'retry'];
        yield ['approved', 'approved'];
        yield ['declined', 'declined'];
        yield ['abandoned', 'reset'];
        yield ['expired', 'reset'];
        yield ['something_new', 'pending'];
    }

    #[DataProvider('states')]
    public function testMapsStatusToState(string $status, string $state): void
    {
        self::assertSame($state, StatusMapper::toState($status));
    }

    public function testFinalStatuses(): void
    {
        foreach (['approved', 'declined', 'abandoned', 'expired'] as $status) {
            self::assertTrue(StatusMapper::isFinal($status), $status);
            self::assertFalse(StatusMapper::isReusable($status), $status);
        }
        foreach (['created', 'started', 'submitted', 'review', 'documents_required', 'resubmission_requested'] as $status) {
            self::assertFalse(StatusMapper::isFinal($status), $status);
            self::assertTrue(StatusMapper::isReusable($status), $status);
        }
    }
}
