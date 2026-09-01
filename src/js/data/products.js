/**
 * BALENTO Product Catalog Data Model
 * 5 Core Handcrafted Silhouettes with Rich Specifications
 */
const PRODUCTS = [
    {
        id: "verona-tote",
        name: "Verona Tote",
        category: "tote",
        price: 2499,
        description: "Spacious architectural tote with a padded 14-inch laptop compartment, key leash, and reinforced leather shoulder drop.",
        dimensions: "38cm (W) × 30cm (H) × 14cm (D)",
        weight: "680 grams",
        images: {
            primary: "https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=900&q=80",
            hover: "https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=900&q=80"
        },
        colors: [
            { name: "Black", hex: "#1c1b1b" },
            { name: "Cognac", hex: "#8B5A2B" },
            { name: "Coffee Brown", hex: "#4A3B32" }
        ],
        features: ["14\" Laptop Sleeve", "Magnetic Closure", "Water-resistant Lining", "Key Leash"],
        tag: "Best Seller"
    },
    {
        id: "elara-shoulder",
        name: "Elara Shoulder",
        category: "shoulder",
        price: 2299,
        description: "Sculptural crescent shoulder bag with a fluid silhouette, buttery soft hand feel, and smooth magnetic closure.",
        dimensions: "28cm (W) × 18cm (H) × 8cm (D)",
        weight: "420 grams",
        images: {
            primary: "https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?auto=format&fit=crop&w=900&q=80",
            hover: "https://images.unsplash.com/photo-1591561954557-26941169b49e?auto=format&fit=crop&w=900&q=80"
        },
        colors: [
            { name: "Black", hex: "#1c1b1b" },
            { name: "Cognac", hex: "#8B5A2B" },
            { name: "Coffee Brown", hex: "#4A3B32" }
        ],
        features: ["Ergonomic Shoulder Strap", "Interior Card Organizer", "Custom Brass Trim"],
        tag: "Trending"
    },
    {
        id: "cora-crossbody",
        name: "Cora Crossbody",
        category: "crossbody",
        price: 2099,
        description: "Clean, hands-free daily essential with an adjustable strap, dual internal card organizers, and quick-access back slip pocket.",
        dimensions: "22cm (W) × 16cm (H) × 6cm (D)",
        weight: "360 grams",
        images: {
            primary: "https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?auto=format&fit=crop&w=900&q=80",
            hover: "https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=80"
        },
        colors: [
            { name: "Black", hex: "#1c1b1b" },
            { name: "Cognac", hex: "#8B5A2B" },
            { name: "Coffee Brown", hex: "#4A3B32" }
        ],
        features: ["Adjustable Crossbody Strap", "Secure Zipper Pocket", "Scratch-resistant Grain"],
        tag: "Essential"
    },
    {
        id: "alba-hobo",
        name: "Alba Hobo",
        category: "hobo",
        price: 2399,
        description: "Relaxed slouch silhouette crafted from ultra-supple nappa leather with generous internal capacity and comfortable carry.",
        dimensions: "34cm (W) × 26cm (H) × 12cm (D)",
        weight: "510 grams",
        images: {
            primary: "https://images.unsplash.com/photo-1544816155-12df9643f363?auto=format&fit=crop&w=900&q=80",
            hover: "https://images.unsplash.com/photo-1575032617751-6ddec2089882?auto=format&fit=crop&w=900&q=80"
        },
        colors: [
            { name: "Black", hex: "#1c1b1b" },
            { name: "Cognac", hex: "#8B5A2B" },
            { name: "Coffee Brown", hex: "#4A3B32" }
        ],
        features: ["Ultra-soft Supple Leather", "Wide Slouch Profile", "Dual Magnetic Clasp"],
        tag: "Editor's Pick"
    },
    {
        id: "mira-structured",
        name: "Mira Structured",
        category: "structured",
        price: 2499,
        description: "Architectural top-handle bag with a detachable crossbody strap, structured accordion gussets, and gold-tone protective base feet.",
        dimensions: "26cm (W) × 20cm (H) × 10cm (D)",
        weight: "580 grams",
        images: {
            primary: "https://images.unsplash.com/photo-1594223274512-ad4803739b7c?auto=format&fit=crop&w=900&q=80",
            hover: "https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=900&q=80"
        },
        colors: [
            { name: "Black", hex: "#1c1b1b" },
            { name: "Cognac", hex: "#8B5A2B" },
            { name: "Coffee Brown", hex: "#4A3B32" }
        ],
        features: ["Dual Structured Top Handles", "Detachable Long Strap", "Protective Metal Feet"],
        tag: "New"
    }
];
