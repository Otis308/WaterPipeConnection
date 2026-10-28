<?php

namespace App\Http\Controllers;

use App\Policies\GameLevelPolicy;
use App\Rules\NoForbiddenWords;
use App\Rules\ValidMapDimension;
use App\Services\PdoDatabaseService;


/**
 * Class GameLevelController
 * Minh họa Bài 7, Bài 8 & Bài 9 (Resource Controller 7 actions chuẩn RESTful):
 * - index(): Danh sách màn chơi có Eager Loading (category, creator, tags), tìm kiếm & phân trang.
 * - create(): Hiển thị form tạo màn chơi mới (Yêu cầu đăng nhập Admin/Staff).
 * - store(): Kiểm tra Validation (bao gồm NoForbiddenWords, ValidMapDimension, File Upload) và lưu CSDL.
 * - show(): Xem chi tiết màn chơi (Route Model Binding).
 * - edit(): Hiển thị form chỉnh sửa màn chơi (Kiểm tra Policy ownership).
 * - update(): Cập nhật màn chơi và đồng bộ bảng trung gian N-N (sync tags).
 * - destroy(): Xóa màn chơi an toàn với @csrf + @method('DELETE').
 */
class GameLevelController extends Controller
{
    private PdoDatabaseService $db;

    public function __construct()
    {
        $this->db = new PdoDatabaseService();
    }

    /**
     * Action 1/7: GET /levels (levels.index)
     */
    public function index($request = null)
    {
        $keyword = trim($_GET['keyword'] ?? '');
        $difficulty = trim($_GET['difficulty'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));

        $pagination = $this->db->getPaginatedLevels($keyword, $difficulty, $page, 5);
        $categories = $this->db->getAllCategories();
        $tags = $this->db->getAllTags();
        $currentUser = $_SESSION['huit_auth_user'] ?? null;

        return view('levels.index', compact('pagination', 'categories', 'tags', 'currentUser', 'keyword', 'difficulty'));
    }

    /**
     * Action 2/7: GET /levels/create (levels.create)
     */
    public function create($request = null)
    {
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        if (!$currentUser || !in_array($currentUser['role'], ['admin', 'staff'], true)) {
            $_SESSION['flash_error'] = 'Chỉ Quản trị viên (Admin) hoặc Nhân viên thiết kế (Staff) mới có quyền tạo màn chơi mới!';
            return redirect('/levels');
        }

        $categories = $this->db->getAllCategories();
        $tags = $this->db->getAllTags();
        $allowedSizes = ValidMapDimension::ALLOWED_SIZES;

        return view('levels.create', compact('categories', 'tags', 'allowedSizes', 'currentUser'));
    }

    /**
     * Action 3/7: POST /levels (levels.store)
     */
    public function store($request = null)
    {
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        if (!$currentUser || !in_array($currentUser['role'], ['admin', 'staff'], true)) {
            abort(403, 'Bạn không có quyền tạo màn chơi mới.');
        }

        $errors = $this->validateLevelInput($_POST, $_FILES);
        if (!empty($errors)) {
            $_SESSION['validation_errors'] = $errors;
            $_SESSION['old_input'] = $_POST;
            return redirect('/levels/create');
        }

        $thumbnailPath = 'Images/NenNho.jpg';
        if (!empty($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $fileName = 'level_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
            $destDir = dirname(__DIR__, 3) . '/public/storage/levels';
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0775, true);
            }
            if (@move_uploaded_file($_FILES['thumbnail']['tmp_name'], $destDir . '/' . $fileName)) {
                $thumbnailPath = 'storage/levels/' . $fileName;
            }
        }

        $slug = $this->makeSlug($_POST['title']) . '-' . mt_rand(10, 99);
        $this->db->createLevel([
            'category_id' => (int)$_POST['category_id'],
            'created_by' => (int)$currentUser['id'],
            'title' => trim($_POST['title']),
            'slug' => $slug,
            'map_size' => $_POST['map_size'],
            'difficulty' => $_POST['difficulty'],
            'time_limit_seconds' => (int)$_POST['time_limit_seconds'],
            'reward_stars' => (int)$_POST['reward_stars'],
            'thumbnail' => $thumbnailPath,
            'description' => trim($_POST['description']),
            'tag_ids' => $_POST['tag_ids'] ?? [],
            'is_published' => 1,
        ]);

        $_SESSION['flash_success'] = 'Tạo màn chơi mới thành công (Đã kiểm tra Form Request & đồng bộ Tags N-N).';
        return redirect('/levels');
    }

    /**
     * Action 4/7: GET /levels/{id} (levels.show)
     */
    public function show(int $id)
    {
        $level = $this->db->findLevelById($id);
        if (!$level) {
            abort(404, 'Không tìm thấy màn chơi yêu cầu (Route Model Binding 404).');
        }
        $allTags = $this->db->getAllTags();
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        return view('levels.show', compact('level', 'allTags', 'currentUser'));
    }

    /**
     * Action 5/7: GET /levels/{id}/edit (levels.edit)
     */
    public function edit(int $id)
    {
        $level = $this->db->findLevelById($id);
        if (!$level) {
            abort(404, 'Không tìm thấy màn chơi.');
        }

        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        if (!GameLevelPolicy::canModify($currentUser, $level)) {
            $_SESSION['flash_error'] = 'Phân quyền Policy 403: Bạn chỉ được phép chỉnh sửa màn chơi do chính mình tạo (hoặc đăng nhập quyền Admin)!';
            return redirect('/levels');
        }

        $categories = $this->db->getAllCategories();
        $tags = $this->db->getAllTags();
        $allowedSizes = ValidMapDimension::ALLOWED_SIZES;

        return view('levels.edit', compact('level', 'categories', 'tags', 'allowedSizes', 'currentUser'));
    }

    /**
     * Action 6/7: PUT /levels/{id} (levels.update)
     */
    public function update(int $id)
    {
        $level = $this->db->findLevelById($id);
        if (!$level) {
            abort(404, 'Không tìm thấy màn chơi.');
        }

        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        if (!GameLevelPolicy::canModify($currentUser, $level)) {
            abort(403, 'Policy Authorization: Không có quyền cập nhật màn chơi của người khác.');
        }

        $errors = $this->validateLevelInput($_POST, $_FILES, true);
        if (!empty($errors)) {
            $_SESSION['validation_errors'] = $errors;
            $_SESSION['old_input'] = $_POST;
            return redirect("/levels/{$id}/edit");
        }

        $thumbnailPath = $level['thumbnail'];
        if (!empty($_FILES['thumbnail']['name']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $fileName = 'level_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
            $destDir = dirname(__DIR__, 3) . '/public/storage/levels';
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0775, true);
            }
            if (@move_uploaded_file($_FILES['thumbnail']['tmp_name'], $destDir . '/' . $fileName)) {
                $thumbnailPath = 'storage/levels/' . $fileName;
            }
        }

        $this->db->updateLevel($id, [
            'category_id' => (int)$_POST['category_id'],
            'title' => trim($_POST['title']),
            'map_size' => $_POST['map_size'],
            'difficulty' => $_POST['difficulty'],
            'time_limit_seconds' => (int)$_POST['time_limit_seconds'],
            'reward_stars' => (int)$_POST['reward_stars'],
            'thumbnail' => $thumbnailPath,
            'description' => trim($_POST['description']),
            'tag_ids' => $_POST['tag_ids'] ?? [],
            'is_published' => 1,
        ]);

        $_SESSION['flash_success'] = "Cập nhật màn chơi #{$id} thành công!";
        return redirect('/levels');
    }

    /**
     * Action 7/7: DELETE /levels/{id} (levels.destroy)
     */
    public function destroy(int $id)
    {
        $level = $this->db->findLevelById($id);
        if (!$level) {
            abort(404, 'Không tìm thấy màn chơi.');
        }

        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        if (!GameLevelPolicy::canModify($currentUser, $level)) {
            $_SESSION['flash_error'] = 'Policy Authorization 403: Chỉ Admin hoặc tác giả tạo màn chơi mới có quyền xóa!';
            return redirect('/levels');
        }

        $this->db->deleteLevel($id);
        $_SESSION['flash_success'] = "Đã xóa màn chơi #{$id} khỏi hệ thống.";
        return redirect('/levels');
    }

    private function validateLevelInput(array $input, array $files, bool $isUpdate = false): array
    {
        $errors = [];
        $title = trim($input['title'] ?? '');
        if ($title === '') {
            $errors['title'] = 'Tiêu đề màn chơi không được để trống.';
        } elseif (mb_strlen($title) < 5) {
            $errors['title'] = 'Tiêu đề màn chơi phải có ít nhất 5 ký tự.';
        } else {
            $forbiddenErr = NoForbiddenWords::checkString($title);
            if ($forbiddenErr) {
                $errors['title'] = 'Tiêu đề màn chơi: ' . $forbiddenErr;
            }
        }

        if (empty($input['category_id'])) {
            $errors['category_id'] = 'Vui lòng chọn danh mục chủ đề.';
        }

        $mapSize = $input['map_size'] ?? '';
        if (!in_array($mapSize, ValidMapDimension::ALLOWED_SIZES, true)) {
            $errors['map_size'] = 'Kích thước bản đồ phải thuộc chuẩn 3x8 đến 5x12.';
        }

        $difficulty = $input['difficulty'] ?? '';
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $errors['difficulty'] = 'Độ khó chỉ nhận giá trị Easy, Medium hoặc Hard.';
        }

        $desc = trim($input['description'] ?? '');
        if (mb_strlen($desc) < 10) {
            $errors['description'] = 'Mô tả chi tiết phải có tối thiểu 10 ký tự.';
        } else {
            $forbiddenDesc = NoForbiddenWords::checkString($desc);
            if ($forbiddenDesc) {
                $errors['description'] = 'Mô tả chi tiết: ' . $forbiddenDesc;
            }
        }

        if (!empty($files['thumbnail']['name'])) {
            $ext = strtolower(pathinfo($files['thumbnail']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $errors['thumbnail'] = 'Ảnh minh họa chỉ chấp nhận định dạng JPG, JPEG hoặc PNG.';
            } elseif (($files['thumbnail']['size'] ?? 0) > 2 * 1024 * 1024) {
                $errors['thumbnail'] = 'Dung lượng ảnh không được vượt quá 2MB.';
            }
        }

        return $errors;
    }

    private function makeSlug(string $str): string
    {
        $str = mb_strtolower($str, 'UTF-8');
        $unicode = [
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
        ];
        foreach ($unicode as $nonUnicode => $uni) {
            $str = preg_replace("/({$uni})/i", $nonUnicode, $str);
        }
        $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
        $str = preg_replace('/[\s-]+/', '-', trim($str));
        return $str ?: 'man-choi';
    }
}
