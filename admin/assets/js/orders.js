/**
 * BALENTO Admin Orders Management Controller
 */
const AdminOrders = (() => {
    let currentPage = 1;
    let currentFilters = {};

    async function load(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('orders-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--color-muted);">Loading orders...</td></tr>`;

        try {
            const params = {
                page: currentPage,
                limit: 15,
                ...currentFilters
            };

            const res = await AdminAPI.getOrders(params);
            if (res.success && res.data) {
                renderTable(res.data.orders);
                renderPagination(res.data.pagination);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load orders: ${escapeHtml(err.message)}</td></tr>`;
        }
    }

    function renderTable(orders) {
        const tbody = document.getElementById('orders-tbody');
        if (!tbody) return;

        if (!orders || orders.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:40px; color:var(--color-muted);">No orders found matching criteria.</td></tr>`;
            return;
        }

        tbody.innerHTML = orders.map(o => `
            <tr>
                <td class="font-semibold">
                    <a href="javascript:void(0)" onclick="AdminOrders.viewOrder(${o.id})" style="color:var(--color-primary); text-decoration:underline;">
                        ${o.order_number}
                    </a>
                </td>
                <td>
                    <div class="font-medium">${escapeHtml(o.customer_name)}</div>
                    <div class="text-xs text-muted">${escapeHtml(o.customer_phone)} • ${escapeHtml(o.city)}</div>
                </td>
                <td class="text-xs text-muted">${o.created_at ? o.created_at.substring(0, 16) : '-'}</td>
                <td class="font-semibold">₹${o.total_amount.toLocaleString('en-IN')}</td>
                <td>
                    <span class="badge ${getPaymentBadge(o.payment_status)}">${o.payment_status}</span>
                    <div class="text-xs text-muted uppercase mt-0.5">${o.payment_method}</div>
                </td>
                <td>
                    <span class="badge ${getOrderStatusBadge(o.order_status)}">${o.order_status}</span>
                    ${o.is_gift ? '<span class="badge badge-accent text-xs ml-1" title="Gift Packaging">🎁 Gift</span>' : ''}
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick="AdminOrders.viewOrder(${o.id})">View</button>
                        <button class="btn btn-primary btn-sm" onclick="AdminOrders.openStatusModal(${o.id}, '${o.order_status}', '${o.payment_status}')">Status</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderPagination(p) {
        const wrap = document.getElementById('orders-pagination');
        if (!wrap) return;

        wrap.innerHTML = `
            <div>Showing page ${p.current_page} of ${p.total_pages} (${p.total_items} orders total)</div>
            <div class="pagination-btns">
                <button class="btn btn-outline btn-sm" ${p.current_page <= 1 ? 'disabled' : ''} onclick="AdminOrders.load(${p.current_page - 1})">Previous</button>
                <button class="btn btn-outline btn-sm" ${p.current_page >= p.total_pages ? 'disabled' : ''} onclick="AdminOrders.load(${p.current_page + 1})">Next</button>
            </div>
        `;
    }

    async function viewOrder(orderId) {
        try {
            const res = await AdminAPI.getOrder(orderId);
            if (!res.success || !res.data) return;

            const o = res.data;
            const content = document.getElementById('order-detail-content');
            if (!content) return;

            content.innerHTML = `
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:24px;">
                    <!-- Customer & Shipping -->
                    <div class="card" style="padding:16px; margin-bottom:0;">
                        <div class="card-title text-sm mb-2">Customer & Shipping</div>
                        <div style="font-size:13px; line-height:1.6;">
                            <strong>${escapeHtml(o.customer_name)}</strong><br/>
                            <span>Email: ${escapeHtml(o.customer_email)}</span><br/>
                            <span>Phone: ${escapeHtml(o.customer_phone)}</span><br/>
                            <div style="margin-top:8px; padding-top:8px; border-top:1px solid var(--color-border-light);">
                                <span class="text-muted">Address:</span><br/>
                                ${escapeHtml(o.shipping_address)}<br/>
                                ${escapeHtml(o.city)}, ${escapeHtml(o.state)} - <strong>${escapeHtml(o.pincode)}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="card" style="padding:16px; margin-bottom:0;">
                        <div class="card-title text-sm mb-2">Financial Breakdown</div>
                        <div style="font-size:13px; line-height:1.8;">
                            <div style="display:flex; justify-content:space-between;">
                                <span class="text-muted">Subtotal:</span>
                                <span>₹${o.subtotal.toLocaleString('en-IN')}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <span class="text-muted">Coupon (${o.coupon_code || 'None'}):</span>
                                <span style="color:var(--color-success);">-₹${o.discount_amount.toLocaleString('en-IN')}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <span class="text-muted">Shipping:</span>
                                <span>${o.shipping_fee > 0 ? '₹' + o.shipping_fee : 'FREE'}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-weight:700; font-size:15px; border-top:1px solid var(--color-border); padding-top:6px; margin-top:6px;">
                                <span>Total Paid:</span>
                                <span style="color:var(--color-primary);">₹${o.total_amount.toLocaleString('en-IN')}</span>
                            </div>
                            <div style="margin-top:6px; font-size:11px;" class="text-muted">
                                Payment: <strong>${o.payment_method.toUpperCase()}</strong> (${o.payment_status.toUpperCase()})
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="card" style="padding:16px; margin-bottom:0;">
                    <div class="card-title text-sm mb-2">Purchased Handbag Items</div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product / SKU</th>
                                <th>Color</th>
                                <th>Unit Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${o.items.map(item => `
                                <tr>
                                    <td>
                                        <div class="font-medium">${escapeHtml(item.product_name)}</div>
                                        <div class="text-xs text-muted">SKU: <code>${escapeHtml(item.sku)}</code></div>
                                        ${item.monogram ? `
                                            <div style="display:inline-block; font-size:10px; background:#f5efe6; color:#8a5e30; padding:2px 6px; border-radius:3px; margin-top:4px;">
                                                ✨ Monogram: <strong>${escapeHtml(item.monogram.initials)}</strong> (${item.monogram.foil} foil)
                                            </div>
                                        ` : ''}
                                    </td>
                                    <td>${escapeHtml(item.color_name)}</td>
                                    <td>₹${item.unit_price.toLocaleString('en-IN')}</td>
                                    <td class="font-semibold">${item.quantity}</td>
                                    <td class="font-semibold">₹${item.total_price.toLocaleString('en-IN')}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>

                ${o.is_gift ? `
                    <div style="margin-top:16px; padding:12px 16px; background:#fff8f0; border:1px solid #fae2cc; border-radius:var(--radius-sm); font-size:12px;">
                        <strong>🎁 Gift Packaging Requested</strong><br/>
                        <em>Gift Note:</em> "${escapeHtml(o.gift_note || 'Complimentary luxury gift wrapping')}"
                    </div>
                ` : ''}
            `;

            document.getElementById('modal-order-number-title').textContent = o.order_number;
            App.openModal('modal-order-detail');
        } catch (err) {
            App.showToast('Failed to retrieve order details.', 'error');
        }
    }

    function openStatusModal(orderId, currentStatus, currentPayment) {
        document.getElementById('status-order-id').value = orderId;
        document.getElementById('status-order-select').value = currentStatus;
        document.getElementById('status-payment-select').value = currentPayment;
        document.getElementById('status-order-num-label').textContent = `#${orderId}`;
        App.openModal('modal-order-status');
    }

    async function handleStatusSubmit(e) {
        e.preventDefault();
        const orderId = document.getElementById('status-order-id').value;
        const orderStatus = document.getElementById('status-order-select').value;
        const paymentStatus = document.getElementById('status-payment-select').value;
        const btn = document.getElementById('status-submit-btn');

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Updating...';
        }

        try {
            const res = await AdminAPI.updateOrderStatus(orderId, orderStatus, paymentStatus);
            if (res.success) {
                App.showToast('Order status updated successfully.', 'success');
                App.closeModal('modal-order-status');
                load(currentPage);
            }
        } catch (err) {
            App.showToast(err.message || 'Failed to update order status.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save Changes';
            }
        }
    }

    function applyFilters() {
        const search = document.getElementById('orders-search-input')?.value.trim() || '';
        const status = document.getElementById('orders-status-filter')?.value || '';
        const payment = document.getElementById('orders-payment-filter')?.value || '';

        currentFilters = {};
        if (search) currentFilters.search = search;
        if (status) currentFilters.status = status;
        if (payment) currentFilters.payment_status = payment;

        load(1);
    }

    function resetFilters() {
        if (document.getElementById('orders-search-input')) document.getElementById('orders-search-input').value = '';
        if (document.getElementById('orders-status-filter')) document.getElementById('orders-status-filter').value = '';
        if (document.getElementById('orders-payment-filter')) document.getElementById('orders-payment-filter').value = '';
        currentFilters = {};
        load(1);
    }

    function getOrderStatusBadge(s) {
        return {
            'placed': 'badge-info',
            'processing': 'badge-warning',
            'shipped': 'badge-primary',
            'delivered': 'badge-success',
            'cancelled': 'badge-danger'
        }[s] || 'badge-neutral';
    }

    function getPaymentBadge(s) {
        return {
            'paid': 'badge-success',
            'pending': 'badge-warning',
            'failed': 'badge-danger',
            'refunded': 'badge-neutral'
        }[s] || 'badge-neutral';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load,
        viewOrder,
        openStatusModal,
        handleStatusSubmit,
        applyFilters,
        resetFilters
    };
})();
