<?php

namespace ProofAge\PrestaShop\Tests\Unit\Verification;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Verification\StatusTransition;

final class StatusTransitionTest extends TestCase
{
    public function testNonFinalCanMoveAnywhere(): void
    {
        self::assertTrue(StatusTransition::isAllowed('created', 'review'));
        self::assertTrue(StatusTransition::isAllowed('review', 'approved'));
        self::assertTrue(StatusTransition::isAllowed('resubmission_requested', 'submitted'));
        self::assertTrue(StatusTransition::isAllowed('review', 'declined'));
    }

    public function testSameStatusIsNoop(): void
    {
        self::assertFalse(StatusTransition::isAllowed('review', 'review'));
        self::assertFalse(StatusTransition::isAllowed('approved', 'approved'));
    }

    public function testApprovedCanOnlyBeRevoked(): void
    {
        self::assertTrue(StatusTransition::isAllowed('approved', 'declined'));
        self::assertFalse(StatusTransition::isAllowed('approved', 'review'));
        self::assertFalse(StatusTransition::isAllowed('approved', 'expired'));
    }

    public function testOtherFinalStatusesAreTerminal(): void
    {
        self::assertFalse(StatusTransition::isAllowed('declined', 'approved'));
        self::assertFalse(StatusTransition::isAllowed('expired', 'review'));
        self::assertFalse(StatusTransition::isAllowed('abandoned', 'approved'));
    }
}
