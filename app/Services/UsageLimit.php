<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Daily per-account limits on the tools that cost money or server time
 * (config/limits.php). A day runs midnight to midnight, Philippine time.
 */
class UsageLimit
{
    public const LITERATURE_REVIEW = 'literature_review';
    public const SIMILARITY_CHECK  = 'similarity_check';

    /** @return array{used: int, limit: int, remaining: int} */
    public static function status(int $userId, string $feature): array
    {
        $limit = self::limit($feature);
        $used  = (int) DB::table('feature_usage')
            ->where(['user_id' => $userId, 'feature' => $feature, 'used_on' => self::today()])
            ->value('count');

        return ['used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used)];
    }

    public static function exhausted(int $userId, string $feature): bool
    {
        return self::status($userId, $feature)['remaining'] === 0;
    }

    /**
     * Count one use, unless the limit is already reached. The check and the
     * count are one statement, so two tabs submitting at once cannot both
     * slip past the last remaining use.
     */
    public static function record(int $userId, string $feature): bool
    {
        $key = ['user_id' => $userId, 'feature' => $feature, 'used_on' => self::today()];

        DB::table('feature_usage')->insertOrIgnore($key + ['count' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('feature_usage')
            ->where($key)
            ->where('count', '<', self::limit($feature))
            ->update(['count' => DB::raw('count + 1'), 'updated_at' => now()]) > 0;
    }

    public static function message(string $feature): string
    {
        $name = $feature === self::LITERATURE_REVIEW ? 'literature review searches' : 'similarity checks';

        return 'You have used all ' . self::limit($feature) . " of today's {$name}. You can search again after midnight.";
    }

    private static function limit(string $feature): int
    {
        return max(0, (int) config("limits.daily.{$feature}", 0));
    }

    private static function today(): string
    {
        return now(config('limits.timezone'))->toDateString();
    }
}
