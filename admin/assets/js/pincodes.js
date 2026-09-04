/**
 * BALENTO Admin Pincode & Delivery Serviceability Controller
 */
const AdminPincodes = (() => {
    let currentPage = 1;
    let currentSearch = '';

    async function load(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('pincodes-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--color-muted);">Loading PIN serviceability records...</td></tr>`;

        try {
            const params = {
                page: currentPage,
                limit: 25,
                search: currentSearch
            };

            const res = await AdminAPI.getPincodes(params);
            if (res.success && res.data) {
                renderTable(res.data.pincodes);
                renderPagination(res.data.pagination);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load PIN codes.</td></tr>`;
        }
    }

    function renderTable(pins) {
        const tbody = document.getElementById('pincodes-tbody');
        if (!tbody) return;

        if (!pins || pins.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:40px; color:var(--color-muted);">No PIN codes found matching search.</td></tr>`;
            return;
        }

        tbody.innerHTML = pins.map(p => `
            <tr>
                <td class="font-bold"><code>${escapeHtml(p.pincode)}</code></td>
                <td class="font-medium">${escapeHtml(p.city)}</td>
                <td>${escapeHtml(p.state)}</td>
                <td>${p.estimated_days} business days</td>
                <td><span class="badge ${p.cod_available ? 'badge-success' : 'badge-neutral'}">${p.cod_available ? 'COD Available' : 'Prepaid Only'}</span></td>
                <td><span class="badge ${p.is_serviceable ? 'badge-success' : 'badge-danger'}">${p.is_serviceable ? 'Serviceable' : 'Suspended'}</span></td>
                <td>
                    <button class="btn btn-outline btn-sm text-danger" onclick="AdminPincodes.toggleActive(${p.id}, ${p.is_serviceable ? 0 : 1})">
                        ${p.is_serviceable ? 'Suspend' : 'Activate'}
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function renderPagination(p) {
        const wrap = document.getElementById('pincodes-pagination');
        if (!wrap) return;

        wrap.innerHTML = `
            <div>Showing page ${p.current_page} of ${p.total_pages} (${p.total_items} PIN codes)</div>
            <div class="pagination-btns">
                <button class="btn btn-outline btn-sm" ${p.current_page <= 1 ? 'disabled' : ''} onclick="AdminPincodes.load(${p.current_page - 1})">Previous</button>
                <button class="btn btn-outline btn-sm" ${p.current_page >= p.total_pages ? 'disabled' : ''} onclick="AdminPincodes.load(${p.current_page + 1})">Next</button>
            </div>
        `;
    }

    function openCreateModal() {
        document.getElementById('pincode-modal-title').textContent = 'Add Indian Delivery PIN Code';
        document.getElementById('pincode-form-code').value = '';
        document.getElementById('pincode-form-city').value = '';
        document.getElementById('pincode-form-state').value = 'Karnataka';
        document.getElementById('pincode-form-days').value = '2';
        document.getElementById('pincode-form-cod').checked = true;
        document.getElementById('pincode-form-serviceable').checked = true;

        App.openModal('modal-pincode-form');
    }

    async function handlePincodeSubmit(e) {
        e.preventDefault();
        const pin = document.getElementById('pincode-form-code').value.trim();
        const city = document.getElementById('pincode-form-city').value.trim();
        const state = document.getElementById('pincode-form-state').value.trim();
        const days = parseInt(document.getElementById('pincode-form-days').value || '3', 10);
        const cod = document.getElementById('pincode-form-cod').checked ? 1 : 0;
        const serv = document.getElementById('pincode-form-serviceable').checked ? 1 : 0;
        const btn = document.getElementById('pincode-submit-btn');

        if (!/^[1-9][0-9]{5}$/.test(pin)) {
            App.showToast('Please enter a valid 6-digit Indian PIN code.', 'error');
            return;
        }

        const payload = {
            pincode: pin,
            city,
            state,
            estimated_days: days,
            cod_available: cod,
            is_serviceable: serv
        };

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving...';
        }

        try {
            await AdminAPI.createPincode(payload);
            App.showToast(`PIN code ${pin} (${city}) added successfully!`, 'success');
            App.closeModal('modal-pincode-form');
            load();
        } catch (err) {
            App.showToast(err.message || 'Error adding PIN code.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save PIN Code';
            }
        }
    }

    async function toggleActive(id, newStatus) {
        const action = newStatus ? 'activate' : 'suspend';
        App.confirmDialog(`Are you sure you want to ${action} serviceability for this PIN code?`, async () => {
            try {
                if (newStatus) {
                    await AdminAPI.updatePincode(id, { is_serviceable: 1 });
                    App.showToast('PIN serviceability activated.', 'success');
                } else {
                    await AdminAPI.deletePincode(id);
                    App.showToast('PIN serviceability suspended.', 'success');
                }
                load(currentPage);
            } catch (err) {
                App.showToast(err.message || 'Action failed.', 'error');
            }
        });
    }

    function search(val) {
        currentSearch = val.trim();
        load(1);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load,
        openCreateModal,
        handlePincodeSubmit,
        toggleActive,
        search
    };
})();
