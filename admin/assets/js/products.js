/**
 * BALENTO Admin Product Management Controller
 * Handles product listing, adding/editing products with dynamic variants, features, and images.
 */
const AdminProducts = (() => {
    let currentPage = 1;
    let currentFilters = {};
    let categoriesList = [];

    async function load(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('products-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--color-muted);">Loading product catalog...</td></tr>`;

        try {
            // Load categories for dropdowns if needed
            if (categoriesList.length === 0) {
                const catRes = await AdminAPI.getCategories();
                if (catRes.success) categoriesList = catRes.data || [];
                populateCategoryFilter();
            }

            const params = {
                page: currentPage,
                limit: 20,
                ...currentFilters
            };

            const res = await AdminAPI.getProducts(params);
            if (res.success && res.data) {
                renderTable(res.data.products);
                renderPagination(res.data.pagination);
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:32px; color:var(--color-danger);">Failed to load products: ${escapeHtml(err.message)}</td></tr>`;
        }
    }

    function populateCategoryFilter() {
        const select = document.getElementById('products-category-filter');
        const modalSelect = document.getElementById('product-form-category');
        if (!select) return;

        const optionsHtml = '<option value="">All Categories</option>' + categoriesList.map(c => `
            <option value="${c.id}">${escapeHtml(c.name)}</option>
        `).join('');
        select.innerHTML = optionsHtml;

        if (modalSelect) {
            modalSelect.innerHTML = categoriesList.map(c => `
                <option value="${c.id}">${escapeHtml(c.name)}</option>
            `).join('');
        }
    }

    function renderTable(products) {
        const tbody = document.getElementById('products-tbody');
        if (!tbody) return;

        if (!products || products.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:40px; color:var(--color-muted);">No products found in catalog.</td></tr>`;
            return;
        }

        tbody.innerHTML = products.map(p => `
            <tr>
                <td>
                    <div style="width:44px; height:44px; background:#f0ece8; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--color-border-light);">
                        ${p.primary_image ? `<img src="${p.primary_image}" alt="" style="width:100%; height:100%; object-fit:cover;" />` : ''}
                    </div>
                </td>
                <td>
                    <div class="font-medium">${escapeHtml(p.name)}</div>
                    <div class="text-xs text-muted">Slug: <code>${escapeHtml(p.slug)}</code></div>
                </td>
                <td><span class="badge badge-neutral">${escapeHtml(p.category_name)}</span></td>
                <td>
                    <div class="font-semibold">₹${p.price.toLocaleString('en-IN')}</div>
                    ${p.compare_at_price ? `<div class="text-xs text-muted" style="text-decoration:line-through;">₹${p.compare_at_price.toLocaleString('en-IN')}</div>` : ''}
                </td>
                <td>
                    <div class="font-semibold">${p.total_stock} in stock</div>
                    <div class="text-xs text-muted">${p.variant_count} colors</div>
                </td>
                <td>
                    ${p.tag ? `<span class="badge badge-accent">${escapeHtml(p.tag)}</span>` : '<span class="text-muted text-xs">-</span>'}
                </td>
                <td>
                    <span class="badge ${p.is_active ? 'badge-success' : 'badge-neutral'}">${p.is_active ? 'Active' : 'Inactive'}</span>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick="AdminProducts.openEditModal(${p.id})">Edit</button>
                        <button class="btn btn-outline btn-sm text-danger" onclick="AdminProducts.toggleActive(${p.id}, ${p.is_active ? 0 : 1})">
                            ${p.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderPagination(p) {
        const wrap = document.getElementById('products-pagination');
        if (!wrap) return;

        wrap.innerHTML = `
            <div>Showing page ${p.current_page} of ${p.total_pages} (${p.total_items} items total)</div>
            <div class="pagination-btns">
                <button class="btn btn-outline btn-sm" ${p.current_page <= 1 ? 'disabled' : ''} onclick="AdminProducts.load(${p.current_page - 1})">Previous</button>
                <button class="btn btn-outline btn-sm" ${p.current_page >= p.total_pages ? 'disabled' : ''} onclick="AdminProducts.load(${p.current_page + 1})">Next</button>
            </div>
        `;
    }

    function openCreateModal() {
        document.getElementById('product-modal-title').textContent = 'Add New Handbag Silhouette';
        document.getElementById('product-form-id').value = '';
        document.getElementById('product-form-name').value = '';
        document.getElementById('product-form-slug').value = '';
        document.getElementById('product-form-tag').value = '';
        document.getElementById('product-form-price').value = '';
        document.getElementById('product-form-compare').value = '';
        document.getElementById('product-form-desc').value = '';
        document.getElementById('product-form-dimensions').value = '';
        document.getElementById('product-form-weight').value = '';
        document.getElementById('product-form-active').checked = true;

        // Reset variant rows
        const varContainer = document.getElementById('product-variants-builder');
        varContainer.innerHTML = '';
        addVariantRow('Black', '#1c1b1b', 20);
        addVariantRow('Cognac', '#8B5A2B', 15);
        addVariantRow('Coffee Brown', '#4A3B32', 15);

        // Reset feature rows
        const featContainer = document.getElementById('product-features-builder');
        featContainer.innerHTML = '';
        addFeatureRow('14" Laptop Compartment');
        addFeatureRow('Reinforced Leather Drop Handles');

        // Reset image rows
        const imgContainer = document.getElementById('product-images-builder');
        imgContainer.innerHTML = '';
        addImageRow('https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=900&q=80', 'primary');

        App.openModal('modal-product-form');
    }

    async function openEditModal(productId) {
        try {
            const res = await AdminAPI.getProduct(productId);
            if (!res.success || !res.data) return;

            const p = res.data;
            document.getElementById('product-modal-title').textContent = `Edit Product: ${p.name}`;
            document.getElementById('product-form-id').value = p.id;
            document.getElementById('product-form-name').value = p.name;
            document.getElementById('product-form-slug').value = p.slug;
            document.getElementById('product-form-category').value = p.category_id;
            document.getElementById('product-form-tag').value = p.tag || '';
            document.getElementById('product-form-price').value = p.price;
            document.getElementById('product-form-compare').value = p.compare_at_price || '';
            document.getElementById('product-form-desc').value = p.description || '';
            document.getElementById('product-form-dimensions').value = p.dimensions || '';
            document.getElementById('product-form-weight').value = p.weight || '';
            document.getElementById('product-form-active').checked = p.is_active;

            // Populate variants
            const varContainer = document.getElementById('product-variants-builder');
            varContainer.innerHTML = '';
            (p.variants || []).forEach(v => {
                addVariantRow(v.color_name, v.color_hex, v.stock_quantity, v.id, v.sku);
            });

            // Populate features
            const featContainer = document.getElementById('product-features-builder');
            featContainer.innerHTML = '';
            (p.features || []).forEach(f => {
                addFeatureRow(f.feature_text);
            });

            // Populate images
            const imgContainer = document.getElementById('product-images-builder');
            imgContainer.innerHTML = '';
            (p.images || []).forEach(img => {
                addImageRow(img.image_url, img.image_type, img.id);
            });

            App.openModal('modal-product-form');
        } catch (err) {
            App.showToast('Failed to load product details.', 'error');
        }
    }

    function addVariantRow(colorName = '', colorHex = '#1c1b1b', stock = 10, id = '', sku = '') {
        const container = document.getElementById('product-variants-builder');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'form-row mb-2 variant-item-row';
        row.style.alignItems = 'center';
        row.innerHTML = `
            <input type="hidden" class="var-id" value="${id}" />
            <input type="text" class="form-control var-name" placeholder="Color (e.g. Cognac)" value="${escapeHtml(colorName)}" required />
            <div style="display:flex; align-items:center; gap:6px;">
                <input type="color" class="var-hex-picker" value="${colorHex}" style="width:36px; height:34px; border:none; background:none; cursor:pointer;" onchange="this.nextElementSibling.value=this.value" />
                <input type="text" class="form-control var-hex" value="${colorHex}" style="width:90px;" placeholder="#hex" />
            </div>
            <input type="number" class="form-control var-stock" placeholder="Stock" min="0" value="${stock}" style="width:90px;" required />
            <input type="text" class="form-control var-sku" placeholder="SKU (optional)" value="${escapeHtml(sku)}" style="width:140px;" />
            <button type="button" class="btn btn-outline btn-sm text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    }

    function addFeatureRow(text = '') {
        const container = document.getElementById('product-features-builder');
        if (!container) return;

        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.gap = '8px';
        row.style.marginBottom = '8px';
        row.className = 'feature-item-row';
        row.innerHTML = `
            <input type="text" class="form-control feat-text" placeholder="Bullet specification" value="${escapeHtml(text)}" required />
            <button type="button" class="btn btn-outline btn-sm text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    }

    function addImageRow(url = '', type = 'gallery', id = '') {
        const container = document.getElementById('product-images-builder');
        if (!container) return;

        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.gap = '8px';
        row.style.marginBottom = '8px';
        row.className = 'image-item-row';
        row.innerHTML = `
            <input type="hidden" class="img-id" value="${id}" />
            <input type="url" class="form-control img-url" placeholder="https://image-url..." value="${escapeHtml(url)}" style="flex:2;" required />
            <select class="form-control img-type" style="width:120px;">
                <option value="primary" ${type === 'primary' ? 'selected' : ''}>Primary</option>
                <option value="hover" ${type === 'hover' ? 'selected' : ''}>Hover</option>
                <option value="gallery" ${type === 'gallery' ? 'selected' : ''}>Gallery</option>
            </select>
            <button type="button" class="btn btn-outline btn-sm text-danger" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    }

    async function handleImageUploadInput(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        try {
            App.showToast('Uploading image securely to server storage...', 'info');
            const res = await AdminAPI.uploadImage(file);
            if (res.success && res.data) {
                addImageRow(res.data.url, 'gallery');
                App.showToast('Image uploaded successfully!', 'success');
            }
        } catch (err) {
            App.showToast(err.message || 'Image upload failed.', 'error');
        } finally {
            input.value = '';
        }
    }

    async function handleProductSubmit(e) {
        e.preventDefault();
        const id = document.getElementById('product-form-id').value;
        const btn = document.getElementById('product-submit-btn');

        // Extract Variants
        const variants = [];
        document.querySelectorAll('.variant-item-row').forEach(row => {
            const vId = row.querySelector('.var-id')?.value;
            const name = row.querySelector('.var-name')?.value.trim();
            const hex = row.querySelector('.var-hex')?.value.trim() || '#1c1b1b';
            const stock = parseInt(row.querySelector('.var-stock')?.value || '0', 10);
            const sku = row.querySelector('.var-sku')?.value.trim();
            if (name) {
                variants.push({
                    id: vId ? parseInt(vId, 10) : undefined,
                    color_name: name,
                    color_hex: hex,
                    stock_quantity: stock,
                    sku: sku || undefined
                });
            }
        });

        // Extract Features
        const features = [];
        document.querySelectorAll('.feature-item-row').forEach(row => {
            const txt = row.querySelector('.feat-text')?.value.trim();
            if (txt) features.push(txt);
        });

        // Extract Images
        const images = [];
        document.querySelectorAll('.image-item-row').forEach(row => {
            const url = row.querySelector('.img-url')?.value.trim();
            const type = row.querySelector('.img-type')?.value || 'gallery';
            if (url) images.push({ image_url: url, image_type: type });
        });

        const payload = {
            category_id: parseInt(document.getElementById('product-form-category').value, 10),
            name: document.getElementById('product-form-name').value.trim(),
            slug: document.getElementById('product-form-slug').value.trim(),
            tag: document.getElementById('product-form-tag').value.trim() || null,
            price: parseFloat(document.getElementById('product-form-price').value),
            compare_at_price: document.getElementById('product-form-compare').value ? parseFloat(document.getElementById('product-form-compare').value) : null,
            description: document.getElementById('product-form-desc').value.trim(),
            dimensions: document.getElementById('product-form-dimensions').value.trim() || null,
            weight: document.getElementById('product-form-weight').value.trim() || null,
            is_active: document.getElementById('product-form-active').checked ? 1 : 0,
            variants,
            features,
            images
        };

        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving...';
        }

        try {
            if (id) {
                await AdminAPI.updateProduct(id, payload);
                App.showToast('Product updated successfully.', 'success');
            } else {
                await AdminAPI.createProduct(payload);
                App.showToast('New product silhouette created.', 'success');
            }

            App.closeModal('modal-product-form');
            load(currentPage);
        } catch (err) {
            App.showToast(err.message || 'Error saving product.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save Product';
            }
        }
    }

    async function toggleActive(id, newStatus) {
        const action = newStatus ? 'activate' : 'deactivate';
        App.confirmDialog(`Are you sure you want to ${action} this product?`, async () => {
            try {
                if (newStatus) {
                    await AdminAPI.updateProduct(id, { is_active: 1 });
                    App.showToast('Product activated.', 'success');
                } else {
                    await AdminAPI.deleteProduct(id);
                    App.showToast('Product deactivated.', 'success');
                }
                load(currentPage);
            } catch (err) {
                App.showToast(err.message || 'Action failed.', 'error');
            }
        });
    }

    function applyFilters() {
        const search = document.getElementById('products-search-input')?.value.trim() || '';
        const cat = document.getElementById('products-category-filter')?.value || '';
        const status = document.getElementById('products-status-filter')?.value || '';

        currentFilters = {};
        if (search) currentFilters.search = search;
        if (cat) currentFilters.category_id = cat;
        if (status !== '') currentFilters.is_active = status;

        load(1);
    }

    function resetFilters() {
        if (document.getElementById('products-search-input')) document.getElementById('products-search-input').value = '';
        if (document.getElementById('products-category-filter')) document.getElementById('products-category-filter').value = '';
        if (document.getElementById('products-status-filter')) document.getElementById('products-status-filter').value = '';
        currentFilters = {};
        load(1);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    return {
        load,
        openCreateModal,
        openEditModal,
        addVariantRow,
        addFeatureRow,
        addImageRow,
        handleImageUploadInput,
        handleProductSubmit,
        toggleActive,
        applyFilters,
        resetFilters
    };
})();
