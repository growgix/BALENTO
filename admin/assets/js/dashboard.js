/**
 * BALENTO Admin Dashboard Controller
 * Renders KPI cards, sales analytics interactive SVG charts, recent orders, and low-stock alerts.
 */
const AdminDashboard = (() => {
    async function load() {
        const container = document.getElementById('tab-dashboard');
        if (!container) return;

        try {
            const res = await AdminAPI.getDashboardStats();
            if (res.success && res.data) {
                render(res.data);
            }
        } catch (err) {
            console.error('Failed to load dashboard statistics:', err);
            App.showToast('Unable to load dashboard data.', 'error');
        }
    }

    function render(data) {
        // 1. Metric values
        setVal('stat-total-revenue', `₹${Math.round(data.total_revenue).toLocaleString('en-IN')}`);
        setVal('stat-today-revenue', `₹${Math.round(data.today_revenue).toLocaleString('en-IN')}`);
        setVal('stat-total-orders', data.total_orders.toLocaleString('en-IN'));
        setVal('stat-today-orders', data.today_orders.toLocaleString('en-IN'));
        setVal('stat-aov', `₹${Math.round(data.avg_order_value).toLocaleString('en-IN')}`);
        setVal('stat-active-products', `${data.active_products} / ${data.total_products}`);
        setVal('stat-low-stock', data.low_stock_count);
        setVal('stat-subscribers', data.subscribers_count.toLocaleString('en-IN'));

        // 2. Order Status Breakdown counts
        const obs = data.orders_by_status || {};
        setVal('count-placed', obs.placed || 0);
        setVal('count-processing', obs.processing || 0);
        setVal('count-shipped', obs.shipped || 0);
        setVal('count-delivered', obs.delivered || 0);
        setVal('count-cancelled', obs.cancelled || 0);

        // 3. Render Sales Chart (SVG Vector Chart)
        renderSalesChart(data.sales_trend || []);

        // 4. Render Recent Orders
        renderRecentOrders(data.recent_orders || []);

        // 5. Render Low Stock Alerts Widget
        renderLowStockAlerts(data.low_stock_alerts || []);
    }

    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function renderSalesChart(trends) {
        const chartBox = document.getElementById('dashboard-sales-chart');
        if (!chartBox) return;

        if (!trends || trends.length === 0) {
            chartBox.innerHTML = `
                <div style="height:100%; display:flex; align-items:center; justify-content:center; color:var(--color-muted); font-size:13px;">
                    No order sales recorded in this period yet.
                </div>
            `;
            return;
        }

        const maxRev = Math.max(...trends.map(t => t.revenue), 1000);
        const width = 600;
        const height = 180;
        const padding = 20;

        const points = trends.map((t, idx) => {
            const x = padding + (idx * ((width - (padding * 2)) / Math.max(1, trends.length - 1)));
            const y = (height - padding) - ((t.revenue / maxRev) * (height - (padding * 2)));
            return { x, y, ...t };
        });

        const pathD = points.map((p, i) => (i === 0 ? `M ${p.x} ${p.y}` : `L ${p.x} ${p.y}`)).join(' ');
        const areaD = `${pathD} L ${points[points.length - 1].x} ${height - padding} L ${points[0].x} ${height - padding} Z`;

        chartBox.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" style="width:100%; height:100%; overflow:visible;">
                <!-- Grid Lines -->
                <line x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}" stroke="#e6e2de" stroke-width="1" />
                <line x1="${padding}" y1="${height / 2}" x2="${width - padding}" y2="${height / 2}" stroke="#f0ece8" stroke-dasharray="4" />
                
                <!-- Area Gradient -->
                <defs>
                    <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#c49a6c" stop-opacity="0.35" />
                        <stop offset="100%" stop-color="#c49a6c" stop-opacity="0.0" />
                    </linearGradient>
                </defs>
                <path d="${areaD}" fill="url(#chartGrad)" />
                <path d="${pathD}" fill="none" stroke="#c49a6c" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                <!-- Dots & Labels -->
                ${points.map(p => `
                    <circle cx="${p.x}" cy="${p.y}" r="4" fill="#ffffff" stroke="#c49a6c" stroke-width="2">
                        <title>${p.date}: ₹${p.revenue.toLocaleString('en-IN')} (${p.orders_count} orders)</title>
                    </circle>
                    <text x="${p.x}" y="${height}" font-size="9" fill="#9e9a95" text-anchor="middle" font-family="sans-serif">
                        ${p.date ? p.date.split('-').slice(1).join('/') : ''}
                    </text>
                `).join('')}
            </svg>
        `;
    }

    function renderRecentOrders(orders) {
        const tbody = document.getElementById('dashboard-recent-orders-tbody');
        if (!tbody) return;

        if (orders.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--color-muted);">No orders recorded yet.</td></tr>`;
            return;
        }

        tbody.innerHTML = orders.map(o => `
            <tr>
                <td class="font-semibold"><a href="javascript:void(0)" onclick="AdminOrders.viewOrder(${o.id})" style="color:var(--color-primary); text-decoration:underline;">${o.order_number}</a></td>
                <td>${escapeHtml(o.customer_name)}</td>
                <td class="text-muted text-xs">${o.created_at ? o.created_at.substring(0, 16) : '-'}</td>
                <td class="font-semibold">₹${o.total_amount.toLocaleString('en-IN')}</td>
                <td><span class="badge ${getStatusBadge(o.order_status)}">${o.order_status}</span></td>
                <td>
                    <button class="btn btn-outline btn-sm" onclick="AdminOrders.viewOrder(${o.id})">Details</button>
                </td>
            </tr>
        `).join('');
    }

    function renderLowStockAlerts(alerts) {
        const listEl = document.getElementById('dashboard-low-stock-list');
        if (!listEl) return;

        if (alerts.length === 0) {
            listEl.innerHTML = `<div style="padding:16px; text-align:center; color:var(--color-success); font-size:12px;">✓ All inventory stock levels are healthy!</div>`;
            return;
        }

        listEl.innerHTML = alerts.map(a => `
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--color-border-light);">
                <div>
                    <div style="font-size:13px; font-weight:500;">${escapeHtml(a.product_name)}</div>
                    <div style="font-size:11px; color:var(--color-secondary);">${escapeHtml(a.color_name)} • SKU: <code>${a.sku}</code></div>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span class="badge ${a.stock_quantity === 0 ? 'badge-danger' : 'badge-warning'}">
                        ${a.stock_quantity} left
                    </span>
                    <button class="btn btn-outline btn-sm" onclick="AdminInventory.openAdjustModal(${a.variant_id}, '${escapeHtml(a.sku)}', ${a.stock_quantity})">Restock</button>
                </div>
            </div>
        `).join('');
    }

    function getStatusBadge(status) {
        return {
            'placed': 'badge-info',
            'processing': 'badge-warning',
            'shipped': 'badge-primary',
            'delivered': 'badge-success',
            'cancelled': 'badge-danger'
        }[status] || 'badge-neutral';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load
    };
})();
