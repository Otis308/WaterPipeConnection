/**
 * Admin Dashboard JavaScript
 * - Table sorting
 * - Table filtering
 * - Table search
 * - Table pagination
 * - Confirmation dialogs
 * - Toast notifications
 * - Mobile sidebar toggle
 */

(function() {
    'use strict';

    // ==========================================
    // Toast Notifications
    // ==========================================
    function showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `admin-toast admin-toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-icon">${getToastIcon(type)}</span>
                <span class="toast-message">${message}</span>
            </div>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('toast-dismissing');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    function getToastIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    // ==========================================
    // Confirmation Dialogs
    // ==========================================
    function showConfirmDialog(options) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'admin-modal-overlay';
            overlay.innerHTML = `
                <div class="admin-modal">
                    <div class="modal-header">
                        <h3 class="modal-title">${options.title || 'Xác nhận'}</h3>
                        <button class="close-btn" onclick="this.closest('.admin-modal-overlay').remove()">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="admin-confirm-dialog">
                            <span class="confirm-icon">${options.icon || '⚠'}</span>
                            <div class="confirm-title">${options.title || 'Bạn có chắc chắn?'}</div>
                            <div class="confirm-message">${options.message}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="admin-btn admin-btn-secondary" data-action="cancel">Hủy bỏ</button>
                        <button class="admin-btn admin-btn-${options.danger ? 'danger' : 'primary'}" data-action="confirm">${options.confirmText || 'Xác nhận'}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            overlay.querySelector('[data-action="cancel"]').addEventListener('click', () => {
                overlay.remove();
                resolve(false);
            });

            overlay.querySelector('[data-action="confirm"]').addEventListener('click', () => {
                overlay.remove();
                resolve(true);
            });

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.remove();
                    resolve(false);
                }
            });
        });
    }

    // ==========================================
    // Table Sorting
    // ==========================================
    function initTableSorting() {
        const tables = document.querySelectorAll('.admin-table[data-sortable="true"]');
        tables.forEach(table => {
            const headers = table.querySelectorAll('th.sortable');
            headers.forEach((header, index) => {
                header.addEventListener('click', () => {
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const isAsc = header.classList.contains('sort-asc');

                    // Remove sort classes from all headers
                    headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));

                    // Add sort class to current header
                    header.classList.add(isAsc ? 'sort-desc' : 'sort-asc');

                    // Sort rows
                    rows.sort((a, b) => {
                        const aText = a.cells[index].textContent.trim();
                        const bText = b.cells[index].textContent.trim();

                        // Try numeric sort
                        const aNum = parseFloat(aText);
                        const bNum = parseFloat(bText);

                        if (!isNaN(aNum) && !isNaN(bNum)) {
                            return isAsc ? bNum - aNum : aNum - bNum;
                        }

                        // String sort
                        return isAsc
                            ? bText.localeCompare(aText)
                            : aText.localeCompare(bText);
                    });

                    // Reorder rows
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        });
    }

    // ==========================================
    // Table Filtering
    // ==========================================
    function initTableFiltering() {
        const filters = document.querySelectorAll('.admin-table-filter');
        filters.forEach(filter => {
            filter.addEventListener('change', (e) => {
                const tableId = e.target.dataset.table;
                const column = e.target.dataset.column;
                const value = e.target.value.toLowerCase();

                const table = document.getElementById(tableId);
                if (!table) return;

                const tbody = table.querySelector('tbody');
                const rows = tbody.querySelectorAll('tr');

                rows.forEach(row => {
                    const cell = row.querySelector(`td[data-column="${column}"]`);
                    if (!cell) return;

                    const cellText = cell.textContent.toLowerCase();
                    row.style.display = value === '' || cellText.includes(value)
                        ? ''
                        : 'none';
                });
            });
        });
    }

    // ==========================================
    // Table Search
    // ==========================================
    function initTableSearch() {
        const searchInputs = document.querySelectorAll('.admin-table-search');
        searchInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const tableId = e.target.dataset.table;
                const query = e.target.value.toLowerCase();

                const table = document.getElementById(tableId);
                if (!table) return;

                const tbody = table.querySelector('tbody');
                const rows = tbody.querySelectorAll('tr');

                rows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    row.style.display = query === '' || rowText.includes(query)
                        ? ''
                        : 'none';
                });
            });
        });
    }

    // ==========================================
    // Table Pagination
    // ==========================================
    function initTablePagination() {
        const paginations = document.querySelectorAll('.admin-table-pagination');
        paginations.forEach(pagination => {
            const tableId = pagination.dataset.table;
            const table = document.getElementById(tableId);
            if (!table) return;

            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const itemsPerPage = parseInt(pagination.dataset.itemsPerPage) || 10;
            let currentPage = 1;

            function showPage(page) {
                const start = (page - 1) * itemsPerPage;
                const end = start + itemsPerPage;

                rows.forEach((row, index) => {
                    row.style.display = index >= start && index < end ? '' : 'none';
                });

                updatePaginationUI();
            }

            function updatePaginationUI() {
                const totalPages = Math.ceil(rows.length / itemsPerPage);
                const pageNumbers = pagination.querySelector('.page-numbers');
                const pageInfo = pagination.querySelector('.page-info');

                if (pageInfo) {
                    pageInfo.textContent = `Hiển thị ${Math.min((currentPage - 1) * itemsPerPage + 1, rows.length)} - ${Math.min(currentPage * itemsPerPage, rows.length)} của ${rows.length}`;
                }

                if (pageNumbers) {
                    pageNumbers.innerHTML = '';
                    for (let i = 1; i <= totalPages; i++) {
                        const btn = document.createElement('button');
                        btn.className = `page-link ${i === currentPage ? 'active' : ''}`;
                        btn.textContent = i;
                        btn.addEventListener('click', () => {
                            currentPage = i;
                            showPage(currentPage);
                        });
                        pageNumbers.appendChild(btn);
                    }
                }
            }

            // Initialize
            showPage(1);
        });
    }

    // ==========================================
    // Mobile Sidebar Toggle
    // ==========================================
    function initMobileSidebar() {
        const toggle = document.querySelector('.admin-sidebar-toggle');
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        if (!toggle || !sidebar) return;

        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });

        // Create overlay if not exists
        if (!overlay) {
            const newOverlay = document.createElement('div');
            newOverlay.className = 'sidebar-overlay';
            newOverlay.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                display: none;
            `;
            document.body.appendChild(newOverlay);
            newOverlay.addEventListener('click', () => {
                sidebar.classList.remove('show');
                newOverlay.style.display = 'none';
            });
        }

        // Show overlay when sidebar is open
        const observer = new MutationObserver(() => {
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
            }
        });
        observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    }

    // ==========================================
    // Kebab Menu
    // ==========================================
    function initKebabMenus() {
        document.addEventListener('click', (e) => {
            const kebabBtn = e.target.closest('.admin-kebab-btn');
            const menu = e.target.closest('.admin-action-menu');

            // Close all other menus
            document.querySelectorAll('.menu-dropdown.show').forEach(dropdown => {
                if (!menu || !menu.contains(dropdown)) {
                    dropdown.classList.remove('show');
                    dropdown.style.zIndex = '';
                }
            });

            // Toggle current menu
            if (kebabBtn) {
                const dropdown = kebabBtn.nextElementSibling;
                if (dropdown && dropdown.classList.contains('menu-dropdown')) {
                    const isShowing = dropdown.classList.contains('show');
                    dropdown.classList.toggle('show');
                    
                    // Set high z-index when showing, reset when hiding
                    if (!isShowing) {
                        dropdown.style.zIndex = '9999';
                    } else {
                        dropdown.style.zIndex = '';
                    }
                }
            }
        });
    }

    // ==========================================
    // Initialize All
    // ==========================================
    document.addEventListener('DOMContentLoaded', () => {
        initTableSorting();
        initTableFiltering();
        initTableSearch();
        initTablePagination();
        initMobileSidebar();
        initKebabMenus();
        initProgressBars();

        // Initialize Bootstrap tabs if available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            const triggerTabList = [].slice.call(document.querySelectorAll('#adminTab button[data-bs-toggle="tab"]'));
            triggerTabList.forEach(function (triggerEl) {
                var tabTrigger = new bootstrap.Tab(triggerEl);
                triggerEl.addEventListener('click', function (event) {
                    event.preventDefault();
                    tabTrigger.show();
                });
            });
        }

        // Make functions globally available
        window.showToast = showToast;
        window.showConfirmDialog = showConfirmDialog;
    });

    // ==========================================
    // Initialize Progress Bars
    // ==========================================
    function initProgressBars() {
        const progressBars = document.querySelectorAll('.progress-bar[data-width]');
        progressBars.forEach(bar => {
            const width = bar.getAttribute('data-width');
            if (width) {
                bar.style.width = width + '%';
            }
        });
    }
