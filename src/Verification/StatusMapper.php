<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

/**
 * Maps raw ProofAge statuses to the states the storefront acts on.
 */
final class StatusMapper
{
    public const STATE_PENDING = 'pending';
    public const STATE_RETRY = 'retry';
    public const STATE_APPROVED = 'approved';
    public const STATE_DECLINED = 'declined';
    public const STATE_RESET = 'reset';

    public const FINAL_STATUSES = ['approved', 'declined', 'abandoned', 'expired'];

    public static function toState(string $status): string
    {
        switch ($status) {
            case 'approved':
                return self::STATE_APPROVED;
            case 'declined':
                return self::STATE_DECLINED;
            case 'resubmission_requested':
                return self::STATE_RETRY;
            case 'abandoned':
            case 'expired':
                return self::STATE_RESET;
            default:
                return self::STATE_PENDING;
        }
    }

    public static function isFinal(string $status): bool
    {
        return in_array($status, self::FINAL_STATUSES, true);
    }

    /**
     * An unfinished verification can be reopened instead of creating a new one.
     */
    public static function isReusable(string $status): bool
    {
        return !self::isFinal($status);
    }
}
