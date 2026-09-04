/**
 * BALENTO Admin Authentication Controller
 */
const AdminAuth = (() => {
    async function init() {
        const loginForm = document.getElementById('admin-login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', handleLogin);
        }

        const logoutBtn = document.getElementById('btn-logout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogout);
        }

        // Check active session
        if (AdminAPI.isAuthenticated()) {
            try {
                const res = await AdminAPI.getMe();
                if (res.success && res.data) {
                    showApp(res.data);
                } else {
                    showLogin();
                }
            } catch {
                showLogin();
            }
        } else {
            showLogin();
        }
    }

    async function handleLogin(e) {
        e.preventDefault();
        const usernameEl = document.getElementById('login-username');
        const passwordEl = document.getElementById('login-password');
        const rememberEl = document.getElementById('login-remember');
        const errorEl = document.getElementById('login-error-msg');
        const submitBtn = document.getElementById('login-submit-btn');

        if (!usernameEl || !passwordEl) return;

        const identifier = usernameEl.value.trim();
        const password = passwordEl.value;
        const remember = rememberEl ? rememberEl.checked : true;

        if (errorEl) errorEl.classList.add('hidden');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Authenticating...';
        }

        try {
            const res = await AdminAPI.login(identifier, password, remember);
            if (res.success && res.data) {
                AdminAPI.setSession(res.data.token, res.data.admin, remember);
                showApp(res.data.admin);
                App.showToast(`Welcome back, ${res.data.admin.username}`, 'success');
                App.loadCurrentTab();
            }
        } catch (err) {
            if (errorEl) {
                errorEl.textContent = err.message || 'Invalid username or password.';
                errorEl.classList.remove('hidden');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In to Backoffice';
            }
        }
    }

    function handleLogout() {
        App.confirmDialog('Are you sure you want to log out of the admin panel?', () => {
            AdminAPI.clearSession();
            showLogin();
            App.showToast('You have been logged out.');
        });
    }

    function showLogin() {
        const loginScreen = document.getElementById('login-screen');
        const adminRoot = document.getElementById('admin-root');
        if (loginScreen) loginScreen.classList.remove('hidden');
        if (adminRoot) adminRoot.classList.add('hidden');
    }

    function showApp(user) {
        const loginScreen = document.getElementById('login-screen');
        const adminRoot = document.getElementById('admin-root');
        if (loginScreen) loginScreen.classList.add('hidden');
        if (adminRoot) adminRoot.classList.remove('hidden');

        // Populate User UI
        const nameEl = document.getElementById('sidebar-user-name');
        const roleEl = document.getElementById('sidebar-user-role');
        const avatarEl = document.getElementById('sidebar-user-avatar');

        if (nameEl) nameEl.textContent = user.username || 'Admin';
        if (roleEl) roleEl.textContent = (user.role || 'staff').toUpperCase();
        if (avatarEl) avatarEl.textContent = (user.username || 'A').charAt(0).toUpperCase();

        // Role-based sidebar menu items filtering
        applyRolePermissions(user.role || 'staff');
    }

    function applyRolePermissions(role) {
        // Staff: only Orders & Inventory
        // Manager: Orders, Products, Inventory, Categories, Coupons, Lookbook, Pincodes, Newsletter
        // Admin: All
        document.querySelectorAll('[data-role-min]').forEach(el => {
            const minRole = el.dataset.roleMin;
            if (minRole === 'admin' && role !== 'admin') {
                el.style.display = 'none';
            } else if (minRole === 'manager' && role === 'staff') {
                el.style.display = 'none';
            } else {
                el.style.display = '';
            }
        });
    }

    return {
        init,
        showLogin,
        showApp,
        handleLogout
    };
})();
