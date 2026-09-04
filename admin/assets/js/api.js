/**
 * BALENTO Admin Backoffice API Client
 * Manages JWT Bearer authentication headers, token storage, and API communication.
 */
const AdminAPI = (() => {
    const TOKEN_KEY = 'balento_admin_jwt';
    const USER_KEY = 'balento_admin_user';

    // Auto-detect base API endpoint
    const API_BASE = window.location.origin.includes(':8000')
        ? `${window.location.origin}/api/admin`
        : '/api/admin';

    function getToken() {
        return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY);
    }

    function setSession(token, user, remember = true) {
        if (remember) {
            localStorage.setItem(TOKEN_KEY, token);
            localStorage.setItem(USER_KEY, JSON.stringify(user));
        } else {
            sessionStorage.setItem(TOKEN_KEY, token);
            sessionStorage.setItem(USER_KEY, JSON.stringify(user));
        }
    }

    function clearSession() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        sessionStorage.removeItem(TOKEN_KEY);
        sessionStorage.removeItem(USER_KEY);
    }

    function getUser() {
        const str = localStorage.getItem(USER_KEY) || sessionStorage.getItem(USER_KEY);
        try {
            return str ? JSON.parse(str) : null;
        } catch {
            return null;
        }
    }

    async function request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint}`;
        const token = getToken();

        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        // If body is not FormData, default to application/json
        if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            if (response.status === 401 && endpoint !== '/login') {
                clearSession();
                window.location.reload();
                throw new Error('Session expired. Please log in again.');
            }

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('text/csv')) {
                return response.text();
            }

            const json = await response.json();
            if (!response.ok) {
                const errorMsg = json.message || 'An administrative error occurred.';
                const err = new Error(errorMsg);
                err.errors = json.errors || {};
                err.status = response.status;
                throw err;
            }

            return json;
        } catch (err) {
            console.error(`[AdminAPI Error] ${endpoint}:`, err);
            throw err;
        }
    }

    return {
        getToken,
        getUser,
        setSession,
        clearSession,
        isAuthenticated: () => !!getToken(),

        // Auth
        login: (identifier, password, remember = true) => 
            request('/login', {
                method: 'POST',
                body: JSON.stringify({ identifier, password })
            }),
        getMe: () => request('/me'),
        changePassword: (data) => request('/me/password', { method: 'PUT', body: JSON.stringify(data) }),

        // Dashboard & Analytics
        getDashboardStats: (threshold = 15) => request(`/dashboard/stats?threshold=${threshold}`),
        getAnalytics: (range = '30d') => request(`/analytics?range=${range}`),

        // Orders
        getOrders: (params = {}) => {
            const q = new URLSearchParams(params).toString();
            return request(`/orders?${q}`);
        },
        getOrder: (id) => request(`/orders/${id}`),
        updateOrderStatus: (id, orderStatus, paymentStatus) => 
            request(`/orders/${id}/status`, {
                method: 'PUT',
                body: JSON.stringify({ order_status: orderStatus, payment_status: paymentStatus })
            }),

        // Products
        getProducts: (params = {}) => {
            const q = new URLSearchParams(params).toString();
            return request(`/products?${q}`);
        },
        getProduct: (id) => request(`/products/${id}`),
        createProduct: (data) => request('/products', { method: 'POST', body: JSON.stringify(data) }),
        updateProduct: (id, data) => request(`/products/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
        deleteProduct: (id) => request(`/products/${id}`, { method: 'DELETE' }),

        // Inventory
        getInventory: (params = {}) => {
            const q = new URLSearchParams(params).toString();
            return request(`/inventory?${q}`);
        },
        adjustInventory: (variantId, adjustment, reason) => 
            request('/inventory/adjust', {
                method: 'PUT',
                body: JSON.stringify({ variant_id: variantId, adjustment, reason })
            }),

        // Categories
        getCategories: () => request('/categories'),
        createCategory: (data) => request('/categories', { method: 'POST', body: JSON.stringify(data) }),
        updateCategory: (id, data) => request(`/categories/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
        deleteCategory: (id) => request(`/categories/${id}`, { method: 'DELETE' }),

        // Coupons
        getCoupons: () => request('/coupons'),
        createCoupon: (data) => request('/coupons', { method: 'POST', body: JSON.stringify(data) }),
        updateCoupon: (id, data) => request(`/coupons/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
        deleteCoupon: (id) => request(`/coupons/${id}`, { method: 'DELETE' }),

        // Lookbook
        getLookbook: () => request('/lookbook'),
        createLookbook: (data) => request('/lookbook', { method: 'POST', body: JSON.stringify(data) }),
        updateLookbook: (id, data) => request(`/lookbook/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
        deleteLookbook: (id) => request(`/lookbook/${id}`, { method: 'DELETE' }),

        // Pincodes
        getPincodes: (params = {}) => {
            const q = new URLSearchParams(params).toString();
            return request(`/pincodes?${q}`);
        },
        createPincode: (data) => request('/pincodes', { method: 'POST', body: JSON.stringify(data) }),
        updatePincode: (id, data) => request(`/pincodes/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
        deletePincode: (id) => request(`/pincodes/${id}`, { method: 'DELETE' }),

        // Newsletter
        getSubscribers: (params = {}) => {
            const q = new URLSearchParams(params).toString();
            return request(`/newsletter?${q}`);
        },
        exportSubscribers: () => request('/newsletter/export'),

        // Admin Users (RBAC)
        getAdminUsers: () => request('/users'),
        createAdminUser: (data) => request('/users', { method: 'POST', body: JSON.stringify(data) }),
        updateAdminUser: (id, data) => request(`/users/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
        deleteAdminUser: (id) => request(`/users/${id}`, { method: 'DELETE' }),

        // Audit Logs
        getAuditLogs: (page = 1) => request(`/audit-logs?page=${page}`),

        // Image Upload
        uploadImage: (file) => {
            const fd = new FormData();
            fd.append('image', file);
            return request('/upload', { method: 'POST', body: fd });
        }
    };
})();
