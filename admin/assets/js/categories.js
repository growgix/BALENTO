/**
 * BALENTO Admin Category Management Controller
 */
const AdminCategories = (() => {
    async function load() {
        const tbody = document.getElementById('categories-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--color-muted);">Loading categories...</td></tr>`;

        try {
            const res = await AdminAPI.getCategories();
            if (res.success && res.data) {
                renderTable(res.data);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load categories.</td></tr>`;
        }
    }

    function renderTable(categories) {
        const tbody = document.getElementById('categories-tbody');
        if (!tbody) return;

        if (!categories || categories.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--color-muted);">No categories found.</td></tr>`;
            return;
        }

        tbody.innerHTML = categories.map(c => `
            <tr>
                <td class="font-medium">${escapeHtml(c.name)}</td>
                <td><code>${escapeHtml(c.slug)}</code></td>
                <td>${c.active_products_count} active bags</td>
                <td>${escapeHtml(c.description || '-')}</td>
                <td>
                    <span class="badge ${c.is_active ? 'badge-success' : 'badge-neutral'}">${c.is_active ? 'Active' : 'Inactive'}</span>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick="AdminCategories.openEditModal(${c.id}, '${escapeHtml(c.name)}', '${escapeHtml(c.slug)}', '${escapeHtml(c.description || '')}', ${c.is_active})">Edit</button>
                        <button class="btn btn-outline btn-sm text-danger" onclick="AdminCategories.toggleActive(${c.id}, ${c.is_active ? 0 : 1})">
                            ${c.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function openCreateModal() {
        document.getElementById('category-modal-title').textContent = 'Add Product Category';
        document.getElementById('category-form-id').value = '';
        document.getElementById('category-form-name').value = '';
        document.getElementById('category-form-slug').value = '';
        document.getElementById('category-form-desc').value = '';
        document.getElementById('category-form-active').checked = true;
        App.openModal('modal-category-form');
    }

    function openEditModal(id, name, slug, desc, isActive) {
        document.getElementById('category-modal-title').textContent = `Edit Category: ${name}`;
        document.getElementById('category-form-id').value = id;
        document.getElementById('category-form-name').value = name;
        document.getElementById('category-form-slug').value = slug;
        document.getElementById('category-form-desc').value = desc;
        document.getElementById('category-form-active').checked = !!isActive;
        App.openModal('modal-category-form');
    }

    async function handleCategorySubmit(e) {
        e.preventDefault();
        const id = document.getElementById('category-form-id').value;
        const name = document.getElementById('category-form-name').value.trim();
        const slug = document.getElementById('category-form-slug').value.trim();
        const desc = document.getElementById('category-form-desc').value.trim();
        const isActive = document.getElementById('category-form-active').checked ? 1 : 0;
        const btn = document.getElementById('category-submit-btn');

        const payload = { name, slug, description: desc, is_active: isActive };

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving...';
        }

        try {
            if (id) {
                await AdminAPI.updateCategory(id, payload);
                App.showToast('Category updated successfully.', 'success');
            } else {
                await AdminAPI.createCategory(payload);
                App.showToast('New category created.', 'success');
            }
            App.closeModal('modal-category-form');
            load();
        } catch (err) {
            App.showToast(err.message || 'Error saving category.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save Category';
            }
        }
    }

    async function toggleActive(id, newStatus) {
        const action = newStatus ? 'activate' : 'deactivate';
        App.confirmDialog(`Are you sure you want to ${action} this category?`, async () => {
            try {
                if (newStatus) {
                    await AdminAPI.updateCategory(id, { is_active: 1 });
                    App.showToast('Category activated.', 'success');
                } else {
                    await AdminAPI.deleteCategory(id);
                    App.showToast('Category deactivated.', 'success');
                }
                load();
            } catch (err) {
                App.showToast(err.message || 'Action failed.', 'error');
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load,
        openCreateModal,
        openEditModal,
        handleCategorySubmit,
        toggleActive
    };
})();
