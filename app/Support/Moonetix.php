<?php

namespace App\Support;

use RuntimeException;

/**
 * Signs offerwall links and checks postback hashes. Server side only.
 * Docs: https://docs.moonetix.com/offerwall-link and https://docs.moonetix.com/postbacks
 */
class Moonetix
{
    public const WALL_BASE = 'https://wall.moonetix.com/';

    /** Rewards are stored in millionths of a coin, so 0.75 + 1.23456 adds up exactly. */
    public const MICRO = 1000000;

    private const USER_ID = '/^[A-Za-z0-9._@:+\-]{1,100}$/';

    private const SUB_ID = '/^[A-Za-z0-9._~:@+\-]{1,100}$/';

    /**
     * The signed offerwall link for one of YOUR user IDs.
     *
     * @param  array<string, string>  $subs  Optional sub1 to sub5 tracking values. They are not signed.
     */
    public static function wallUrl(string $userId, array $subs = []): string
    {
        if (! preg_match(self::USER_ID, $userId)) {
            throw new RuntimeException('The user ID must be 1 to 100 characters from A-Z a-z 0-9 . _ @ : + -');
        }

        $hash = hash_hmac('sha256', $userId, self::secret('link_salt', 'MOONETIX_LINK_SALT'));
        $url = self::WALL_BASE.rawurlencode(self::secret('app_id', 'MOONETIX_APP_ID'))
            .'?user_id='.rawurlencode($userId).'&hash='.$hash;

        foreach ($subs as $key => $value) {
            if (preg_match('/^sub[1-5]$/', $key) && is_string($value) && preg_match(self::SUB_ID, $value)) {
                $url .= '&'.$key.'='.rawurlencode($value);
            }
        }

        return $url;
    }

    /**
     * True when $hash is the postback signature of the four values, exactly as they arrived.
     */
    public static function validPostbackHash(string $tx, string $userId, string $reward, string $status, string $hash): bool
    {
        $expected = hash_hmac('sha256', $tx.':'.$userId.':'.$reward.':'.$status,
            self::secret('postback_key', 'MOONETIX_POSTBACK_KEY'));

        // Constant-time comparison. Never use == or === for this.
        return hash_equals($expected, $hash);
    }

    /**
     * "150" -> 150000000, "0.75" -> 750000. Null for anything else. No floats.
     */
    public static function toMicro(string $amount): ?int
    {
        if (! preg_match('/^(\d{1,12})(?:\.(\d{1,6}))?$/', $amount, $m)) {
            return null;
        }

        return (int) $m[1] * self::MICRO + (int) str_pad($m[2] ?? '', 6, '0');
    }

    /**
     * 151984560 -> "151.98456".
     */
    public static function formatMicro(int $micro): string
    {
        $sign = $micro < 0 ? '-' : '';
        $abs = abs($micro);
        $fraction = rtrim(str_pad((string) ($abs % self::MICRO), 6, '0', STR_PAD_LEFT), '0');

        return $sign.intdiv($abs, self::MICRO).($fraction === '' ? '' : '.'.$fraction);
    }

    /**
     * Read lazily, so a missing key fails loudly (500, Moonetix retries) instead of signing with an empty string.
     */
    private static function secret(string $key, string $envName): string
    {
        $value = config('services.moonetix.'.$key);
        if (! is_string($value) || $value === '') {
            throw new RuntimeException($envName.' is not set');
        }

        return $value;
    }
}
