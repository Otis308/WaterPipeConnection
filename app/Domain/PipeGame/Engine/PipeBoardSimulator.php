<?php

namespace App\Domain\PipeGame\Engine;

use App\Domain\PipeGame\Contracts\PaymentInterface;
use App\Domain\PipeGame\Pipes\AbstractPipe;
use App\Domain\PipeGame\Pipes\CornerPipe;
use App\Domain\PipeGame\Pipes\CrossPipe;
use App\Domain\PipeGame\Pipes\StraightPipe;

/**
 * Class PipeBoardSimulator
 * Tổng hợp kiến thức OOP Chương 2: Quản lý danh sách đối tượng AbstractPipe đa hình,
 * tính điểm xếp hạng người chơi (getRank - Slide 27) và xử lý thanh toán qua PaymentInterface.
 */
class PipeBoardSimulator
{
    /** @var AbstractPipe[] */
    private array $pipes = [];

    public function addPipe(AbstractPipe $pipe): void
    {
        $this->pipes[] = $pipe;
    }

    /**
     * Tính tổng điểm dòng chảy bằng tính Đa hình (Polymorphism):
     * Không cần dùng if/switch kiểm tra từng loại ống, chỉ gọi $pipe->calculateFlowScore()!
     */
    public function calculateTotalBoardScore(string $difficulty = 'hard'): int
    {
        $total = 0;
        foreach ($this->pipes as $pipe) {
            $total += $pipe->calculateFlowScore($difficulty);
        }
        return $total;
    }

    public function exportBoardState(): array
    {
        return array_map(fn(AbstractPipe $pipe) => $pipe->getPipeSummary(), $this->pipes);
    }

    /**
     * Xếp hạng kỹ năng người chơi dựa trên điểm số và tỉ lệ thắng (Bài tập Slide 27 Chương 2)
     */
    public static function getPlayerRank(int $highScore, float $winRate): string
    {
        if ($highScore >= 1000 && $winRate >= 70.0) {
            return 'Tổng Công Trình Sư Thủy Lợi (Grandmaster)';
        }
        if ($highScore >= 700 && $winRate >= 50.0) {
            return 'Kỹ Sư Trưởng (Senior Hydro Engineer)';
        }
        if ($highScore >= 300) {
            return 'Kỹ Thuật Viên Đường Ống (Technician)';
        }
        return 'Tập Sự Nối Ống (Apprentice)';
    }

    public function processItemPurchase(PaymentInterface $paymentGateway, float $amount, string $itemName): array
    {
        return $paymentGateway->getTransactionReceipt($amount, $itemName);
    }

    /**
     * Tạo kịch bản mẫu để hiển thị trên trang OOP Lab & kiểm thử tự động
     */
    public static function runDemoScenario(): array
    {
        $simulator = new self();

        $p1 = new StraightPipe(1, 2, 90, 'OngXanh');
        $p2 = new CornerPipe(1, 3, 180, 'OngVang');
        $p3 = new CrossPipe(2, 3, 0, 'OngXanh');
        $p4 = new CornerPipe(1, 4, 270, 'OngVang');

        // Thử xoay ống xanh và tráo đổi 2 ống vàng liền kề
        $p1->rotate(90);
        $swapSuccess = $p2->swapPositionWith($p4);
        $connectedP1P2 = $p1->canConnectWith($p3, 1);

        $simulator->addPipe($p1);
        $simulator->addPipe($p2);
        $simulator->addPipe($p3);
        $simulator->addPipe($p4);

        return [
            'pipes' => $simulator->exportBoardState(),
            'swap_yellow_test' => $swapSuccess,
            'connection_test' => $connectedP1P2,
            'total_score_easy' => $simulator->calculateTotalBoardScore('easy'),
            'total_score_hard' => $simulator->calculateTotalBoardScore('hard'),
        ];
    }
}
