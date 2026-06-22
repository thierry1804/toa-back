<?php

declare(strict_types=1);

namespace App\Security;

use Predis\Client;

/**
 * Progressive IP-based login rate limiter backed by Redis.
 *
 * Tier 1 (5 failures)  → block for 15 minutes.
 * Tier 2 (any failure after the tier-1 block expires) → block for 30 minutes.
 * Attempt 4 triggers a warning response so the user knows one try remains.
 */
class LoginAttemptManager
{
    private const PREFIX        = 'toa:login:';
    private const WARN_AT       = 4;    // warn on the 4th failure
    private const BLOCK_AT      = 5;    // block on the 5th failure
    private const TIER1_TTL     = 900;  // 15 min in seconds
    private const TIER2_TTL     = 1800; // 30 min in seconds
    private const ESCALATED_TTL = 2700; // 45 min — must outlast the tier-1 block

    public function __construct(private readonly Client $redis) {}

    // ── Redis key helpers ────────────────────────────────────────────────────

    private function attemptsKey(string $ip): string
    {
        return self::PREFIX . 'attempts:' . sha1($ip);
    }

    private function blockedKey(string $ip): string
    {
        return self::PREFIX . 'blocked:' . sha1($ip);
    }

    private function escalatedKey(string $ip): string
    {
        return self::PREFIX . 'escalated:' . sha1($ip);
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Returns current block data when the IP is blocked, null otherwise.
     *
     * @return array{retry_after: int, tier: int}|null
     */
    public function getBlock(string $ip): ?array
    {
        $tier = $this->redis->get($this->blockedKey($ip));
        if ($tier === null) {
            return null;
        }

        return [
            'retry_after' => max(1, (int) $this->redis->ttl($this->blockedKey($ip))),
            'tier'        => (int) $tier,
        ];
    }

    /**
     * Record one failed authentication attempt and return the action to take.
     *
     * @return array{action: 'continue'|'warn'|'block', tier?: int, retry_after?: int}
     */
    public function recordFailure(string $ip): array
    {
        // Tier-2 escalation: the IP was previously blocked (escalated key exists)
        // but the block has since expired (blocked key is gone).
        // Any new failure immediately triggers a 30-minute block.
        if (
            (int) $this->redis->exists($this->escalatedKey($ip)) > 0 &&
            (int) $this->redis->exists($this->blockedKey($ip)) === 0
        ) {
            return $this->applyBlock($ip, 2, self::TIER2_TTL, deleteEscalated: true);
        }

        $count = (int) $this->redis->incr($this->attemptsKey($ip));
        if ($count === 1) {
            // Start the 15-minute window on the first failure
            $this->redis->expire($this->attemptsKey($ip), self::TIER1_TTL);
        }

        if ($count < self::WARN_AT) {
            return ['action' => 'continue'];
        }

        if ($count === self::WARN_AT) {
            // 4th attempt — warn the user that one attempt remains
            return ['action' => 'warn'];
        }

        // 5th failure: tier-1 block + plant the escalation marker
        $this->redis->setex($this->escalatedKey($ip), self::ESCALATED_TTL, '1');

        return $this->applyBlock($ip, 1, self::TIER1_TTL, deleteEscalated: false);
    }

    /**
     * Clear the attempt counter on successful login.
     * The escalation marker is left intact — it expires naturally.
     */
    public function onSuccess(string $ip): void
    {
        $this->redis->del([$this->attemptsKey($ip)]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * @return array{action: 'block', tier: int, retry_after: int}
     */
    private function applyBlock(string $ip, int $tier, int $ttl, bool $deleteEscalated): array
    {
        $this->redis->setex($this->blockedKey($ip), $ttl, (string) $tier);
        $this->redis->del([$this->attemptsKey($ip)]);

        if ($deleteEscalated) {
            $this->redis->del([$this->escalatedKey($ip)]);
        }

        return ['action' => 'block', 'tier' => $tier, 'retry_after' => $ttl];
    }
}
