/**
 * BALENTO Admin Newsletter Subscribers Controller
 */
const AdminNewsletter = (() => {
    let currentPage = 1;
    let currentSearch = '';

    async function load(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('newsletter-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:32px; color:var(--color-muted);">Loading subscribers...</td></tr>`;

        try {
            const params = {
                page: currentPage,
                limit: 25,
                search: currentSearch
            };

            const res = await AdminAPI.getSubscribers(params);
            if (res.success && res.data) {
                renderTable(res.data.subscribers);
                renderPagination(res.data.pagination);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load subscribers.</td></tr>`;
        }
    }

    function renderTable(subscribers) {
        const tbody = document.getElementById('newsletter-tbody');
        if (!tbody) return;

        if (!subscribers || subscribers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px; color:var(--color-muted);">No subscribers found.</td></tr>`;
            return;
        }

        tbody.innerHTML = subscribers.map(s => `
            <tr>
                <td class="font-medium">${escapeHtml(s.email)}</td>
                <td><span class="badge badge-neutral">${escapeHtml(s.source)}</span></td>
                <td>
                    <span class="badge ${s.is_active ? 'badge-success' : 'badge-neutral'}">${s.is_active ? 'Active' : 'Unsubscribed'}</span>
                </td>
                <td class="text-xs text-muted">${s.created_at ? s.created_at.substring(0, 16) : '-'}</td>
            </tr>
        `).join('');
    }

    function renderPagination(p) {
        const wrap = document.getElementById('newsletter-pagination');
        if (!wrap) return;

        wrap.innerHTML = `
            <div>Showing page ${p.current_page} of ${p.total_pages} (${p.total_items} subscribers)</div>
            <div class="pagination-btns">
                <button class="btn btn-outline btn-sm" ${p.current_page <= 1 ? 'disabled' : ''} onclick="AdminNewsletter.load(${p.current_page - 1})">Previous</button>
                <button class="btn btn-outline btn-sm" ${p.current_page >= p.total_pages ? 'disabled' : ''} onclick="AdminNewsletter.load(${p.current_page + 1})">Next</button>
            </div>
        `;
    }

    async function exportCsv() {
        try {
            App.showToast('Generating subscribers CSV export...', 'info');
            const csvText = await AdminAPI.exportSubscribers();
            const blob = new Blob([csvText], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.setAttribute('download', `balento_subscribers_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            App.showToast('Subscribers CSV exported successfully!', 'success');
        } catch (err) {
            App.showToast('Failed to export CSV.', 'error');
        }
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
        exportCsv,
        search
    };
})();
