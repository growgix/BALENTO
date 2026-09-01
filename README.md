# BALENTO | Accessible Premium Everyday Leather Bags

A high-performance editorial e-commerce experience architected for **BALENTO** — an Indian accessible quiet luxury leather bag label (₹2,000–₹2,500 target price).

---

## 📁 Modular Directory Structure

```text
stitch_balento_e_commerce_editorial_homepage/
├── index.html                  # Semantic HTML entry point linking modular assets
├── code.html                   # Standalone self-contained distribution bundle
├── README.md                   # Project documentation & folder guide
├── DESIGN.md                   # Brand tokens, colors, typography, & layout rules
│
├── assets/
│   ├── css/
│   │   └── styles.css          # Custom animations, transitions, & scrollbar styling
│   └── images/
│       └── screen.png          # Reference screenshots & static media assets
│
└── src/
    └── js/
        ├── main.js             # Application bootstrap & global event listeners
        ├── data/
        │   ├── products.js     # 5 Core Balento products catalog, specs, & imagery
        │   ├── lookbook.js     # Street style gallery data (Bengaluru, Mumbai, Delhi, Goa)
        │   ├── info-data.js    # Materials & Care, Shipping, FAQ, Sustainability modal texts
        │   └── color-story.js  # Tonal shade descriptions & palette info
        └── modules/
            ├── cart.js         # Slide-out Bag drawer, ₹2,000 Shipping bar, & Gifting note
            ├── wishlist.js     # Wishlist drawer & LocalStorage persistence
            ├── quick-view.js   # Product Quick View Lightbox & Pincode Delivery Estimator
            ├── monogram.js     # Bespoke Monogramming Atelier live foil customizer
            ├── search.js       # Live collection search modal & quick tag filters
            └── checkout.js     # Express 1-Step Checkout modal, promo validation, & orders
```

---

## 🚀 Key Features

1. **Editorial Architecture**:
   - 18 structured sections including *Silhouette Comparison Guide*, *Bespoke Monogramming Atelier*, *Critical Press Acclaim*, and *Styled By You Street Style Gallery*.
2. **Performance First**:
   - Zero external framework overhead beyond Tailwind CDN + Google Fonts.
   - LCP hero image prioritization via `fetchpriority="high"` and `<link rel="preload">`.
   - Layout stability (0 Cumulative Layout Shift) using explicit aspect ratio containers.
3. **Interactive E-Commerce Suite**:
   - **Slide-out Cart Drawer** with ₹2,000 Complimentary Shipping threshold bar & gift message note.
   - **Live Pincode Delivery Estimator** inside Quick View lightbox.
   - **Wishlist Drawer** with `localStorage` persistence.
   - **Express 1-Step Checkout Modal** with promo code validation (`WELCOME10`).
   - **Live Search Overlay** (`Cmd/Ctrl + K` or `Escape`).
   - **Floating Sticky Quick Add Bar** on scroll.

---

## 🛠️ Development & Running Locally

Simply open `index.html` in any modern web browser or serve via any static HTTP server:

```bash
# Option 1: Using Python
python -m http.server 3000

# Option 2: Using Node
npx serve .
```
