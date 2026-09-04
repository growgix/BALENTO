/**
 * BALENTO Admin Coupon & Promotional Privilege Controller
 */
const AdminCoupons = (() => {
    async function load() {
        const tbody = document.getElementById('coupons-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--color-muted);">Loading coupons...</td></tr>`;

        try {
            const res = await AdminAPI.getCoupons();
            if (res.success && res.data) {
                renderTable(res.data);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load coupons.</td></tr>`;
        }
    }

    function renderTable(coupons) {
        const tbody = document.getElementById('coupons-tbody');
        if (!tbody) return;

        if (!coupons || coupons.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:40px; color:var(--color-muted);">No promo coupons configured.</td></tr>`;
            return;
        }

        tbody.innerHTML = coupons.map(c => `
            <tr>
                <td class="font-bold"><code>${escapeHtml(c.code)}</code></td>
                <td>
                    ${c.discount_type === 'percentage' 
                        ? `<span class="badge badge-accent">${c.discount_value}% OFF</span>` 
                        : `<span class="badge badge-primary">₹${c.discount_value} OFF</span>`}
                </td>
                <td>₹${c.min_order_amount.toLocaleString('en-IN')}</td>
                <td>${c.max_discount_cap ? '₹' + c.max_discount_cap.toLocaleString('en-IN') : 'No Cap'}</td>
                <td>
                    <strong>${c.usage_count}</strong> ${c.usage_limit ? '/ ' + c.usage_limit : 'used'}
                </td>
                <td class="text-xs text-muted">
                    ${c.expires_at ? c.expires_at.substring(0, 10) : 'Never'}
                </td>
                <td>
                    <span class="badge ${getStatusBadge(c.status)}">${c.status}</span>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-outline btn-sm text-danger" onclick="AdminCoupons.toggleActive(${c.id}, ${c.is_active ? 0 : 1})">
                            ${c.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function openCreateModal() {
        document.getElementById('coupon-modal-title').textContent = 'Create Promotional Coupon';
        document.getElementById('coupon-form-code').value = '';
        document.getElementById('coupon-form-type').value = 'percentage';
        document.getElementById('coupon-form-value').value = '';
        document.getElementById('coupon-form-min').value = '2000';
        document.getElementById('coupon-form-cap').value = '';
        document.getElementById('coupon-form-limit').value = '';
        document.getElementById('coupon-form-expires').value = '';
        document.getElementById('coupon-form-active').checked = true;

        App.openModal('modal-coupon-form');
    }

    async function handleCouponSubmit(e) {
        e.preventDefault();
        const code = document.getElementById('coupon-form-code').value.trim().toUpperCase();
        const type = document.getElementById('coupon-form-type').value;
        const val = parseFloat(document.getElementById('coupon-form-value').value);
        const minOrder = parseFloat(document.getElementById('coupon-form-min').value || '0');
        const cap = document.getElementById('coupon-form-cap').value ? parseFloat(document.getElementById('coupon-form-cap').value) : null;
        const limit = document.getElementById('coupon-form-limit').value ? parseInt(document.getElementById('coupon-form-limit').value, 10) : null;
        const expires = document.getElementById('coupon-form-expires').value || null;
        const isActive = document.getElementById('coupon-form-active').checked ? 1 : 0;
        const btn = document.getElementById('coupon-submit-btn');

        if (!code || isNaN(val) || val <= 0) {
            App.showToast('Please provide a valid coupon code and positive discount value.', 'error');
            return;
        }

        if (type === 'percentage' && val > 100) {
            App.showToast('Percentage discount cannot exceed 100%.', 'error');
            return;
        }

        const payload = {
            code,
            discount_type: type,
            discount_value: val,
            min_order_amount: minOrder,
            max_discount_cap: cap,
            usage_limit: limit,
            expires_at: expires,
            is_active: isActive
        };

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Creating...';
        }

        try {
            await AdminAPI.createCoupon(payload);
            App.showToast(`Coupon ${code} created successfully!`, 'success');
            App.closeModal('modal-coupon-form');
            load();
        } catch (err) {
            App.showToast(err.message || 'Error creating coupon.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Create Coupon';
            }
        }
    }

    async function toggleActive(id, newStatus) {
        const action = newStatus ? 'activate' : 'deactivate';
        App.confirmDialog(`Are you sure you want to ${action} this coupon?`, async () => {
            try {
                if (newStatus) {
                    await AdminAPI.updateCoupon(id, { is_active: 1 });
                    App.showToast('Coupon activated.', 'success');
                } else {
                    await AdminAPI.deleteCoupon(id);
                    App.showToast('Coupon deactivated.', 'success');
                }
                load();
            } catch (err) {
                App.showToast(err.message || 'Action failed.', 'error');
            }
        });
    }

    function getStatusBadge(s) {
        return {
            'Active': 'badge-success',
            'Inactive': 'badge-neutral',
            'Expired': 'badge-danger',
            'Limit Reached': 'badge-warning',
            'Scheduled': 'badge-info'
        }[s] || 'badge-neutral';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load,
        openCreateModal,
        handleCouponSubmit,
        toggleActive
    };
})();
