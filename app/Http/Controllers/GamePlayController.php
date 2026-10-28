<?php

namespace App\Http\Controllers;

use App\Domain\PipeGame\Engine\PipeBoardSimulator;
use App\Domain\PipeGame\Payments\BankPayment;
use App\Domain\PipeGame\Payments\MomoPayment;
use App\Domain\PipeGame\Payments\PlayerWalletAccount;
use App\Services\PdoDatabaseService;


class GamePlayController extends Controller
{
    private PdoDatabaseService $db;

    public function __construct()
    {
        $this->db = new PdoDatabaseService();
    }

    /**
     * Trang chủ Game Nối Ống Nước (Giữ nguyên 100% giao diện index.html gốc)
     */
    public function home()
    {
        $leaderboard = $this->db->getLeaderboard('', 10);
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        return view('game.home', compact('leaderboard', 'currentUser'));
    }

    /**
     * Trang chơi Game Nối Ống Nước (Giữ nguyên 100% giao diện TrangGame.html gốc)
     */
    public function play()
    {
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        return view('game.play', compact('currentUser'));
    }

    /**
     * Cổng Tổng quan Kiến trúc Đồ án Cuối kỳ HUIT (Admin & Staff & User Portal)
     */
    public function portal()
    {
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        $leaderboard = $this->db->getLeaderboard('', 10);
        $recentSessions = $this->db->getRecentSessions(10);
        $categoryStats = $this->db->getCategoryProductStatistics();
        $aiAnalytics = $this->db->getGameModeTelemetryAnalytics();
        $allUsers = $this->db->getAllUsersWithProfiles();
        $dbDriver = $this->db->getDriverName();
        $adminStats = $this->db->getAdminDashboardStats();
        $allLevels = $this->db->getPaginatedLevels('', '', 1, 50)['items'] ?? [];
        $categories = $this->db->getAllCategories();
        $tags = $this->db->getAllTags();

        return view('portal.dashboard', compact(
            'currentUser',
            'leaderboard',
            'recentSessions',
            'categoryStats',
            'aiAnalytics',
            'allUsers',
            'dbDriver',
            'adminStats',
            'allLevels',
            'categories',
            'tags'
        ));
    }

    /**
     * Phòng Thực hành & Kiểm thử Lập trình Hướng đối tượng OOP PHP (Chương 2)
     */
    public function oopLab()
    {
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        $simulation = PipeBoardSimulator::runDemoScenario();

        // Minh họa Encapsulation: PlayerWalletAccount (Slide 6 & 27 Chương 2)
        $wallet = new PlayerWalletAccount($currentUser['username'] ?? 'player1', 500000);
        $wallet->deposit(250000);
        $wallet->withdraw(150000);

        // Minh họa Interface & Polymorphism: MomoPayment vs BankPayment (Slide 13-14 Chương 2)
        $simulator = new PipeBoardSimulator();
        $momoReceipt = $simulator->processItemPurchase(
            new MomoPayment('0987654321'),
            150000,
            'Bộ Ống Xanh Ngọc Bích (Jade Pipe)'
        );
        $bankReceipt = $simulator->processItemPurchase(
            new BankPayment('Vietcombank', '0071000888999'),
            450000,
            'Bộ Ống Vàng Hoàng Gia 24K'
        );

        $playerRank = PipeBoardSimulator::getPlayerRank(
            (int)($currentUser['high_score'] ?? 1120),
            80.0
        );

        return view('oop.index', compact(
            'currentUser',
            'simulation',
            'wallet',
            'momoReceipt',
            'bankReceipt',
            'playerRank'
        ));
    }
}
