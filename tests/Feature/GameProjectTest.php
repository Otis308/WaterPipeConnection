<?php

namespace Tests\Feature;

use App\Domain\PipeGame\Engine\PipeBoardSimulator;
use App\Services\PdoDatabaseService;
use App\Services\PvpBoardStateService;
use App\Services\PvpMapService;
use App\Services\PvpMatchService;
use App\Services\PvpRatingService;
use PHPUnit\Framework\TestCase;

/**
 * Test Suite toàn diện cho PvP Core Game — Giai đoạn 1
 *
 * Bao gồm:
 *  - Test 1: OOP PipeBoardSimulator (test cũ, giữ nguyên)
 *  - Test 2: BFS Board Validator (AC1 — Server Authority)
 *  - Test 3: Pipe rotation logic
 *  - Test 4: Chống giả mạo (AC1)
 *  - Test 5: Elo pairwise 2 người (AC6)
 *  - Test 6: Elo pairwise 3 người (AC6)
 *  - Test 7: Elo pairwise 4 người (AC6)
 *  - Test 8: Elo tổng delta = 0 (AC6 zero-sum)
 *  - Test 9: Seed Uniformity — map generation deterministic (AC3)
 *  - Test 10: MapService CAU_HINH_ONG khớp BFS defaults
 *  - Test 11: BFS từ chối kết nối sai hướng cổng
 *  - Test 12: BFS không đi qua Start/End của cặp khác
 *  - Test 13: Elo không cập nhật 2 lần (idempotency guard)
 */
class GameProjectTest extends TestCase
{
    // =========================================================================
    // TEST 1: OOP PipeBoardSimulator (giữ nguyên test cũ — AC7 regression)
    // =========================================================================
    public function test_oop_pipe_board_simulator_calculates_polymorphic_score(): void
    {
        $result = PipeBoardSimulator::runDemoScenario();
        $this->assertTrue($result['swap_yellow_test'], 'Hoán đổi 2 ống vàng liền kề phải thành công');
        $this->assertGreaterThan(0, $result['total_score_hard'], 'Điểm tổng phải > 0');
    }

    // =========================================================================
    // TEST 2: BFS getOpenPorts — xác minh cổng mở theo shape và rotation
    // =========================================================================
    public function test_bfs_get_open_ports_straight_pipe(): void
    {
        // Ống thẳng mặc định (rotation=0): cổng [0=Trên, 2=Dưới]
        $ports = PvpBoardStateService::getOpenPorts('Thang', 0);
        $this->assertEqualsCanonicalizing([0, 2], $ports, 'Ống thẳng rot=0 phải mở Trên và Dưới');

        // Ống thẳng xoay 90: cổng [1=Phải, 3=Trái]
        $ports90 = PvpBoardStateService::getOpenPorts('Thang', 90);
        $this->assertEqualsCanonicalizing([1, 3], $ports90, 'Ống thẳng rot=90 phải mở Phải và Trái');

        // Ống thẳng xoay 180: trở về [0=Trên, 2=Dưới]
        $ports180 = PvpBoardStateService::getOpenPorts('Thang', 180);
        $this->assertEqualsCanonicalizing([0, 2], $ports180, 'Ống thẳng rot=180 giống rot=0');
    }

    public function test_bfs_get_open_ports_corner_pipe(): void
    {
        // Ống chữ L mặc định (rotation=0): [1=Phải, 2=Dưới]
        $ports = PvpBoardStateService::getOpenPorts('ChuL', 0);
        $this->assertEqualsCanonicalizing([1, 2], $ports, 'Ống L rot=0 phải mở Phải và Dưới');

        // Ống chữ L xoay 90: [2=Dưới+1=3=Trái, 2+1=3=Trái] → [2, 3]
        $ports90 = PvpBoardStateService::getOpenPorts('ChuL', 90);
        $this->assertEqualsCanonicalizing([2, 3], $ports90, 'Ống L rot=90 phải mở Dưới và Trái');

        // Ống chữ L xoay 180: [3=Trái, 0=Trên]
        $ports180 = PvpBoardStateService::getOpenPorts('ChuL', 180);
        $this->assertEqualsCanonicalizing([3, 0], $ports180, 'Ống L rot=180 phải mở Trái và Trên');

        // Ống chữ L xoay 270: [0=Trên, 1=Phải]
        $ports270 = PvpBoardStateService::getOpenPorts('ChuL', 270);
        $this->assertEqualsCanonicalizing([0, 1], $ports270, 'Ống L rot=270 phải mở Trên và Phải');
    }

    public function test_bfs_get_open_ports_cross_pipe_unchanged(): void
    {
        // Ống chữ thập: tất cả 4 cổng luôn mở bất kể rotation
        foreach ([0, 90, 180, 270] as $rot) {
            $ports = PvpBoardStateService::getOpenPorts('ChuThap', $rot);
            $this->assertEqualsCanonicalizing([0, 1, 2, 3], $ports,
                "Ống chữ thập rot={$rot} phải mở cả 4 hướng");
        }
    }

    // =========================================================================
    // TEST 3: BFS verifyBoardSolution — simple 2-cell map (AC1)
    // =========================================================================
    public function test_bfs_verifies_simple_connected_pipe(): void
    {
        // Bản đồ tối giản: 2 ô theo chiều ngang
        // Ô (1,1): BatDau, rotation=90 (mở hướng Phải=1)
        // Ô (1,2): KetThuc, rotation=270 (port mặc định=[1], xoay 270/90=3 bước → (1+3)%4=0=Trên??? )
        // Cần tính đúng: KetThuc rotation=270, ports=(1+3)%4=0 → Trên. Không kết nối!
        // Thử lại: KetThuc ở vị trí (1,2) cần cổng Trái (3) để nhận từ BatDau hướng Phải
        // KetThuc rotation=90: (1+1)%4=2=Dưới. Không đúng.
        // KetThuc default ports=[1=Phải]. Để mở Trái=3 cần xoay 2 bước (180°): (1+2)%4=3=Trái ✓

        $mapData = [
            'numPairs'           => 1,
            'optimalTotalLength' => 2,
            'pipes' => [
                ['pairId' => 1, 'shortestLength' => 2],
            ],
            'cells' => [
                ['r' => 1, 'c' => 1, 'file' => 'OngBatDau.svg', 'initialRot' => 90,  'pairId' => 1, 'role' => 'start'],
                ['r' => 1, 'c' => 2, 'file' => 'OngKetThuc.svg', 'initialRot' => 180, 'pairId' => 1, 'role' => 'end'],
            ],
        ];

        // BatDau rotation=90: ports=(1+1)%4=2=Dưới. Không kết nối sang phải!
        // Để kết nối ngang: BatDau cần mở Phải (1) → rotation=0 (default port=[1])
        $mapData['cells'][0]['initialRot'] = 0;  // BatDau mở Phải

        // KetThuc rotation=180: ports=(1+2)%4=3=Trái ✓ (nhận từ Phải của BatDau)
        $boardRotations = ['1-1' => 0, '1-2' => 180];

        $result = PvpBoardStateService::verifyBoardSolution($mapData, $boardRotations);

        $this->assertTrue($result['is_all_completed'], 'BFS phải xác nhận kết nối thành công');
        $this->assertEquals(100, $result['official_score'], 'Điểm phải là 100 (dùng đúng độ dài tối ưu)');
        $this->assertEquals(1, $result['completed_pairs'], 'Phải hoàn thành 1 cặp');
    }

    public function test_bfs_rejects_disconnected_pipe(): void
    {
        // BatDau mở Phải nhưng KetThuc mở Trên (rotation=270) → không kết nối
        $mapData = [
            'numPairs'           => 1,
            'optimalTotalLength' => 2,
            'pipes'  => [['pairId' => 1, 'shortestLength' => 2]],
            'cells'  => [
                ['r' => 1, 'c' => 1, 'file' => 'OngBatDau.svg',  'initialRot' => 0,   'pairId' => 1, 'role' => 'start'],
                ['r' => 1, 'c' => 2, 'file' => 'OngKetThuc.svg', 'initialRot' => 270, 'pairId' => 1, 'role' => 'end'],
            ],
        ];

        // KetThuc rotation=270: ports=(1+3)%4=0=Trên → không kết nối với BatDau mở Phải
        $boardRotations = ['1-1' => 0, '1-2' => 270];

        $result = PvpBoardStateService::verifyBoardSolution($mapData, $boardRotations);

        $this->assertFalse($result['is_all_completed'], 'BFS phải từ chối đường ống không kết nối');
        $this->assertEquals(0, $result['completed_pairs'], 'Phải có 0 cặp hoàn thành');
    }

    // =========================================================================
    // TEST 4: Chặn giả mạo — BFS từ chối đi qua Start/End của cặp khác (AC1)
    // =========================================================================
    public function test_bfs_rejects_path_through_wrong_pair_endpoint(): void
    {
        // Bản đồ 3 ô ngang: BatDau(pair1) | BatDau(pair2) | KetThuc(pair1)
        // BFS của pair1 KHÔNG được đi qua BatDau của pair2
        $mapData = [
            'numPairs'           => 2,
            'optimalTotalLength' => 4,
            'pipes' => [
                ['pairId' => 1, 'shortestLength' => 2],
                ['pairId' => 2, 'shortestLength' => 2],
            ],
            'cells' => [
                ['r' => 1, 'c' => 1, 'file' => 'OngBatDau.svg',  'initialRot' => 0,   'pairId' => 1, 'role' => 'start'],
                ['r' => 1, 'c' => 2, 'file' => 'OngBatDau.svg',  'initialRot' => 0,   'pairId' => 2, 'role' => 'start'],
                ['r' => 1, 'c' => 3, 'file' => 'OngKetThuc.svg', 'initialRot' => 180, 'pairId' => 1, 'role' => 'end'],
                ['r' => 2, 'c' => 1, 'file' => 'OngKetThuc.svg', 'initialRot' => 90,  'pairId' => 2, 'role' => 'end'],
            ],
        ];

        $boardRotations = [
            '1-1' => 0,  // BatDau pair1: mở Phải
            '1-2' => 0,  // BatDau pair2: mở Phải (block path pair1)
            '1-3' => 180, // KetThuc pair1: mở Trái
            '2-1' => 90,  // KetThuc pair2: (1+1)%4=2=Dưới → từ BatDau pair2 cần mở Dưới
        ];

        $result = PvpBoardStateService::verifyBoardSolution($mapData, $boardRotations);

        // pair1 không thể đến (1,3) vì phải đi qua (1,2) là Start của pair2
        $pair1Result = array_values(array_filter($result['pairs_result'], fn($p) => $p['pair_id'] === 1))[0] ?? null;
        $this->assertNotNull($pair1Result);
        $this->assertFalse($pair1Result['is_connected'], 'BFS pair1 không được đi qua Start của pair2');
    }

    // =========================================================================
    // TEST 5 & 6 & 7: Elo pairwise (AC6)
    // =========================================================================
    public function test_elo_two_players_delta_reciprocal(): void
    {
        $players = [
            ['user_id' => 1, 'rating' => 1200, 'rank' => 1],
            ['user_id' => 2, 'rating' => 1000, 'rank' => 2],
        ];

        $result = PvpRatingService::calculateMatchEloDeltas($players);

        $delta1 = $result[1]['rating_delta'];
        $delta2 = $result[2]['rating_delta'];

        // delta1 + delta2 phải = 0 (zero-sum)
        $this->assertEquals(0, $delta1 + $delta2,
            "Tổng delta Elo 2 người phải = 0. delta1={$delta1}, delta2={$delta2}");

        // Người thắng (rank=1) phải có delta dương
        $this->assertGreaterThan(0, $delta1, 'Người thắng phải được điểm dương');
        $this->assertLessThan(0, $delta2, 'Người thua phải mất điểm');
    }

    public function test_elo_three_players_zero_sum(): void
    {
        $players = [
            ['user_id' => 1, 'rating' => 1200, 'rank' => 1],
            ['user_id' => 2, 'rating' => 1100, 'rank' => 2],
            ['user_id' => 3, 'rating' => 1050, 'rank' => 3],
        ];

        $result = PvpRatingService::calculateMatchEloDeltas($players);

        $totalDelta = array_sum(array_column($result, 'rating_delta'));
        $this->assertEquals(0, $totalDelta,
            "Tổng delta Elo 3 người phải = 0 (zero-sum). Total={$totalDelta}");
    }

    public function test_elo_four_players_zero_sum(): void
    {
        $players = [
            ['user_id' => 1, 'rating' => 1500, 'rank' => 1],
            ['user_id' => 2, 'rating' => 1300, 'rank' => 2],
            ['user_id' => 3, 'rating' => 1100, 'rank' => 3],
            ['user_id' => 4, 'rating' => 900,  'rank' => 4],
        ];

        $result = PvpRatingService::calculateMatchEloDeltas($players);

        $this->assertCount(4, $result, 'Phải có kết quả cho cả 4 người chơi');
        $totalDelta = array_sum(array_column($result, 'rating_delta'));
        $this->assertEquals(0, $totalDelta,
            "Tổng delta Elo 4 người phải = 0. Total={$totalDelta}");
    }

    public function test_elo_rating_before_saved_correctly(): void
    {
        $players = [
            ['user_id' => 10, 'rating' => 1500, 'rank' => 1],
            ['user_id' => 20, 'rating' => 1200, 'rank' => 2],
        ];
        $result = PvpRatingService::calculateMatchEloDeltas($players);

        $this->assertEquals(1500, $result[10]['rating_before'], 'Rating trước của người 10 phải là 1500');
        $this->assertEquals(1200, $result[20]['rating_before'], 'Rating trước của người 20 phải là 1200');
        $this->assertEquals($result[10]['rating_before'] + $result[10]['rating_delta'], $result[10]['rating_after']);
        $this->assertEquals($result[20]['rating_before'] + $result[20]['rating_delta'], $result[20]['rating_after']);
    }

    public function test_elo_pair_delta_is_reciprocal(): void
    {
        // Test helper calculatePairDelta: delta_b = -delta_a
        $pair = PvpRatingService::calculatePairDelta(1000, 1200, 1.0); // A thắng B
        $this->assertEquals(-$pair['delta_a'], $pair['delta_b'],
            'delta_b phải là -delta_a (đối ứng chính xác)');
        $this->assertGreaterThan(0, $pair['delta_a'], 'Người thắng khi rating thấp hơn phải được nhiều điểm hơn');
    }

    // =========================================================================
    // TEST 8: Seed Uniformity — generateMap deterministic (AC3)
    // =========================================================================
    public function test_map_generation_is_deterministic_with_same_seed(): void
    {
        $seed = 'HUIT_PVP_TEST_SEED_12345';
        $map1 = PvpMapService::generateMap('5x8', 'medium', 2, $seed);
        $map2 = PvpMapService::generateMap('5x8', 'medium', 2, $seed);

        $this->assertEquals($map1['seedKey'], $map2['seedKey'], 'seedKey phải khớp');
        $this->assertEquals($map1['numPairs'], $map2['numPairs'], 'numPairs phải khớp');
        $this->assertEquals($map1['optimalTotalLength'], $map2['optimalTotalLength'], 'optimalLength phải khớp');

        // Hash cấu hình cells phải giống nhau
        $hash1 = md5(json_encode($map1['cells']));
        $hash2 = md5(json_encode($map2['cells']));
        $this->assertEquals($hash1, $hash2,
            'Cấu hình cells phải giống hệt nhau khi dùng cùng seed (AC3 Seed Uniformity)');
    }

    public function test_different_seeds_produce_different_maps(): void
    {
        $map1 = PvpMapService::generateMap('5x8', 'medium', 2, 'SEED_AAA');
        $map2 = PvpMapService::generateMap('5x8', 'medium', 2, 'SEED_BBB');

        $hash1 = md5(json_encode($map1['cells']));
        $hash2 = md5(json_encode($map2['cells']));
        $this->assertNotEquals($hash1, $hash2, '2 seed khác nhau phải tạo ra bản đồ khác nhau');
    }

    // =========================================================================
    // TEST 9: CAU_HINH_ONG trong PvpMapService khớp DEFAULT_PORTS trong BFS
    // =========================================================================
    public function test_map_service_config_matches_bfs_default_ports(): void
    {
        $mapConfig = PvpMapService::CAU_HINH_ONG;

        // Thang = [0, 2] → BFS getOpenPorts('Thang', 0) = [0, 2]
        $this->assertEqualsCanonicalizing($mapConfig['Thang'],
            PvpBoardStateService::getOpenPorts('Thang', 0),
            'Cấu hình Thang trong PvpMapService phải khớp BFS default ports');

        // ChuL = [1, 2] → BFS getOpenPorts('ChuL', 0) = [1, 2]
        $this->assertEqualsCanonicalizing($mapConfig['ChuL'],
            PvpBoardStateService::getOpenPorts('ChuL', 0),
            'Cấu hình ChuL phải khớp BFS');

        // ChuThap = [0, 1, 2, 3]
        $this->assertEqualsCanonicalizing($mapConfig['ChuThap'],
            PvpBoardStateService::getOpenPorts('ChuThap', 0),
            'Cấu hình ChuThap phải khớp BFS');
    }

    // =========================================================================
    // TEST 10: BFS từ chối kết nối một chiều (unidirectional port)
    // =========================================================================
    public function test_bfs_rejects_unidirectional_connection(): void
    {
        // Ô A mở Phải nhưng Ô B không mở Trái → không kết nối
        $mapData = [
            'numPairs'           => 1,
            'optimalTotalLength' => 2,
            'pipes'  => [['pairId' => 1, 'shortestLength' => 2]],
            'cells'  => [
                ['r' => 1, 'c' => 1, 'file' => 'OngBatDau.svg',   'initialRot' => 0,  'pairId' => 1, 'role' => 'start'],
                // Ống thẳng xoay 0: mở Trên(0) và Dưới(2) — KHÔNG mở Trái(3)
                ['r' => 1, 'c' => 2, 'shape' => 'Thang',           'initialRot' => 0,  'pairId' => 1],
                ['r' => 1, 'c' => 3, 'file' => 'OngKetThuc.svg',   'initialRot' => 180, 'pairId' => 1, 'role' => 'end'],
            ],
        ];

        $boardRotations = ['1-1' => 0, '1-2' => 0, '1-3' => 180];
        $result = PvpBoardStateService::verifyBoardSolution($mapData, $boardRotations);

        // BatDau(1,1) mở Phải → Ống(1,2) cần mở Trái nhưng Thang rot=0 chỉ mở Trên/Dưới
        $this->assertFalse($result['is_all_completed'],
            'BFS phải từ chối kết nối khi ô kế tiếp không có cổng tương hỗ (Trái)');
    }

    // =========================================================================
    // TEST 11: MapService tạo bản đồ có đúng số cặp
    // =========================================================================
    public function test_map_has_correct_number_of_pairs(): void
    {
        for ($numPairs = 2; $numPairs <= 4; $numPairs++) {
            $map = PvpMapService::generateMap('5x8', 'easy', $numPairs, "SEED_PAIR_{$numPairs}");
            $this->assertCount($numPairs, $map['pipes'],
                "Bản đồ {$numPairs} cặp phải có đúng {$numPairs} phần tử pipes");
            $this->assertEquals($numPairs, $map['numPairs'],
                "numPairs trong map data phải là {$numPairs}");
        }
    }

    // =========================================================================
    // TEST 12: BFS trừ điểm khi dùng đường vòng và từ chối thắng nếu < 100 điểm
    // =========================================================================
    public function test_bfs_calculates_detour_penalty_and_rejects_completion_below_100(): void
    {
        // Bản đồ 1 cặp, đường đi tối ưu = 2 ô
        // Nhưng đường đi thực tế dài 3 ô (đi qua 1 ô ống Thang ở giữa)
        // Extra cells = 3 - 2 = 1 ô thừa. Trừ 10đ. Điểm chính thức = 90đ.
        $mapData = [
            'numPairs'           => 1,
            'optimalTotalLength' => 2,
            'pipes'  => [['pairId' => 1, 'shortestLength' => 2]],
            'cells'  => [
                ['r' => 1, 'c' => 1, 'file' => 'OngBatDau.svg',  'initialRot' => 0,   'pairId' => 1, 'role' => 'start'],
                ['r' => 1, 'c' => 2, 'shape' => 'Thang',          'initialRot' => 90,  'pairId' => 1],
                ['r' => 1, 'c' => 3, 'file' => 'OngKetThuc.svg', 'initialRot' => 180, 'pairId' => 1, 'role' => 'end'],
            ],
        ];

        // Ô (1,1) BatDau rot=0 (port 1=Phải)
        // Ô (1,2) Thang rot=90 (ports 1=Phải, 3=Trái)
        // Ô (1,3) KetThuc rot=180 (port 3=Trái)
        $boardRotations = ['1-1' => 0, '1-2' => 90, '1-3' => 180];

        $result = PvpBoardStateService::verifyBoardSolution($mapData, $boardRotations);

        $this->assertEquals(1, $result['completed_pairs'], 'Đường ống đã nối thông 1 cặp');
        $this->assertEquals(90, $result['official_score'], 'Điểm phải bị trừ 10 điểm do dùng đường đi dài hơn tối ưu');
        $this->assertFalse($result['is_all_completed'], 'Chưa đạt 100 điểm thì is_all_completed phải là false');
    }

    // =========================================================================
    // TEST 13: Xác minh thao tác xoay - chỉ chấp nhận ống xoay được và góc hợp lệ
    // =========================================================================
    public function test_board_state_service_validates_cell_types_for_rotation(): void
    {
        $service = new PvpBoardStateService();
        $mapData = [
            'cells' => [
                ['r' => 1, 'c' => 1, 'file' => 'OngBatDau.svg'],
                ['r' => 1, 'c' => 2, 'file' => 'OngKetThuc.svg'],
                ['r' => 1, 'c' => 3, 'file' => 'OngDo_Thang.svg'],
                ['r' => 1, 'c' => 4, 'file' => 'OngVang_Thang.svg'],
                ['r' => 1, 'c' => 5, 'file' => 'OngXanh_Thang.svg'],
            ],
        ];

        // Góc xoay sai (chỉ chấp nhận 90, 180, 270)
        $resAngle = $service->applyRotation(1, 1, 5, 45, $mapData);
        $this->assertFalse($resAngle['success'], 'Góc xoay 45 độ phải bị từ chối');

        // BatDau không xoay được
        $resBatDau = $service->applyRotation(1, 1, 1, 90, $mapData);
        $this->assertFalse($resBatDau['success'], 'OngBatDau không được phép xoay');

        // KetThuc không xoay được
        $resKetThuc = $service->applyRotation(1, 1, 2, 90, $mapData);
        $this->assertFalse($resKetThuc['success'], 'OngKetThuc không được phép xoay');

        // OngDo không xoay được
        $resOngDo = $service->applyRotation(1, 1, 3, 90, $mapData);
        $this->assertFalse($resOngDo['success'], 'OngDo không được phép xoay');

        // OngVang không xoay được (chỉ hoán đổi)
        $resOngVang = $service->applyRotation(1, 1, 4, 90, $mapData);
        $this->assertFalse($resOngVang['success'], 'OngVang không được phép xoay');
    }

    // =========================================================================
    // TEST 14: Chặn gửi điểm 100 và is_winner giả trong submitMoveOrProgress
    // =========================================================================
    public function test_match_service_rejects_fake_win_and_score_over_99_in_progress(): void
    {
        $db = new PdoDatabaseService();
        $conn = $db->getConnection();
        $userId = (int)$conn->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        if (!$userId) {
            $this->markTestSkipped('Không tìm thấy user nào trong database để test match service');
            return;
        }

        // Tạo phòng test in_progress
        $roomCode = 'TESTFAKEROOM';
        $conn->prepare("DELETE FROM pvp_room_players WHERE room_id IN (SELECT id FROM pvp_rooms WHERE room_code = ?)")->execute([$roomCode]);
        $conn->prepare("DELETE FROM pvp_rooms WHERE room_code = ?")->execute([$roomCode]);

        $conn->prepare("
            INSERT INTO pvp_rooms (room_code, host_user_id, room_type, max_players, current_players, status, map_size, difficulty, num_pairs, time_limit_seconds, map_config_json)
            VALUES (?, ?, 'custom', 2, 1, 'in_progress', '5x8', 'easy', 1, 300, '{}')
        ")->execute([$roomCode, $userId]);
        $roomId = (int)$conn->lastInsertId();

        $conn->prepare("
            INSERT INTO pvp_room_players (room_id, user_id, player_name, seat_number, is_host, is_ready, connection_status, solve_status, initial_rating, total_pairs)
            VALUES (?, ?, 'TesterFake', 1, 1, 1, 'connected', 'playing', 1000, 1)
        ")->execute([$roomId, $userId]);

        $matchService = new PvpMatchService($db);

        // Client cố tình gửi score = 100 trong progress -> Phải bị từ chối
        $fakeScoreRes = $matchService->submitMoveOrProgress($userId, $roomCode, ['score' => 100]);
        $this->assertFalse($fakeScoreRes['success'], 'Client không được phép gửi score = 100 qua submitMoveOrProgress');
        $this->assertStringContainsString('Điểm 100', $fakeScoreRes['message']);

        // Client cố tình gửi is_winner = true trong progress -> Phải bị từ chối
        $fakeWinRes = $matchService->submitMoveOrProgress($userId, $roomCode, ['is_winner' => true]);
        $this->assertFalse($fakeWinRes['success'], 'Client không được phép gửi is_winner = true qua submitMoveOrProgress');
        $this->assertStringContainsString('Kết quả thắng phải do server xác minh', $fakeWinRes['message']);

        // Cleanup
        $conn->prepare("DELETE FROM pvp_room_players WHERE room_id = ?")->execute([$roomId]);
        $conn->prepare("DELETE FROM pvp_rooms WHERE id = ?")->execute([$roomId]);
    }

    // =========================================================================
    // TEST 15: Chặn force_win giả mạo và từ chối hoàn thành khi bàn cờ chưa giải xong
    // =========================================================================
    public function test_match_service_submit_solution_rejects_fake_win_and_incomplete_solution(): void
    {
        $db = new PdoDatabaseService();
        $conn = $db->getConnection();
        $userId = (int)$conn->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        if (!$userId) {
            $this->markTestSkipped('Không tìm thấy user nào trong database để test match service');
            return;
        }

        $roomCode = 'TESTSOLROOM';
        $conn->prepare("DELETE FROM pvp_room_players WHERE room_id IN (SELECT id FROM pvp_rooms WHERE room_code = ?)")->execute([$roomCode]);
        $conn->prepare("DELETE FROM pvp_rooms WHERE room_code = ?")->execute([$roomCode]);

        $mapData = [
            'numPairs'           => 1,
            'optimalTotalLength' => 2,
            'pipes'  => [['pairId' => 1, 'shortestLength' => 2]],
            'cells'  => [
                ['r' => 1, 'c' => 1, 'file' => 'OngBatDau.svg',  'initialRot' => 0,   'pairId' => 1, 'role' => 'start'],
                ['r' => 1, 'c' => 2, 'file' => 'OngKetThuc.svg', 'initialRot' => 270, 'pairId' => 1, 'role' => 'end'], // Đứt đoạn
            ],
        ];

        $conn->prepare("
            INSERT INTO pvp_rooms (room_code, host_user_id, room_type, max_players, current_players, status, map_size, difficulty, num_pairs, time_limit_seconds, map_config_json)
            VALUES (?, ?, 'custom', 2, 1, 'in_progress', '5x8', 'easy', 1, 300, ?)
        ")->execute([$roomCode, $userId, json_encode($mapData)]);
        $roomId = (int)$conn->lastInsertId();

        $conn->prepare("
            INSERT INTO pvp_room_players (room_id, user_id, player_name, seat_number, is_host, is_ready, connection_status, solve_status, initial_rating, total_pairs, board_rotations_json)
            VALUES (?, ?, 'TesterSol', 1, 1, 1, 'connected', 'playing', 1000, 1, ?)
        ")->execute([$roomId, $userId, json_encode(['1-1' => 0, '1-2' => 270])]);

        $matchService = new PvpMatchService($db);

        // 1. Client gửi cờ force_win hoặc is_winner -> Bị chặn ngay
        $resForce = $matchService->submitSolution($userId, $roomCode, ['force_win' => true]);
        $this->assertFalse($resForce['success'], 'Cờ force_win phải bị từ chối');

        // 2. Client gửi bài giải đứt đoạn -> BFS server phát hiện, từ chối chiến thắng
        $resIncomplete = $matchService->submitSolution($userId, $roomCode, ['finish_time_ms' => 15000]);
        $this->assertFalse($resIncomplete['success'], 'Bàn cờ chưa nối đúng phải bị từ chối chiến thắng');
        $this->assertFalse($resIncomplete['is_completed'], 'is_completed phải là false');

        // Kiểm tra trong DB: solve_status vẫn là playing, chưa được gán rank
        $stmt = $conn->prepare("SELECT solve_status, final_rank FROM pvp_room_players WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$roomId, $userId]);
        $p = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals('playing', $p['solve_status'], 'solve_status phải được giữ nguyên là playing');
        $this->assertEquals(null, $p['final_rank'], 'Người chưa hoàn thành không được gán final_rank');

        // Cleanup
        $conn->prepare("DELETE FROM pvp_room_players WHERE room_id = ?")->execute([$roomId]);
        $conn->prepare("DELETE FROM pvp_rooms WHERE id = ?")->execute([$roomId]);
    }

    // =========================================================================
    // TEST 16: Idempotency khi nộp bài nhiều lần
    // =========================================================================
    public function test_match_service_submit_solution_idempotency(): void
    {
        $db = new PdoDatabaseService();
        $conn = $db->getConnection();
        $userId = (int)$conn->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        if (!$userId) {
            $this->markTestSkipped('Không tìm thấy user nào trong database để test match service');
            return;
        }

        $roomCode = 'TESTIDEMROOM';
        $conn->prepare("DELETE FROM pvp_room_players WHERE room_id IN (SELECT id FROM pvp_rooms WHERE room_code = ?)")->execute([$roomCode]);
        $conn->prepare("DELETE FROM pvp_rooms WHERE room_code = ?")->execute([$roomCode]);

        $conn->prepare("
            INSERT INTO pvp_rooms (room_code, host_user_id, room_type, max_players, current_players, status, map_size, difficulty, num_pairs, time_limit_seconds, map_config_json)
            VALUES (?, ?, 'custom', 2, 1, 'in_progress', '5x8', 'easy', 1, 300, '{}')
        ")->execute([$roomCode, $userId]);
        $roomId = (int)$conn->lastInsertId();

        // Người chơi đã completed với rank 1, score 100
        $conn->prepare("
            INSERT INTO pvp_room_players (room_id, user_id, player_name, seat_number, is_host, is_ready, connection_status, solve_status, initial_rating, total_pairs, current_score, final_rank)
            VALUES (?, ?, 'TesterIdem', 1, 1, 1, 'connected', 'completed', 1000, 1, 100, 1)
        ")->execute([$roomId, $userId]);

        $matchService = new PvpMatchService($db);

        // Nộp lại lần 2
        $res = $matchService->submitSolution($userId, $roomCode, ['finish_time_ms' => 20000]);
        $this->assertTrue($res['success'], 'Nộp lặp khi đã completed phải trả về success idempotent');
        $this->assertEquals(100, $res['score'], 'Điểm phải giữ nguyên');
        $this->assertStringContainsString('hoàn thành bài thi trước đó', $res['message']);

        // Cleanup
        $conn->prepare("DELETE FROM pvp_room_players WHERE room_id = ?")->execute([$roomId]);
        $conn->prepare("DELETE FROM pvp_rooms WHERE id = ?")->execute([$roomId]);
    }

    // =========================================================================
    // TEST 17: Từ chối phòng không tồn tại hoặc người chơi không hợp lệ
    // =========================================================================
    public function test_match_service_rejects_unauthorized_room_or_player(): void
    {
        $matchService = new PvpMatchService();

        // Phòng không tồn tại
        $resRoom = $matchService->submitSolution(12345, 'NON_EXISTENT_ROOM_XYZ', []);
        $this->assertFalse($resRoom['success'], 'Phòng không tồn tại phải bị từ chối');

        $resProg = $matchService->submitMoveOrProgress(12345, 'NON_EXISTENT_ROOM_XYZ', []);
        $this->assertFalse($resProg['success'], 'Phòng không tồn tại phải bị từ chối');
    }
}
