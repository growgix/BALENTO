/**
 * BALENTO Admin Main Application Bootstrap
 */
const App = (() => {
    let currentTab = 'dashboard';
    let confirmCallback = null;

    function init() {
        // Init Auth first
        AdminAuth.init();

        // Setup Sidebar Navigation
        document.querySelectorAll('.sidebar-menu .nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const tab = item.dataset.tab;
                if (tab) switchTab(tab);
            });
        });

        // Setup Mobile Sidebar Toggle
        const toggleBtn = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.admin-sidebar');
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });
        }

        // Setup Modal Close Handlers
        document.querySelectorAll('.modal-backdrop').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal(modal.id);
                }
            });
        });

        // Setup Confirmation Dialog buttons
        const confirmBtn = document.getElementById('dialog-confirm-btn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                if (typeof confirmCallback === 'function') {
                    confirmCallback();
                }
                closeModal('modal-confirm-dialog');
            });
        }

        // Setup Form Submissions
        document.getElementById('form-order-status')?.addEventListener('submit', AdminOrders.handleStatusSubmit);
        document.getElementById('form-product')?.addEventListener('submit', AdminProducts.handleProductSubmit);
        document.getElementById('form-inventory-adjust')?.addEventListener('submit', AdminInventory.handleAdjustSubmit);
        document.getElementById('form-category')?.addEventListener('submit', AdminCategories.handleCategorySubmit);
        document.getElementById('form-coupon')?.addEventListener('submit', AdminCoupons.handleCouponSubmit);
        document.getElementById('form-lookbook')?.addEventListener('submit', AdminLookbook.handleLookbookSubmit);
        document.getElementById('form-pincode')?.addEventListener('submit', AdminPincodes.handlePincodeSubmit);
        document.getElementById('form-admin-user')?.addEventListener('submit', AdminUsers.handleUserSubmit);
        document.getElementById('form-password-change')?.addEventListener('submit', AdminProfile.handlePasswordChange);
    }

    function switchTab(tabName) {
        currentTab = tabName;

        // Update nav items
        document.querySelectorAll('.sidebar-menu .nav-item').forEach(item => {
            if (item.dataset.tab === tabName) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Update Tab Views
        document.querySelectorAll('.tab-view').forEach(view => {
            if (view.id === `tab-${tabName}`) {
                view.classList.remove('hidden');
            } else {
                view.classList.add('hidden');
            }
        });

        // Update Header Title
        const headerTitle = document.getElementById('page-header-title');
        if (headerTitle) {
            const activeItem = document.querySelector(`.sidebar-menu .nav-item[data-tab="${tabName}"]`);
            headerTitle.textContent = activeItem ? activeItem.querySelector('.label')?.textContent : 'Backoffice';
        }

        // Close mobile sidebar if open
        const sidebar = document.querySelector('.admin-sidebar');
        if (sidebar) sidebar.classList.remove('open');

        // Load data for selected tab
        loadCurrentTab();
    }

    function loadCurrentTab() {
        switch (currentTab) {
            case 'dashboard':
                AdminDashboard.load();
                break;
            case 'orders':
                AdminOrders.load();
                break;
            case 'products':
                AdminProducts.load();
                break;
            case 'inventory':
                AdminInventory.load();
                break;
            case 'categories':
                AdminCategories.load();
                break;
            case 'coupons':
                AdminCoupons.load();
                break;
            case 'lookbook':
                AdminLookbook.load();
                break;
            case 'pincodes':
                AdminPincodes.load();
                break;
            case 'newsletter':
                AdminNewsletter.load();
                break;
            case 'admins':
                AdminUsers.load();
                break;
            case 'profile':
                AdminProfile.load();
                break;
            case 'audit':
                AdminAudit.load();
                break;
        }
    }

    function openModal(modalId) {
        const m = document.getElementById(modalId);
        if (m) m.classList.add('active');
    }

    function closeModal(modalId) {
        const m = document.getElementById(modalId);
        if (m) m.classList.remove('active');
    }

    function confirmDialog(message, onConfirm) {
        const msgEl = document.getElementById('dialog-confirm-message');
        if (msgEl) msgEl.textContent = message;
        confirmCallback = onConfirm;
        openModal('modal-confirm-dialog');
    }

    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span>${type === 'success' ? '✓' : type === 'error' ? '⚠' : 'ℹ'}</span>
            <span>${message}</span>
        `;

        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    return {
        init,
        switchTab,
        loadCurrentTab,
        openModal,
        closeModal,
        confirmDialog,
        showToast
    };
})();

// Bootstrap on DOM Ready
document.addEventListener('DOMContentLoaded', App.init);
