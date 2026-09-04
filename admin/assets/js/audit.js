/**
 * BALENTO Admin Activity Audit Logs Controller
 */
const AdminAudit = (() => {
    let currentPage = 1;

    async function load(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('audit-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--color-muted);">Loading audit logs...</td></tr>`;

        try {
            const res = await AdminAPI.getAuditLogs(currentPage);
            if (res.success && res.data) {
                renderTable(res.data.logs);
                renderPagination(res.data.pagination);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load audit logs.</td></tr>`;
        }
    }

    function renderTable(logs) {
        const tbody = document.getElementById('audit-tbody');
        if (!tbody) return;

        if (!logs || logs.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:40px; color:var(--color-muted);">No activity logs recorded yet.</td></tr>`;
            return;
        }

        tbody.innerHTML = logs.map(l => `
            <tr>
                <td class="text-xs text-muted">${l.created_at ? l.created_at.substring(0, 19) : '-'}</td>
                <td class="font-medium">${escapeHtml(l.admin_username || 'System')}</td>
                <td><span class="badge badge-primary">${escapeHtml(l.action)}</span></td>
                <td><span class="badge badge-neutral">${escapeHtml(l.entity_type)}</span></td>
                <td class="text-xs" style="max-width:320px;" title="${escapeHtml(l.details || '')}">
                    ${escapeHtml(l.details || '-')}
                </td>
                <td class="text-xs text-muted"><code>${escapeHtml(l.ip_address || '127.0.0.1')}</code></td>
            </tr>
        `).join('');
    }

    function renderPagination(p) {
        const wrap = document.getElementById('audit-pagination');
        if (!wrap) return;

        wrap.innerHTML = `
            <div>Showing page ${p.current_page} of ${p.total_pages} (${p.total_items} log entries)</div>
            <div class="pagination-btns">
                <button class="btn btn-outline btn-sm" ${p.current_page <= 1 ? 'disabled' : ''} onclick="AdminAudit.load(${p.current_page - 1})">Previous</button>
                <button class="btn btn-outline btn-sm" ${p.current_page >= p.total_pages ? 'disabled' : ''} onclick="AdminAudit.load(${p.current_page + 1})">Next</button>
            </div>
        `;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load
    };
})();
