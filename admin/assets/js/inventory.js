/**
 * BALENTO Admin Inventory & Stock Control Controller
 */
const AdminInventory = (() => {
    let currentPage = 1;
    let currentFilters = {};

    async function load(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('inventory-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--color-muted);">Loading inventory records...</td></tr>`;

        try {
            const params = {
                page: currentPage,
                limit: 25,
                ...currentFilters
            };

            const res = await AdminAPI.getInventory(params);
            if (res.success && res.data) {
                renderTable(res.data.inventory);
                renderPagination(res.data.pagination);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load inventory: ${escapeHtml(err.message)}</td></tr>`;
        }
    }

    function renderTable(items) {
        const tbody = document.getElementById('inventory-tbody');
        if (!tbody) return;

        if (!items || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--color-muted);">No inventory records found.</td></tr>`;
            return;
        }

        tbody.innerHTML = items.map(item => `
            <tr>
                <td class="font-medium">${escapeHtml(item.product_name)}</td>
                <td><code>${escapeHtml(item.sku)}</code></td>
                <td>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="width:14px; height:14px; border-radius:50%; background-color:${item.color_hex}; border:1px solid #d0cbc6; display:inline-block;"></span>
                        <span>${escapeHtml(item.color_name)}</span>
                    </div>
                </td>
                <td>₹${item.price.toLocaleString('en-IN')}</td>
                <td>
                    <strong style="font-size:15px;">${item.stock_quantity}</strong> units
                </td>
                <td>
                    <span class="badge ${getStockBadge(item.stock_status)}">${item.stock_status}</span>
                </td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="AdminInventory.openAdjustModal(${item.variant_id}, '${escapeHtml(item.sku)}', ${item.stock_quantity})">
                        Adjust Stock
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderPagination(p) {
        const wrap = document.getElementById('inventory-pagination');
        if (!wrap) return;

        wrap.innerHTML = `
            <div>Showing page ${p.current_page} of ${p.total_pages} (${p.total_items} variants total)</div>
            <div class="pagination-btns">
                <button class="btn btn-outline btn-sm" ${p.current_page <= 1 ? 'disabled' : ''} onclick="AdminInventory.load(${p.current_page - 1})">Previous</button>
                <button class="btn btn-outline btn-sm" ${p.current_page >= p.total_pages ? 'disabled' : ''} onclick="AdminInventory.load(${p.current_page + 1})">Next</button>
            </div>
        `;
    }

    function openAdjustModal(variantId, sku, currentStock) {
        document.getElementById('adjust-variant-id').value = variantId;
        document.getElementById('adjust-sku-label').textContent = sku;
        document.getElementById('adjust-current-stock').textContent = currentStock;
        document.getElementById('adjust-amount').value = '';
        document.getElementById('adjust-reason').value = '';
        document.getElementById('adjust-new-stock-preview').textContent = currentStock;

        App.openModal('modal-inventory-adjust');
    }

    function updatePreview() {
        const curr = parseInt(document.getElementById('adjust-current-stock').textContent || '0', 10);
        const adj = parseInt(document.getElementById('adjust-amount').value || '0', 10);
        const previewEl = document.getElementById('adjust-new-stock-preview');
        if (previewEl) {
            const next = curr + adj;
            previewEl.textContent = next;
            previewEl.style.color = next < 0 ? 'var(--color-danger)' : 'var(--color-primary)';
        }
    }

    async function handleAdjustSubmit(e) {
        e.preventDefault();
        const variantId = parseInt(document.getElementById('adjust-variant-id').value, 10);
        const adjustment = parseInt(document.getElementById('adjust-amount').value, 10);
        const reason = document.getElementById('adjust-reason').value.trim();
        const btn = document.getElementById('adjust-submit-btn');

        if (isNaN(adjustment) || adjustment === 0) {
            App.showToast('Please enter a non-zero adjustment amount (e.g. +10 or -5).', 'error');
            return;
        }

        if (!reason) {
            App.showToast('Please specify a reason for this manual stock change.', 'error');
            return;
        }

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Updating...';
        }

        try {
            const res = await AdminAPI.adjustInventory(variantId, adjustment, reason);
            if (res.success) {
                App.showToast(`Stock updated: now ${res.data.new_stock} units.`, 'success');
                App.closeModal('modal-inventory-adjust');
                load(currentPage);
            }
        } catch (err) {
            App.showToast(err.message || 'Failed to adjust inventory.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Apply Adjustment';
            }
        }
    }

    function applyFilters() {
        const search = document.getElementById('inventory-search-input')?.value.trim() || '';
        const status = document.getElementById('inventory-status-filter')?.value || '';

        currentFilters = {};
        if (search) currentFilters.search = search;
        if (status) currentFilters.stock_status = status;

        load(1);
    }

    function resetFilters() {
        if (document.getElementById('inventory-search-input')) document.getElementById('inventory-search-input').value = '';
        if (document.getElementById('inventory-status-filter')) document.getElementById('inventory-status-filter').value = '';
        currentFilters = {};
        load(1);
    }

    function getStockBadge(s) {
        return {
            'In Stock': 'badge-success',
            'Low Stock': 'badge-warning',
            'Out of Stock': 'badge-danger'
        }[s] || 'badge-neutral';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load,
        openAdjustModal,
        updatePreview,
        handleAdjustSubmit,
        applyFilters,
        resetFilters
    };
})();
