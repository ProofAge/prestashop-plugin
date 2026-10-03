<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Support;

final class Input
{
    public const MAX_LINES = 200;

    /**
     * @return string[]
     */
    public static function lines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $out, true)) {
                $out[] = $line;
            }
            if (count($out) >= self::MAX_LINES) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value array of ids or a JSON-encoded array
     *
     * @return int[]
     */
    public static function ids($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $id = (int) $item;
                if ($id > 0 && !in_array($id, $out, true)) {
                    $out[] = $id;
                }
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     */
    public static function clampInt($value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }
}
