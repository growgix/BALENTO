const BalentoAPI = (() => {
    function detectApiBase() {
        if (window.BALENTO_API_BASE) {
            return window.BALENTO_API_BASE;
        }
        const origin = window.location.origin;
        const path = window.location.pathname;

        // Subdirectory check (e.g. XAMPP /project-name/...)
        const lastSlash = path.lastIndexOf('/');
        if (lastSlash > 0 && !path.includes('/api')) {
            const subfolder = path.substring(0, lastSlash);
            if (!subfolder.endsWith('/src') && !subfolder.endsWith('/public') && !subfolder.endsWith('/admin')) {
                return `${origin}${subfolder}/api`;
            }
        }

        if (origin.includes(':8000')) {
            return `${origin}/api`;
        }

        // Live Server / VS Code preview fallback to standard PHP port
        if (origin.includes(':5500') || origin.includes(':3000') || origin.includes(':5173')) {
            return 'http://localhost:8000/api';
        }

        return '/api';
    }

    const API_BASE = detectApiBase();

    async function request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        try {
            const response = await fetch(url, {
                ...options,
                headers
            });

            const contentType = response.headers.get('content-type') || '';
            let json;
            if (contentType.includes('application/json')) {
                json = await response.json();
            } else {
                const text = await response.text();
                try {
                    json = JSON.parse(text);
                } catch (e) {
                    console.warn(`[BalentoAPI Non-JSON Response from ${url}]:`, text.substring(0, 200));
                    throw new Error(`Server returned status ${response.status} with non-JSON response.`);
                }
            }

            if (!response.ok) {
                const errorMsg = json.message || 'An error occurred during the request.';
                throw new Error(errorMsg);
            }

            return json;
        } catch (err) {
            console.error(`[BalentoAPI Error] ${endpoint}:`, err.message);
            throw err;
        }
    }

    return {
        // Product Catalog
        async getProducts(params = {}) {
            const query = new URLSearchParams(params).toString();
            const endpoint = query ? `/products?${query}` : '/products';
            return request(endpoint, { method: 'GET' });
        },

        async getProductDetail(slugOrId) {
            return request(`/products/${encodeURIComponent(slugOrId)}`, { method: 'GET' });
        },

        // Pincode & Delivery Serviceability
        async checkPincode(pincode) {
            return request('/pincode/check', {
                method: 'POST',
                body: JSON.stringify({ pincode: pincode.trim() })
            });
        },

        // Coupon Validation & Pricing Breakdown
        async validateCoupon(code, subtotal) {
            return request('/coupons/validate', {
                method: 'POST',
                body: JSON.stringify({ code: code.trim(), subtotal })
            });
        },

        // Atomic Checkout
        async checkout(orderPayload, idempotencyKey = null) {
            const headers = {};
            if (idempotencyKey) {
                headers['X-Idempotency-Key'] = idempotencyKey;
            }

            return request('/orders/checkout', {
                method: 'POST',
                headers,
                body: JSON.stringify(orderPayload)
            });
        },

        // Public Order Tracking
        async trackOrder(orderNumber) {
            return request(`/orders/track/${encodeURIComponent(orderNumber.trim())}`, {
                method: 'GET'
            });
        },

        // Newsletter Subscription
        async subscribeNewsletter(email, source = 'footer') {
            return request('/newsletter/subscribe', {
                method: 'POST',
                body: JSON.stringify({ email: email.trim(), source })
            });
        },

        // Editorial Lookbook
        async getLookbook() {
            return request('/lookbook', { method: 'GET' });
        }
    };
})();
