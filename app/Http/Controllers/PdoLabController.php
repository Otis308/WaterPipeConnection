<?php

namespace App\Http\Controllers;

use App\Services\PdoDatabaseService;

/**
 * Class PdoLabController
 * Trình diễn trực quan toàn bộ truy vấn PDO thuần (Buổi 4) và SQL nâng cao đa bảng (Bài 5 - Lab 5):
 * - INNER JOIN, LEFT JOIN, GROUP BY, HAVING, Subquery, Aggregate Functions (COUNT, SUM, AVG, MIN, MAX)
 * - Phân trang LIMIT/OFFSET và Tìm kiếm LIKE với bindValue(PDO::PARAM_INT / PDO::PARAM_STR)
 */
class PdoLabController extends Controller
{
    private PdoDatabaseService $db;

    public function __construct()
    {
        $this->db = new PdoDatabaseService();
    }

    public function index()
    {
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        $keyword = trim($_GET['keyword'] ?? '');
        $difficulty = trim($_GET['difficulty'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $minLevels = max(1, (int)($_GET['min_levels'] ?? 2));
        $minSpent = max(0, (float)($_GET['min_spent'] ?? 200000));

        $paginatedLevels = $this->db->getPaginatedLevels($keyword, $difficulty, $page, 4);
        $lab5Ex1 = $this->db->getCategoryProductStatistics();
        $lab5Ex2 = $this->db->getDailyRevenueReport();
        $lab5Ex3 = $this->db->getCategoriesHavingMinLevels($minLevels);
        $lab5Ex4 = $this->db->getHighSpendingPlayers($minSpent);
        $lab5Ex5 = $this->db->getMostExpensiveItemPerCategory();
        $lab5Ex6 = $this->db->getNeverOrderedShopItems();
        $lab5Ex7 = $this->db->getGameModeTelemetryAnalytics();
        $driverName = $this->db->getDriverName();

        return view('pdo.index', compact(
            'currentUser',
            'paginatedLevels',
            'lab5Ex1',
            'lab5Ex2',
            'lab5Ex3',
            'lab5Ex4',
            'lab5Ex5',
            'lab5Ex6',
            'lab5Ex7',
            'keyword',
            'difficulty',
            'minLevels',
            'minSpent',
            'driverName'
        ));
    }
}
