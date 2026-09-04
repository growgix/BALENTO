/**
 * BALENTO Admin Users & RBAC Controller
 */
const AdminUsers = (() => {
    async function load() {
        const tbody = document.getElementById('admins-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--color-muted);">Loading admin accounts...</td></tr>`;

        try {
            const res = await AdminAPI.getAdminUsers();
            if (res.success && res.data) {
                renderTable(res.data);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load admin users.</td></tr>`;
        }
    }

    function renderTable(users) {
        const tbody = document.getElementById('admins-tbody');
        if (!tbody) return;

        if (!users || users.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--color-muted);">No users found.</td></tr>`;
            return;
        }

        tbody.innerHTML = users.map(u => `
            <tr>
                <td class="font-medium">${escapeHtml(u.username)}</td>
                <td>${escapeHtml(u.email)}</td>
                <td>
                    <span class="badge ${getRoleBadge(u.role)}">${u.role.toUpperCase()}</span>
                </td>
                <td class="text-xs text-muted">${u.last_login_at ? u.last_login_at.substring(0, 16) : 'Never'}</td>
                <td>
                    <span class="badge ${u.is_active ? 'badge-success' : 'badge-neutral'}">${u.is_active ? 'Active' : 'Deactivated'}</span>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick="AdminUsers.openEditModal(${u.id}, '${escapeHtml(u.username)}', '${escapeHtml(u.email)}', '${u.role}', ${u.is_active})">Edit</button>
                        <button class="btn btn-outline btn-sm text-danger" onclick="AdminUsers.toggleActive(${u.id}, ${u.is_active ? 0 : 1})">
                            ${u.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function openCreateModal() {
        document.getElementById('admin-user-modal-title').textContent = 'Create Admin / Staff Account';
        document.getElementById('admin-user-form-id').value = '';
        document.getElementById('admin-user-form-username').value = '';
        document.getElementById('admin-user-form-email').value = '';
        document.getElementById('admin-user-form-password').value = '';
        document.getElementById('admin-user-form-password').required = true;
        document.getElementById('admin-user-form-role').value = 'staff';
        document.getElementById('admin-user-form-active').checked = true;

        App.openModal('modal-admin-user-form');
    }

    function openEditModal(id, username, email, role, isActive) {
        document.getElementById('admin-user-modal-title').textContent = `Edit Account: ${username}`;
        document.getElementById('admin-user-form-id').value = id;
        document.getElementById('admin-user-form-username').value = username;
        document.getElementById('admin-user-form-email').value = email;
        document.getElementById('admin-user-form-password').value = '';
        document.getElementById('admin-user-form-password').required = false;
        document.getElementById('admin-user-form-role').value = role;
        document.getElementById('admin-user-form-active').checked = !!isActive;

        App.openModal('modal-admin-user-form');
    }

    async function handleUserSubmit(e) {
        e.preventDefault();
        const id = document.getElementById('admin-user-form-id').value;
        const username = document.getElementById('admin-user-form-username').value.trim();
        const email = document.getElementById('admin-user-form-email').value.trim();
        const password = document.getElementById('admin-user-form-password').value;
        const role = document.getElementById('admin-user-form-role').value;
        const isActive = document.getElementById('admin-user-form-active').checked ? 1 : 0;
        const btn = document.getElementById('admin-user-submit-btn');

        const payload = { username, email, role, is_active: isActive };
        if (password) payload.password = password;

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving...';
        }

        try {
            if (id) {
                await AdminAPI.updateAdminUser(id, payload);
                App.showToast('User account updated successfully.', 'success');
            } else {
                await AdminAPI.createAdminUser(payload);
                App.showToast('New user account created.', 'success');
            }
            App.closeModal('modal-admin-user-form');
            load();
        } catch (err) {
            App.showToast(err.message || 'Error saving user account.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save Account';
            }
        }
    }

    async function toggleActive(id, newStatus) {
        const action = newStatus ? 'activate' : 'deactivate';
        App.confirmDialog(`Are you sure you want to ${action} this user?`, async () => {
            try {
                if (newStatus) {
                    await AdminAPI.updateAdminUser(id, { is_active: 1 });
                    App.showToast('Account activated.', 'success');
                } else {
                    await AdminAPI.deleteAdminUser(id);
                    App.showToast('Account deactivated.', 'success');
                }
                load();
            } catch (err) {
                App.showToast(err.message || 'Action failed.', 'error');
            }
        });
    }

    function getRoleBadge(r) {
        return {
            'admin': 'badge-primary',
            'manager': 'badge-accent',
            'staff': 'badge-neutral'
        }[r] || 'badge-neutral';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load,
        openCreateModal,
        openEditModal,
        handleUserSubmit,
        toggleActive
    };
})();
