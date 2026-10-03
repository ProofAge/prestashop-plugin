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
 * Guards against late or replayed status updates: final statuses never change,
 * except an approval that ProofAge later revokes (approved -> declined).
 */
final class StatusTransition
{
    public static function isAllowed(string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }
        if (StatusMapper::isFinal($from)) {
            return $from === 'approved' && $to === 'declined';
        }

        return true;
    }
}
