<?php
declare(strict_types=1);

// 1. Ensure trailing slash for accurate relative asset resolution
$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH) ?? '';
if (!str_ends_with($path, '/') && !str_ends_with($path, '.php')) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $path . '/' . ($qs ? '?' . $qs : ''), true, 301);
    exit;
}

$baseHref = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/';
if ($baseHref === '//' || $baseHref === '\\') {
    $baseHref = '/admin/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BALENTO Backoffice — E-Commerce Administration</title>
    <base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Google Fonts for Quiet Luxury SaaS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..700;1,6..96,400..700&family=Cinzel:wght@400..700&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .hidden { display: none !important; }
    </style>
</head>
<body>

    <!-- =======================================================================
         1. LOGIN SCREEN (Default Visible When Not Authenticated)
         ======================================================================= -->
    <div id="login-screen">
        <div class="login-card">
            <div class="login-brand">BALENTO</div>
            <div class="login-sub">Executive Backoffice Login</div>
            <div id="login-error-msg" class="hidden" style="background:var(--color-danger-bg); color:var(--color-danger); padding:10px 14px; border-radius:var(--radius-sm); font-size:12px; margin-bottom:18px; text-align:left;">
                Invalid credentials.
            </div>
            <form id="admin-login-form">
                <div class="form-group" style="text-align:left;">
                    <label class="form-label">Username or Email</label>
                    <input type="text" id="login-username" class="form-control" placeholder="admin@balento.com" required autofocus />
                </div>
                <div class="form-group" style="text-align:left; margin-bottom:20px;">
                    <label class="form-label">Password</label>
                    <input type="password" id="login-password" class="form-control" placeholder="••••••••••••" required />
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; font-size:12px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; color:var(--color-secondary);">
                        <input type="checkbox" id="login-remember" checked /> Remember session
                    </label>
                </div>
                <button type="submit" id="login-submit-btn" class="btn btn-primary" style="width:100%; padding:12px; font-size:13px;">
                    Sign In to Backoffice
                </button>
            </form>
        </div>
    </div>

    <!-- =======================================================================
         2. ADMIN APPLICATION SHELL (When Authenticated)
         ======================================================================= -->
    <div id="admin-root" class="hidden">
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <a href="javascript:void(0)" class="brand-logo">BALENTO</a>
                <span class="badge-tag">ADMIN</span>
            </div>

            <nav class="sidebar-menu">
                <div class="sidebar-group-title">Overview</div>
                <a class="nav-item active" data-tab="dashboard">
                    <span class="icon">📊</span>
                    <span class="label">Dashboard</span>
                </a>

                <div class="sidebar-group-title">Catalog & Stock</div>
                <a class="nav-item" data-tab="products" data-role-min="manager">
                    <span class="icon">👜</span>
                    <span class="label">Products</span>
                </a>
                <a class="nav-item" data-tab="inventory">
                    <span class="icon">📦</span>
                    <span class="label">Inventory</span>
                </a>
                <a class="nav-item" data-tab="categories" data-role-min="manager">
                    <span class="icon">🏷️</span>
                    <span class="label">Categories</span>
                </a>

                <div class="sidebar-group-title">Sales & Fulfillment</div>
                <a class="nav-item" data-tab="orders">
                    <span class="icon">🛒</span>
                    <span class="label">Orders</span>
                </a>
                <a class="nav-item" data-tab="coupons" data-role-min="manager">
                    <span class="icon">🎟️</span>
                    <span class="label">Coupons</span>
                </a>

                <div class="sidebar-group-title">Content & Editorial</div>
                <a class="nav-item" data-tab="lookbook" data-role-min="manager">
                    <span class="icon">📸</span>
                    <span class="label">Lookbook</span>
                </a>
                <a class="nav-item" data-tab="newsletter" data-role-min="manager">
                    <span class="icon">✉️</span>
                    <span class="label">Newsletter</span>
                </a>

                <div class="sidebar-group-title">Logistics</div>
                <a class="nav-item" data-tab="pincodes" data-role-min="manager">
                    <span class="icon">📍</span>
                    <span class="label">PIN Delivery</span>
                </a>

                <div class="sidebar-group-title" data-role-min="admin">System</div>
                <a class="nav-item" data-tab="admins" data-role-min="admin">
                    <span class="icon">👥</span>
                    <span class="label">Admin Users</span>
                </a>
                <a class="nav-item" data-tab="audit" data-role-min="admin">
                    <span class="icon">📜</span>
                    <span class="label">Audit Logs</span>
                </a>
                <a class="nav-item" data-tab="profile">
                    <span class="icon">⚙️</span>
                    <span class="label">My Profile</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-mini-profile">
                    <div class="user-avatar" id="sidebar-user-avatar">A</div>
                    <div class="user-info">
                        <div class="user-name" id="sidebar-user-name">Admin</div>
                        <div class="user-role" id="sidebar-user-role">ADMINISTRATOR</div>
                    </div>
                </div>
                <button id="btn-logout" class="btn-icon-logout" title="Sign Out">🚪</button>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Top Header -->
            <header class="admin-header">
                <div class="header-left">
                    <button id="sidebar-toggle" class="sidebar-toggle-btn">☰</button>
                    <h1 class="header-title" id="page-header-title">Dashboard</h1>
                </div>
                <div class="header-right">
                    <span class="badge badge-success" style="font-size:11px; padding:4px 8px;">
                        ● Live DB Connected
                    </span>
                    <a href="../index.html" target="_blank" class="storefront-link">
                        View Storefront ↗
                    </a>
                </div>
            </header>

            <!-- Body Container -->
            <div class="admin-body">

                <!-- TAB 1: DASHBOARD -->
                <section id="tab-dashboard" class="tab-view">
                    <!-- KPI Cards Grid -->
                    <div class="metrics-grid">
                        <div class="metric-card accent">
                            <div class="metric-header">
                                <span class="metric-label">Total Revenue</span>
                                <span class="metric-icon">💰</span>
                            </div>
                            <div class="metric-value" id="stat-total-revenue">₹0</div>
                            <div class="metric-sub">Today: <span id="stat-today-revenue">₹0</span></div>
                        </div>

                        <div class="metric-card success">
                            <div class="metric-header">
                                <span class="metric-label">Total Orders</span>
                                <span class="metric-icon">🛒</span>
                            </div>
                            <div class="metric-value" id="stat-total-orders">0</div>
                            <div class="metric-sub">Today: <span id="stat-today-orders">0</span> orders</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-label">Avg Order Value (AOV)</span>
                                <span class="metric-icon">📊</span>
                            </div>
                            <div class="metric-value" id="stat-aov">₹0</div>
                            <div class="metric-sub">Paid transactions</div>
                        </div>

                        <div class="metric-card warning">
                            <div class="metric-header">
                                <span class="metric-label">Low Stock Variants</span>
                                <span class="metric-icon">⚠️</span>
                            </div>
                            <div class="metric-value" id="stat-low-stock">0</div>
                            <div class="metric-sub">At or below 15 units</div>
                        </div>

                        <div class="metric-card">
                            <div class="metric-header">
                                <span class="metric-label">Subscribers</span>
                                <span class="metric-icon">✉️</span>
                            </div>
                            <div class="metric-value" id="stat-subscribers">0</div>
                            <div class="metric-sub">Active inner circle list</div>
                        </div>
                    </div>

                    <!-- Analytics Chart & Status Summary -->
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:24px; margin-bottom:24px;">
                        <div class="card" style="margin-bottom:0;">
                            <div class="card-header">
                                <div class="card-title">Sales Revenue & Trend (Last 7 Days)</div>
                                <span class="text-xs text-muted">Daily Paid Volume</span>
                            </div>
                            <div class="chart-container" id="dashboard-sales-chart">
                                <!-- Rendered via SVG in dashboard.js -->
                            </div>
                        </div>

                        <div class="card" style="margin-bottom:0;">
                            <div class="card-header">
                                <div class="card-title">Fulfillment Progression Status</div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; padding-bottom:8px; border-bottom:1px solid var(--color-border-light);">
                                    <span>Placed (Pending Dispatch)</span>
                                    <span class="badge badge-info" id="count-placed">0</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding-bottom:8px; border-bottom:1px solid var(--color-border-light);">
                                    <span>Processing (In Atelier Packaging)</span>
                                    <span class="badge badge-warning" id="count-processing">0</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding-bottom:8px; border-bottom:1px solid var(--color-border-light);">
                                    <span>Shipped (With Air Cargo Courier)</span>
                                    <span class="badge badge-primary" id="count-shipped">0</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding-bottom:8px; border-bottom:1px solid var(--color-border-light);">
                                    <span>Delivered</span>
                                    <span class="badge badge-success" id="count-delivered">0</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span>Cancelled</span>
                                    <span class="badge badge-danger" id="count-cancelled">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Orders & Low Stock Grid -->
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px;">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Recent Storefront Orders</div>
                                <a href="javascript:void(0)" onclick="App.switchTab('orders')" class="text-xs text-muted" style="text-decoration:underline;">View All Orders →</a>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashboard-recent-orders-tbody"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Inventory Alerts (≤ 15)</div>
                                <a href="javascript:void(0)" onclick="App.switchTab('inventory')" class="text-xs text-muted" style="text-decoration:underline;">Manage Stock →</a>
                            </div>
                            <div id="dashboard-low-stock-list"></div>
                        </div>
                    </div>
                </section>

                <!-- TAB 2: ORDERS -->
                <section id="tab-orders" class="tab-view hidden">
                    <div class="card">
                        <div class="filter-bar">
                            <div class="filter-left">
                                <div class="search-input-wrap">
                                    <span class="icon">🔍</span>
                                    <input type="text" id="orders-search-input" class="form-control" placeholder="Search Order #, Name, Phone..." onkeyup="if(event.key==='Enter') AdminOrders.applyFilters()" />
                                </div>
                                <select id="orders-status-filter" class="form-control" style="width:160px;" onchange="AdminOrders.applyFilters()">
                                    <option value="">All Order Statuses</option>
                                    <option value="placed">Placed</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <select id="orders-payment-filter" class="form-control" style="width:160px;" onchange="AdminOrders.applyFilters()">
                                    <option value="">All Payment Statuses</option>
                                    <option value="paid">Paid</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                                <button class="btn btn-outline" onclick="AdminOrders.applyFilters()">Filter</button>
                                <button class="btn btn-outline" onclick="AdminOrders.resetFilters()">Reset</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer Details</th>
                                        <th>Date & Time</th>
                                        <th>Total Paid</th>
                                        <th>Payment</th>
                                        <th>Fulfillment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="orders-tbody"></tbody>
                            </table>
                        </div>
                        <div id="orders-pagination" class="pagination-wrap"></div>
                    </div>
                </section>

                <!-- TAB 3: PRODUCTS -->
                <section id="tab-products" class="tab-view hidden">
                    <div class="card">
                        <div class="filter-bar">
                            <div class="filter-left">
                                <div class="search-input-wrap">
                                    <span class="icon">🔍</span>
                                    <input type="text" id="products-search-input" class="form-control" placeholder="Search Silhouette Name, Tag..." onkeyup="if(event.key==='Enter') AdminProducts.applyFilters()" />
                                </div>
                                <select id="products-category-filter" class="form-control" style="width:180px;" onchange="AdminProducts.applyFilters()"></select>
                                <select id="products-status-filter" class="form-control" style="width:140px;" onchange="AdminProducts.applyFilters()">
                                    <option value="">All Statuses</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                <button class="btn btn-outline" onclick="AdminProducts.applyFilters()">Filter</button>
                                <button class="btn btn-outline" onclick="AdminProducts.resetFilters()">Reset</button>
                            </div>
                            <div>
                                <button class="btn btn-primary" onclick="AdminProducts.openCreateModal()">+ Add Handbag</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Product Silhouette</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock & Variants</th>
                                        <th>Tag</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="products-tbody"></tbody>
                            </table>
                        </div>
                        <div id="products-pagination" class="pagination-wrap"></div>
                    </div>
                </section>

                <!-- TAB 4: INVENTORY -->
                <section id="tab-inventory" class="tab-view hidden">
                    <div class="card">
                        <div class="filter-bar">
                            <div class="filter-left">
                                <div class="search-input-wrap">
                                    <span class="icon">🔍</span>
                                    <input type="text" id="inventory-search-input" class="form-control" placeholder="Search SKU, Product, Color..." onkeyup="if(event.key==='Enter') AdminInventory.applyFilters()" />
                                </div>
                                <select id="inventory-status-filter" class="form-control" style="width:180px;" onchange="AdminInventory.applyFilters()">
                                    <option value="">All Stock Levels</option>
                                    <option value="in_stock">In Stock (> 15)</option>
                                    <option value="low_stock">Low Stock (1 - 15)</option>
                                    <option value="out_of_stock">Out of Stock (0)</option>
                                </select>
                                <button class="btn btn-outline" onclick="AdminInventory.applyFilters()">Filter</button>
                                <button class="btn btn-outline" onclick="AdminInventory.resetFilters()">Reset</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Product Silhouette</th>
                                        <th>SKU</th>
                                        <th>Color Variant</th>
                                        <th>Unit Price</th>
                                        <th>Current Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="inventory-tbody"></tbody>
                            </table>
                        </div>
                        <div id="inventory-pagination" class="pagination-wrap"></div>
                    </div>
                </section>

                <!-- TAB 5: CATEGORIES -->
                <section id="tab-categories" class="tab-view hidden">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Product Categories</div>
                            <button class="btn btn-primary" onclick="AdminCategories.openCreateModal()">+ Add Category</button>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Slug</th>
                                        <th>Products</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="categories-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- TAB 6: COUPONS -->
                <section id="tab-coupons" class="tab-view hidden">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Promotional Privileges & Coupons</div>
                            <button class="btn btn-primary" onclick="AdminCoupons.openCreateModal()">+ Create Coupon</button>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Promo Code</th>
                                        <th>Discount</th>
                                        <th>Min Order</th>
                                        <th>Max Cap</th>
                                        <th>Usage</th>
                                        <th>Expires</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="coupons-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- TAB 7: LOOKBOOK -->
                <section id="tab-lookbook" class="tab-view hidden">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Street Style Lookbook Editorial Cards</div>
                            <button class="btn btn-primary" onclick="AdminLookbook.openCreateModal()">+ Add Lookbook Card</button>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Client Name</th>
                                        <th>City Profile</th>
                                        <th>Featured Bag</th>
                                        <th>Quote Highlight</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="lookbook-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- TAB 8: PINCODES -->
                <section id="tab-pincodes" class="tab-view hidden">
                    <div class="card">
                        <div class="filter-bar">
                            <div class="filter-left">
                                <div class="search-input-wrap">
                                    <span class="icon">🔍</span>
                                    <input type="text" id="pincodes-search-input" class="form-control" placeholder="Search 6-digit PIN or City..." onkeyup="AdminPincodes.search(this.value)" />
                                </div>
                            </div>
                            <div>
                                <button class="btn btn-primary" onclick="AdminPincodes.openCreateModal()">+ Add PIN Code</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>PIN Code</th>
                                        <th>City</th>
                                        <th>State</th>
                                        <th>Delivery Timeline</th>
                                        <th>COD Status</th>
                                        <th>Serviceability</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pincodes-tbody"></tbody>
                            </table>
                        </div>
                        <div id="pincodes-pagination" class="pagination-wrap"></div>
                    </div>
                </section>

                <!-- TAB 9: NEWSLETTER -->
                <section id="tab-newsletter" class="tab-view hidden">
                    <div class="card">
                        <div class="filter-bar">
                            <div class="filter-left">
                                <div class="search-input-wrap">
                                    <span class="icon">🔍</span>
                                    <input type="text" id="newsletter-search-input" class="form-control" placeholder="Search email..." onkeyup="AdminNewsletter.search(this.value)" />
                                </div>
                            </div>
                            <div>
                                <button class="btn btn-outline" onclick="AdminNewsletter.exportCsv()">📥 Export CSV</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Subscriber Email</th>
                                        <th>Signup Source</th>
                                        <th>Status</th>
                                        <th>Date Subscribed</th>
                                    </tr>
                                </thead>
                                <tbody id="newsletter-tbody"></tbody>
                            </table>
                        </div>
                        <div id="newsletter-pagination" class="pagination-wrap"></div>
                    </div>
                </section>

                <!-- TAB 10: ADMIN USERS -->
                <section id="tab-admins" class="tab-view hidden">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Backoffice User Accounts & Roles</div>
                            <button class="btn btn-primary" onclick="AdminUsers.openCreateModal()">+ Create User</button>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Last Login</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="admins-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- TAB 11: AUDIT LOGS -->
                <section id="tab-audit" class="tab-view hidden">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Administrative Activity Audit Logs</div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Admin User</th>
                                        <th>Action</th>
                                        <th>Entity</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody id="audit-tbody"></tbody>
                            </table>
                        </div>
                        <div id="audit-pagination" class="pagination-wrap"></div>
                    </div>
                </section>

                <!-- TAB 12: PROFILE -->
                <section id="tab-profile" class="tab-view hidden">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:24px;">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Account Profile</div>
                            </div>
                            <div style="line-height:2.2; font-size:13px;">
                                <div><span class="text-muted">Username:</span> <strong id="profile-username-val">-</strong></div>
                                <div><span class="text-muted">Email Address:</span> <strong id="profile-email-val">-</strong></div>
                                <div><span class="text-muted">Assigned Role:</span> <span class="badge badge-primary" id="profile-role-val">-</span></div>
                                <div><span class="text-muted">Last Activity:</span> <span id="profile-last-login-val">-</span></div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Change Password</div>
                            </div>
                            <form id="form-password-change">
                                <div class="form-group">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" id="pw-current" class="form-control" required />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">New Password (min 8 chars)</label>
                                    <input type="password" id="pw-new" class="form-control" minlength="8" required />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" id="pw-confirm" class="form-control" minlength="8" required />
                                </div>
                                <button type="submit" id="pw-submit-btn" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- =======================================================================
         3. MODALS
         ======================================================================= -->

    <!-- Order Detail Modal -->
    <div id="modal-order-detail" class="modal-backdrop">
        <div class="modal-box" style="max-width:800px;">
            <div class="modal-header">
                <div class="modal-title">Order Details: <span id="modal-order-number-title" class="font-bold"></span></div>
                <button class="modal-close" onclick="App.closeModal('modal-order-detail')">&times;</button>
            </div>
            <div class="modal-body" id="order-detail-content"></div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="App.closeModal('modal-order-detail')">Close</button>
            </div>
        </div>
    </div>

    <!-- Order Status Transition Modal -->
    <div id="modal-order-status" class="modal-backdrop">
        <div class="modal-box" style="max-width:440px;">
            <div class="modal-header">
                <div class="modal-title">Update Status <span id="status-order-num-label"></span></div>
                <button class="modal-close" onclick="App.closeModal('modal-order-status')">&times;</button>
            </div>
            <form id="form-order-status">
                <input type="hidden" id="status-order-id" />
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Fulfillment Progression</label>
                        <select id="status-order-select" class="form-control">
                            <option value="placed">Placed (Order Received)</option>
                            <option value="processing">Processing (Atelier Packaging)</option>
                            <option value="shipped">Shipped (In Transit)</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Status</label>
                        <select id="status-payment-select" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-order-status')">Cancel</button>
                    <button type="submit" id="status-submit-btn" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Product Form Modal (Add / Edit) -->
    <div id="modal-product-form" class="modal-backdrop">
        <div class="modal-box" style="max-width:820px;">
            <div class="modal-header">
                <div class="modal-title" id="product-modal-title">Add New Handbag Silhouette</div>
                <button class="modal-close" onclick="App.closeModal('modal-product-form')">&times;</button>
            </div>
            <form id="form-product">
                <input type="hidden" id="product-form-id" />
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Product Name *</label>
                            <input type="text" id="product-form-name" class="form-control" placeholder="e.g. Verona Tote" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">URL Slug *</label>
                            <input type="text" id="product-form-slug" class="form-control" placeholder="verona-tote" required />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select id="product-form-category" class="form-control" required></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tag / Badge</label>
                            <input type="text" id="product-form-tag" class="form-control" placeholder="e.g. Best Seller, Trending" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Price (INR ₹) *</label>
                            <input type="number" id="product-form-price" class="form-control" step="0.01" min="0" placeholder="2499.00" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Compare-at Price (INR ₹)</label>
                            <input type="number" id="product-form-compare" class="form-control" step="0.01" min="0" placeholder="2999.00" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Editorial Description *</label>
                        <textarea id="product-form-desc" class="form-control" rows="3" placeholder="Handcrafted architectural tote in full-grain leather..." required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Dimensions</label>
                            <input type="text" id="product-form-dimensions" class="form-control" placeholder="38cm (W) × 30cm (H) × 14cm (D)" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Weight</label>
                            <input type="text" id="product-form-weight" class="form-control" placeholder="680 grams" />
                        </div>
                    </div>

                    <!-- Color Variants Section -->
                    <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--color-border);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <label class="form-label" style="margin-bottom:0;">Color Variants & Initial Stock *</label>
                            <button type="button" class="btn btn-outline btn-sm" onclick="AdminProducts.addVariantRow()">+ Add Color Variant</button>
                        </div>
                        <div id="product-variants-builder"></div>
                    </div>

                    <!-- Specifications / Features Section -->
                    <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--color-border);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <label class="form-label" style="margin-bottom:0;">Feature Bullet Highlights</label>
                            <button type="button" class="btn btn-outline btn-sm" onclick="AdminProducts.addFeatureRow()">+ Add Bullet Spec</button>
                        </div>
                        <div id="product-features-builder"></div>
                    </div>

                    <!-- Images Section -->
                    <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--color-border);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <label class="form-label" style="margin-bottom:0;">Product Imagery</label>
                            <div style="display:flex; gap:8px;">
                                <label class="btn btn-outline btn-sm" style="margin-bottom:0; cursor:pointer;">
                                    📁 Upload Image File
                                    <input type="file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="AdminProducts.handleImageUploadInput(this)" />
                                </label>
                                <button type="button" class="btn btn-outline btn-sm" onclick="AdminProducts.addImageRow()">+ Add Image URL</button>
                            </div>
                        </div>
                        <div id="product-images-builder"></div>
                    </div>

                    <div style="margin-top:16px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="product-form-active" checked /> Active on Customer Storefront
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-product-form')">Cancel</button>
                    <button type="submit" id="product-submit-btn" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Adjust Modal -->
    <div id="modal-inventory-adjust" class="modal-backdrop">
        <div class="modal-box" style="max-width:440px;">
            <div class="modal-header">
                <div class="modal-title">Adjust Variant Stock</div>
                <button class="modal-close" onclick="App.closeModal('modal-inventory-adjust')">&times;</button>
            </div>
            <form id="form-inventory-adjust">
                <input type="hidden" id="adjust-variant-id" />
                <div class="modal-body">
                    <div style="padding:12px; background:#fbf9f7; border-radius:var(--radius-sm); margin-bottom:16px; font-size:13px;">
                        <div>SKU: <strong id="adjust-sku-label">-</strong></div>
                        <div>Current Stock: <strong id="adjust-current-stock">0</strong> units</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Adjustment Amount (+ or -) *</label>
                        <input type="number" id="adjust-amount" class="form-control" placeholder="+10 or -5" required oninput="AdminInventory.updatePreview()" />
                        <div class="text-xs text-muted mt-1">New stock will be: <strong id="adjust-new-stock-preview">0</strong> units</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason for Adjustment *</label>
                        <select id="adjust-reason" class="form-control" required>
                            <option value="">Select a reason...</option>
                            <option value="New atelier stock arrival">New atelier stock arrival</option>
                            <option value="Damaged/defective stock write-off">Damaged/defective stock write-off</option>
                            <option value="Physical warehouse audit count">Physical warehouse audit count</option>
                            <option value="Returned item restocked">Returned item restocked</option>
                            <option value="Sample / Showroom allocation">Sample / Showroom allocation</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-inventory-adjust')">Cancel</button>
                    <button type="submit" id="adjust-submit-btn" class="btn btn-primary">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Category Modal -->
    <div id="modal-category-form" class="modal-backdrop">
        <div class="modal-box" style="max-width:440px;">
            <div class="modal-header">
                <div class="modal-title" id="category-modal-title">Category</div>
                <button class="modal-close" onclick="App.closeModal('modal-category-form')">&times;</button>
            </div>
            <form id="form-category">
                <input type="hidden" id="category-form-id" />
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Category Name *</label>
                        <input type="text" id="category-form-name" class="form-control" placeholder="e.g. Shoulder Bags" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug *</label>
                        <input type="text" id="category-form-slug" class="form-control" placeholder="shoulder" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea id="category-form-desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="category-form-active" checked /> Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-category-form')">Cancel</button>
                    <button type="submit" id="category-submit-btn" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Coupon Modal -->
    <div id="modal-coupon-form" class="modal-backdrop">
        <div class="modal-box" style="max-width:500px;">
            <div class="modal-header">
                <div class="modal-title" id="coupon-modal-title">Create Coupon</div>
                <button class="modal-close" onclick="App.closeModal('modal-coupon-form')">&times;</button>
            </div>
            <form id="form-coupon">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Coupon Code *</label>
                            <input type="text" id="coupon-form-code" class="form-control" placeholder="e.g. FESTIVE15" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Discount Type *</label>
                            <select id="coupon-form-type" class="form-control">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Discount Value *</label>
                            <input type="number" id="coupon-form-value" class="form-control" placeholder="15" step="0.01" min="0.01" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Min Order Value (₹)</label>
                            <input type="number" id="coupon-form-min" class="form-control" placeholder="2000" min="0" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Max Discount Cap (₹)</label>
                            <input type="number" id="coupon-form-cap" class="form-control" placeholder="Optional cap" min="0" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Usage Limit</label>
                            <input type="number" id="coupon-form-limit" class="form-control" placeholder="Optional total limit" min="1" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="datetime-local" id="coupon-form-expires" class="form-control" />
                    </div>

                    <div>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="coupon-form-active" checked /> Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-coupon-form')">Cancel</button>
                    <button type="submit" id="coupon-submit-btn" class="btn btn-primary">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lookbook Modal -->
    <div id="modal-lookbook-form" class="modal-backdrop">
        <div class="modal-box" style="max-width:520px;">
            <div class="modal-header">
                <div class="modal-title" id="lookbook-modal-title">Lookbook Card</div>
                <button class="modal-close" onclick="App.closeModal('modal-lookbook-form')">&times;</button>
            </div>
            <form id="form-lookbook">
                <input type="hidden" id="lookbook-form-id" />
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City Key (e.g. mumbai) *</label>
                            <input type="text" id="lookbook-form-city-key" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">City Profile Title *</label>
                            <input type="text" id="lookbook-form-city-title" class="form-control" placeholder="Mumbai • Bandra West" required />
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Client Name *</label>
                            <input type="text" id="lookbook-form-person-name" class="form-control" placeholder="Ananya S." required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Designation / Title</label>
                            <input type="text" id="lookbook-form-person-title" class="form-control" placeholder="Architect" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Featured Bag *</label>
                        <select id="lookbook-form-product" class="form-control" required></select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Photo Image URL *</label>
                        <input type="url" id="lookbook-form-image" class="form-control" placeholder="https://..." required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quote *</label>
                        <textarea id="lookbook-form-quote" class="form-control" rows="2" placeholder="The craftsmanship transitions seamlessly from client reviews to gallery openings." required></textarea>
                    </div>
                    <div>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="lookbook-form-active" checked /> Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-lookbook-form')">Cancel</button>
                    <button type="submit" id="lookbook-submit-btn" class="btn btn-primary">Save Lookbook Card</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pincode Modal -->
    <div id="modal-pincode-form" class="modal-backdrop">
        <div class="modal-box" style="max-width:440px;">
            <div class="modal-header">
                <div class="modal-title" id="pincode-modal-title">PIN Code Serviceability</div>
                <button class="modal-close" onclick="App.closeModal('modal-pincode-form')">&times;</button>
            </div>
            <form id="form-pincode">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">6-digit PIN Code *</label>
                        <input type="text" id="pincode-form-code" class="form-control" maxlength="6" placeholder="560034" required />
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City *</label>
                            <input type="text" id="pincode-form-city" class="form-control" placeholder="Bengaluru" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label">State *</label>
                            <input type="text" id="pincode-form-state" class="form-control" placeholder="Karnataka" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimated Delivery (Days) *</label>
                        <input type="number" id="pincode-form-days" class="form-control" value="2" min="1" max="10" required />
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="pincode-form-cod" checked /> Cash on Delivery (COD) Available
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="pincode-form-serviceable" checked /> Serviceable Delivery Zone
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-pincode-form')">Cancel</button>
                    <button type="submit" id="pincode-submit-btn" class="btn btn-primary">Save PIN Code</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Admin User Modal -->
    <div id="modal-admin-user-form" class="modal-backdrop">
        <div class="modal-box" style="max-width:440px;">
            <div class="modal-header">
                <div class="modal-title" id="admin-user-modal-title">Admin Account</div>
                <button class="modal-close" onclick="App.closeModal('modal-admin-user-form')">&times;</button>
            </div>
            <form id="form-admin-user">
                <input type="hidden" id="admin-user-form-id" />
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" id="admin-user-form-username" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" id="admin-user-form-email" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" id="admin-user-form-password" class="form-control" placeholder="Leave blank to keep unchanged" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select id="admin-user-form-role" class="form-control">
                            <option value="staff">Staff (Orders & Inventory)</option>
                            <option value="manager">Manager (Catalog, Orders, Marketing)</option>
                            <option value="admin">Administrator (Full Access)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" id="admin-user-form-active" checked /> Active Account
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-admin-user-form')">Cancel</button>
                    <button type="submit" id="admin-user-submit-btn" class="btn btn-primary">Save Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Dialog -->
    <div id="modal-confirm-dialog" class="modal-backdrop">
        <div class="modal-box" style="max-width:380px;">
            <div class="modal-header">
                <div class="modal-title">Confirm Action</div>
                <button class="modal-close" onclick="App.closeModal('modal-confirm-dialog')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="dialog-confirm-message" style="font-size:13px; color:var(--color-primary); line-height:1.6;">Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-confirm-dialog')">Cancel</button>
                <button type="button" id="dialog-confirm-btn" class="btn btn-danger">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Scripts -->
    <script src="assets/js/api.js"></script>
    <script src="assets/js/auth.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/orders.js"></script>
    <script src="assets/js/products.js"></script>
    <script src="assets/js/inventory.js"></script>
    <script src="assets/js/categories.js"></script>
    <script src="assets/js/coupons.js"></script>
    <script src="assets/js/lookbook.js"></script>
    <script src="assets/js/pincodes.js"></script>
    <script src="assets/js/newsletter.js"></script>
    <script src="assets/js/admins.js"></script>
    <script src="assets/js/profile.js"></script>
    <script src="assets/js/audit.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
