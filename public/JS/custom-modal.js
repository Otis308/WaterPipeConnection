/**
 * Custom Modal System
 * Thay thế native alert(), confirm(), prompt() bằng custom styled modal
 */

class CustomModal {
    constructor() {
        this.overlay = null;
        this.modal = null;
        this.promiseResolve = null;
        this.promiseReject = null;
        this.init();
    }

    init() {
        // Create overlay
        this.overlay = document.createElement('div');
        this.overlay.className = 'custom-modal-overlay';
        this.overlay.style.cssText = `
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
            backdrop-filter: blur(4px);
        `;

        // Create modal
        this.modal = document.createElement('div');
        this.modal.className = 'custom-modal';
        this.modal.style.cssText = `
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            animation: modalSlideIn 0.3s ease;
        `;

        // Add keyframe animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px) scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            @keyframes modalSlideOut {
                from {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
                to {
                    opacity: 0;
                    transform: translateY(-20px) scale(0.95);
                }
            }
        `;
        document.head.appendChild(style);

        this.overlay.appendChild(this.modal);
        document.body.appendChild(this.overlay);

        // Close on overlay click
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close(false);
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay.style.display === 'flex') {
                this.close(false);
            }
        });
    }

    show(type, options = {}) {
        const {
            title = 'Thông báo',
            message = '',
            icon = 'ℹ️',
            confirmText = 'OK',
            cancelText = 'Hủy',
            showCancel = false,
            inputPlaceholder = '',
            inputValue = ''
        } = options;

        let content = '';

        if (type === 'prompt') {
            content = `
                <input type="text" 
                       class="custom-modal-input" 
                       placeholder="${inputPlaceholder}" 
                       value="${inputValue}"
                       style="width: 100%; padding: 12px 16px; border: 1px solid #475569; border-radius: 8px; background: #0f172a; color: #f1f5f9; font-size: 14px; margin-bottom: 16px; outline: none;">
            `;
        }

        this.modal.innerHTML = `
            <div style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                    <span style="font-size: 32px;">${icon}</span>
                    <h3 style="margin: 0; font-size: 20px; font-weight: 600; color: #f1f5f9;">${title}</h3>
                </div>
                <div style="color: #cbd5e1; font-size: 15px; line-height: 1.6; margin-bottom: 24px;">
                    ${message}
                </div>
                ${content}
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    ${showCancel ? `
                        <button class="custom-modal-btn custom-modal-btn-cancel" style="padding: 10px 20px; border: 1px solid #475569; border-radius: 8px; background: transparent; color: #cbd5e1; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            ${cancelText}
                        </button>
                    ` : ''}
                    <button class="custom-modal-btn custom-modal-btn-confirm" style="padding: 10px 20px; border: none; border-radius: 8px; background: #6366f1; color: #ffffff; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                        ${confirmText}
                    </button>
                </div>
            </div>
        `;

        // Add hover effects
        this.modal.querySelectorAll('.custom-modal-btn').forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                if (btn.classList.contains('custom-modal-btn-confirm')) {
                    btn.style.background = '#4f46e5';
                } else {
                    btn.style.background = '#334155';
                }
            });
            btn.addEventListener('mouseleave', () => {
                if (btn.classList.contains('custom-modal-btn-confirm')) {
                    btn.style.background = '#6366f1';
                } else {
                    btn.style.background = 'transparent';
                }
            });
        });

        this.overlay.style.display = 'flex';

        // Create promise
        return new Promise((resolve, reject) => {
            this.promiseResolve = resolve;
            this.promiseReject = reject;

            // Button click handlers
            const confirmBtn = this.modal.querySelector('.custom-modal-btn-confirm');
            const cancelBtn = this.modal.querySelector('.custom-modal-btn-cancel');

            if (confirmBtn) {
                confirmBtn.addEventListener('click', () => {
                    if (type === 'prompt') {
                        const input = this.modal.querySelector('.custom-modal-input');
                        this.close(input ? input.value : null);
                    } else {
                        this.close(true);
                    }
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    this.close(false);
                });
            }

            // Focus input for prompt
            if (type === 'prompt') {
                const input = this.modal.querySelector('.custom-modal-input');
                if (input) {
                    setTimeout(() => input.focus(), 100);
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            this.close(input.value);
                        }
                    });
                }
            }
        });
    }

    close(result) {
        this.modal.style.animation = 'modalSlideOut 0.2s ease forwards';
        setTimeout(() => {
            this.overlay.style.display = 'none';
            this.modal.style.animation = 'modalSlideIn 0.3s ease';
            if (this.promiseResolve) {
                this.promiseResolve(result);
                this.promiseResolve = null;
            }
        }, 200);
    }

    alert(message, title = 'Thông báo') {
        return this.show('alert', { title, message, icon: 'ℹ️', showCancel: false });
    }

    confirm(message, title = 'Xác nhận') {
        return this.show('confirm', { title, message, icon: '⚠️', showCancel: true });
    }

    prompt(message, title = 'Nhập thông tin', placeholder = '', defaultValue = '') {
        return this.show('prompt', {
            title,
            message,
            icon: '✏️',
            showCancel: true,
            inputPlaceholder: placeholder,
            inputValue: defaultValue
        });
    }
}

// Initialize custom modal
const customModal = new CustomModal();

// Override native functions
window.customAlert = function(message, title) {
    return customModal.alert(message, title);
};

window.customConfirm = function(message, title) {
    return customModal.confirm(message, title);
};

window.customPrompt = function(message, title, placeholder, defaultValue) {
    return customModal.prompt(message, title, placeholder, defaultValue);
};

// Optional: Override native functions (uncomment if you want to completely replace native dialogs)
// window.alert = window.customAlert;
// window.confirm = window.customConfirm;
// window.prompt = window.customPrompt;
