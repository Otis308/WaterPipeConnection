<?php

namespace App\Services;

/**
 * PvpRatingService — Tính biến động Elo cho trận đấu multiplayer (2–4 người)
 *
 * AC6 — Elo Integrity:
 *  - Tính pairwise giữa mọi cặp người chơi dựa trên rating BAN ĐẦU (không bị ảnh hưởng thứ tự vòng lặp)
 *  - Tổng delta Elo trong trận phải = 0 (zero-sum) sau khi làm tròn
 *  - Thuật toán làm tròn: tính delta float cho từng người, làm tròn cuối cùng
 *    với người đứng đầu mảng nhận phần dư để tổng = 0
 *  - Mỗi trận chỉ cập nhật rating một lần (đảm bảo bởi transaction + FOR UPDATE trong finalizeMatch)
 */
class PvpRatingService
{
    public const K_FACTOR = 32;

    /**
     * Tra cứu Tier từ rating
     */
    public static function getTier(int $rating): array
    {
        return match (true) {
            $rating >= 2000 => ['name' => 'Thách Đấu',  'color' => '#f43f5e', 'badge' => '⚡'],
            $rating >= 1800 => ['name' => 'Cao Thủ',    'color' => '#d946ef', 'badge' => '👑'],
            $rating >= 1600 => ['name' => 'Kim Cương',  'color' => '#38bdf8', 'badge' => '💎'],
            $rating >= 1400 => ['name' => 'Bạch Kim',   'color' => '#06b6d4', 'badge' => '💠'],
            $rating >= 1200 => ['name' => 'Vàng',       'color' => '#eab308', 'badge' => '🥇'],
            $rating >= 1000 => ['name' => 'Bạc',        'color' => '#94a3b8', 'badge' => '🥈'],
            default         => ['name' => 'Đồng',       'color' => '#cd7f32', 'badge' => '🥉'],
        };
    }

    /**
     * Tính biến động Elo cho trận đấu N người (2 <= N <= 4).
     *
     * Thuật toán pairwise chuẩn (AC6):
     *  - Với mỗi cặp (i, j), tính E_i = 1 / (1 + 10^((R_j - R_i) / 400))
     *  - S_i = 1.0 nếu rank_i < rank_j (thắng), 0.5 nếu bằng (hòa), 0.0 nếu thua
     *  - delta_i += K/(N-1) * (S_i - E_i)   [scaled K per pair]
     *  - Tất cả E_i tính theo RATING BAN ĐẦU để tránh phụ thuộc thứ tự
     *
     * Đảm bảo tổng delta = 0:
     *  - Tính tổng delta float trước
     *  - Làm tròn từng delta sang int
     *  - Kiểm tra tổng sau làm tròn, điều chỉnh 1 đơn vị cho người đứng đầu nếu lệch
     *
     * @param array $players [['user_id' => int, 'rating' => int, 'rank' => int], ...]
     * @return array [user_id => ['rating_before', 'rating_after', 'rating_delta', 'tier_name', 'tier_color', 'tier_badge']]
     */
    public static function calculateMatchEloDeltas(array $players): array
    {
        $n = count($players);
        if ($n < 2) {
            return [];
        }

        // Chuẩn bị map rating ban đầu (tất cả E_i tính trên rating này)
        $initialRatings = [];
        foreach ($players as $p) {
            $initialRatings[(int)$p['user_id']] = (int)($p['rating'] ?? 1000);
        }

        // K được chia đều cho N-1 cặp mỗi người phải đấu
        $kPerPair = self::K_FACTOR / ($n - 1);

        // Bước 1: Tính tổng delta float cho từng người (chưa làm tròn)
        $floatDeltas = [];
        foreach ($players as $playerA) {
            $userIdA = (int)$playerA['user_id'];
            $ratingA = $initialRatings[$userIdA];
            $rankA   = (int)($playerA['rank'] ?? 1);
            $totalDelta = 0.0;

            foreach ($players as $playerB) {
                $userIdB = (int)$playerB['user_id'];
                if ($userIdA === $userIdB) continue;

                $ratingB = $initialRatings[$userIdB];
                $rankB   = (int)($playerB['rank'] ?? 1);

                // Điểm kỳ vọng (dùng rating BAN ĐẦU)
                $expectedA = 1.0 / (1.0 + pow(10.0, ($ratingB - $ratingA) / 400.0));

                // Kết quả thực tế
                $actualA = match (true) {
                    $rankA < $rankB  => 1.0,
                    $rankA === $rankB => 0.5,
                    default          => 0.0,
                };

                $totalDelta += $kPerPair * ($actualA - $expectedA);
            }

            $floatDeltas[$userIdA] = $totalDelta;
        }

        // Bước 2: Làm tròn delta của từng người sang int
        $intDeltas = [];
        foreach ($floatDeltas as $uid => $delta) {
            $intDeltas[$uid] = (int)round($delta);
        }

        // Bước 3: Điều chỉnh để đảm bảo tổng = 0 (zero-sum)
        // Nếu có lệch do làm tròn, điều chỉnh người có rank tốt nhất (index 0 sau sắp xếp theo rank)
        $sumDelta = array_sum($intDeltas);
        if ($sumDelta !== 0) {
            // Tìm người rank 1 (người thắng nhận phần điều chỉnh)
            $rank1UserId = null;
            $bestRank    = PHP_INT_MAX;
            foreach ($players as $p) {
                if ((int)$p['rank'] < $bestRank) {
                    $bestRank    = (int)$p['rank'];
                    $rank1UserId = (int)$p['user_id'];
                }
            }
            if ($rank1UserId !== null) {
                $intDeltas[$rank1UserId] -= $sumDelta; // Điều chỉnh để tổng = 0
            }
        }

        // Bước 4: Xây dựng kết quả
        $results = [];
        foreach ($players as $p) {
            $uId       = (int)$p['user_id'];
            $ratingA   = $initialRatings[$uId];
            $deltaInt  = $intDeltas[$uId];
            $newRating = max(100, $ratingA + $deltaInt);
            $tierInfo  = self::getTier($newRating);

            $results[$uId] = [
                'user_id'       => $uId,
                'rating_before' => $ratingA,
                'rating_after'  => $newRating,
                'rating_delta'  => $deltaInt,
                'tier_name'     => $tierInfo['name'],
                'tier_color'    => $tierInfo['color'],
                'tier_badge'    => $tierInfo['badge'],
            ];
        }

        return $results;
    }

    /**
     * Tính delta Elo cho một cặp đơn giản (helper cho unit test)
     *
     * @param int $ratingA Rating của người A
     * @param int $ratingB Rating của người B
     * @param float $actualA Kết quả A: 1.0 (thắng), 0.5 (hòa), 0.0 (thua)
     * @return array ['delta_a' => int, 'delta_b' => int]
     */
    public static function calculatePairDelta(int $ratingA, int $ratingB, float $actualA): array
    {
        $expectedA = 1.0 / (1.0 + pow(10.0, ($ratingB - $ratingA) / 400.0));
        $rawDelta  = self::K_FACTOR * ($actualA - $expectedA);
        $deltaA    = (int)round($rawDelta);
        // Đảm bảo delta_b đối ứng chính xác: delta_b = -delta_a (không làm tròn độc lập)
        $deltaB    = -$deltaA;
        return ['delta_a' => $deltaA, 'delta_b' => $deltaB];
    }
}
