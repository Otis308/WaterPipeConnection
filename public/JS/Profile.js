(() => {
    const form = document.getElementById('profile-form');
    if (!form) return;
    const input = document.getElementById('profile-avatar');
    const preview = document.getElementById('profile-avatar-preview');
    const status = document.getElementById('avatar-status');
    const original = preview.src;
    let previewUrl = null;
    const resetPreview = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        preview.src = original;
    };
    input.addEventListener('change', () => {
        resetPreview();
        status.textContent = '';
        input.setCustomValidity('');
        const file = input.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024 || file.size === 0) {
            status.textContent = 'Vui lòng chọn JPEG, PNG hoặc WebP có nội dung, tối đa 2 MB.';
            input.value = '';
            return;
        }
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        status.textContent = 'Ảnh xem trước. Bấm Lưu hồ sơ để lưu ảnh.';
    });
    preview.addEventListener('error', () => {
        if (!previewUrl) return;
        resetPreview();
        input.value = '';
        status.textContent = 'Không đọc được ảnh. Vui lòng chọn ảnh khác.';
    });
    form.addEventListener('submit', () => {
        document.getElementById('profile-save').disabled = true;
        document.getElementById('profile-save-status').textContent = input.files.length ? 'Đang tải ảnh và lưu hồ sơ…' : 'Đang lưu hồ sơ…';
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        document.getElementById('profile-save').disabled = false;
        document.getElementById('profile-save-status').textContent = '';
        form.removeAttribute('aria-busy');
    });
})();
