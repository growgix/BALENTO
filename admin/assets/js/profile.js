/**
 * BALENTO Admin Profile & Password Management Controller
 */
const AdminProfile = (() => {
    async function load() {
        try {
            const res = await AdminAPI.getMe();
            if (res.success && res.data) {
                const u = res.data;
                document.getElementById('profile-username-val').textContent = u.username || '-';
                document.getElementById('profile-email-val').textContent = u.email || '-';
                document.getElementById('profile-role-val').textContent = (u.role || 'staff').toUpperCase();
                document.getElementById('profile-last-login-val').textContent = u.last_login_at || 'Current session';
            }
        } catch (err) {
            App.showToast('Failed to load profile.', 'error');
        }
    }

    async function handlePasswordChange(e) {
        e.preventDefault();
        const curr = document.getElementById('pw-current').value;
        const newP = document.getElementById('pw-new').value;
        const conf = document.getElementById('pw-confirm').value;
        const btn = document.getElementById('pw-submit-btn');

        if (newP !== conf) {
            App.showToast('New passwords do not match.', 'error');
            return;
        }

        if (newP.length < 8) {
            App.showToast('New password must be at least 8 characters long.', 'error');
            return;
        }

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Updating Password...';
        }

        try {
            const res = await AdminAPI.changePassword({
                current_password: curr,
                new_password: newP,
                confirm_password: conf
            });

            if (res.success) {
                App.showToast('Password changed successfully!', 'success');
                document.getElementById('pw-current').value = '';
                document.getElementById('pw-new').value = '';
                document.getElementById('pw-confirm').value = '';
            }
        } catch (err) {
            App.showToast(err.message || 'Failed to update password.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Change Password';
            }
        }
    }

    return {
        load,
        handlePasswordChange
    };
})();
