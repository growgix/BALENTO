/**
 * BALENTO Admin Lookbook Editorial Content Controller
 */
const AdminLookbook = (() => {
    let productsList = [];

    async function load() {
        const tbody = document.getElementById('lookbook-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--color-muted);">Loading lookbook entries...</td></tr>`;

        try {
            if (productsList.length === 0) {
                const pRes = await AdminAPI.getProducts({ limit: 100 });
                if (pRes.success && pRes.data) productsList = pRes.data.products || [];
                populateProductDropdown();
            }

            const res = await AdminAPI.getLookbook();
            if (res.success && res.data) {
                renderTable(res.data);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load lookbook entries.</td></tr>`;
        }
    }

    function populateProductDropdown() {
        const select = document.getElementById('lookbook-form-product');
        if (!select) return;
        select.innerHTML = productsList.map(p => `
            <option value="${p.id}">${escapeHtml(p.name)} (₹${p.price.toLocaleString('en-IN')})</option>
        `).join('');
    }

    function renderTable(items) {
        const tbody = document.getElementById('lookbook-tbody');
        if (!tbody) return;

        if (!items || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--color-muted);">No lookbook cards found.</td></tr>`;
            return;
        }

        tbody.innerHTML = items.map(item => `
            <tr>
                <td>
                    <div style="width:40px; height:50px; background:#f0ece8; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--color-border-light);">
                        <img src="${item.image_url}" alt="" style="width:100%; height:100%; object-fit:cover;" />
                    </div>
                </td>
                <td>
                    <div class="font-medium">${escapeHtml(item.person_name)}</div>
                    <div class="text-xs text-muted">${escapeHtml(item.person_title || '')}</div>
                </td>
                <td>
                    <span class="badge badge-accent">${escapeHtml(item.city_title)}</span>
                    <div class="text-xs text-muted">Key: <code>${escapeHtml(item.city_key)}</code></div>
                </td>
                <td class="font-medium">${escapeHtml(item.product_name)}</td>
                <td style="max-width:240px;" class="truncate text-xs text-muted" title="${escapeHtml(item.quote)}">
                    "${escapeHtml(item.quote)}"
                </td>
                <td>
                    <span class="badge ${item.is_active ? 'badge-success' : 'badge-neutral'}">${item.is_active ? 'Active' : 'Inactive'}</span>
                </td>
                <td>
                    <button class="btn btn-outline btn-sm text-danger" onclick="AdminLookbook.toggleActive(${item.id}, ${item.is_active ? 0 : 1})">
                        ${item.is_active ? 'Deactivate' : 'Activate'}
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function openCreateModal() {
        document.getElementById('lookbook-modal-title').textContent = 'Add Lookbook Street Style Card';
        document.getElementById('lookbook-form-id').value = '';
        document.getElementById('lookbook-form-city-key').value = '';
        document.getElementById('lookbook-form-city-title').value = '';
        document.getElementById('lookbook-form-person-name').value = '';
        document.getElementById('lookbook-form-person-title').value = '';
        document.getElementById('lookbook-form-image').value = '';
        document.getElementById('lookbook-form-quote').value = '';
        document.getElementById('lookbook-form-active').checked = true;

        App.openModal('modal-lookbook-form');
    }

    async function handleLookbookSubmit(e) {
        e.preventDefault();
        const cityKey = document.getElementById('lookbook-form-city-key').value.trim().toLowerCase();
        const cityTitle = document.getElementById('lookbook-form-city-title').value.trim();
        const personName = document.getElementById('lookbook-form-person-name').value.trim();
        const personTitle = document.getElementById('lookbook-form-person-title').value.trim();
        const productId = parseInt(document.getElementById('lookbook-form-product').value, 10);
        const imageUrl = document.getElementById('lookbook-form-image').value.trim();
        const quote = document.getElementById('lookbook-form-quote').value.trim();
        const isActive = document.getElementById('lookbook-form-active').checked ? 1 : 0;
        const btn = document.getElementById('lookbook-submit-btn');

        const payload = {
            city_key: cityKey,
            city_title: cityTitle,
            person_name: personName,
            person_title: personTitle,
            product_id: productId,
            image_url: imageUrl,
            quote,
            is_active: isActive
        };

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving...';
        }

        try {
            await AdminAPI.createLookbook(payload);
            App.showToast('Lookbook editorial card created!', 'success');
            App.closeModal('modal-lookbook-form');
            load();
        } catch (err) {
            App.showToast(err.message || 'Error saving lookbook entry.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save Lookbook Entry';
            }
        }
    }

    async function toggleActive(id, newStatus) {
        const action = newStatus ? 'activate' : 'deactivate';
        App.confirmDialog(`Are you sure you want to ${action} this lookbook entry?`, async () => {
            try {
                if (newStatus) {
                    await AdminAPI.updateLookbook(id, { is_active: 1 });
                    App.showToast('Lookbook card activated.', 'success');
                } else {
                    await AdminAPI.deleteLookbook(id);
                    App.showToast('Lookbook card deactivated.', 'success');
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
        handleLookbookSubmit,
        toggleActive
    };
})();
