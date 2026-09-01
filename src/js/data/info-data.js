/**
 * BALENTO Customer Care & Information Modal Content
 */
const INFO_CONTENT = {
    care: {
        title: "Materials & Care Guide",
        html: `
            <p>Every BALENTO bag is crafted from hand-selected full-grain and fine nappa leathers that develop a distinct, rich patina over time.</p>
            <h4 class="font-headline-sm text-on-surface uppercase text-[16px] mt-4 mb-1">Care Recommendations:</h4>
            <ul class="list-disc pl-5 space-y-2">
                <li>Store your bag in the provided breathable cotton dust bag when not in use.</li>
                <li>Avoid prolonged exposure to intense direct sunlight or moisture.</li>
                <li>If exposed to rain, gently pat dry with a soft microfiber cloth and let air dry naturally.</li>
                <li>Apply a neutral leather conditioner once every six months to nourish the grain.</li>
            </ul>
        `
    },
    shipping: {
        title: "Shipping & Return Policy",
        html: `
            <p>We provide <strong>Complimentary Express Delivery</strong> across India on all orders above ₹2,000.</p>
            <ul class="list-disc pl-5 space-y-2 mt-3">
                <li><strong>Metro Cities (Mumbai, Delhi NCR, Bengaluru):</strong> Delivered in 2–3 business days.</li>
                <li><strong>Rest of India:</strong> Delivered in 4–5 business days.</li>
                <li><strong>Complimentary 7-Day Returns:</strong> If you are not completely delighted with your purchase, we arrange doorstep pickup and 100% full refund with zero questions asked.</li>
            </ul>
        `
    },
    track: {
        title: "Track Your Shipment",
        html: `
            <p>Enter your 10-digit phone number or BALENTO order reference to track live delivery status.</p>
            <div class="flex gap-2 my-4">
                <input type="text" placeholder="e.g. BAL-2026-8921" class="flex-1 bg-surface-container-lowest border border-outline-variant p-3 text-body-md focus:border-on-surface focus:outline-none"/>
                <button onclick="showToast('Shipment status: Out for Delivery with Bluedart Air')" class="bg-on-surface text-surface font-label-caps text-[11px] uppercase px-4">Track</button>
            </div>
        `
    },
    faq: {
        title: "Frequently Asked Questions",
        html: `
            <div class="space-y-4">
                <div>
                    <h4 class="font-label-caps text-[13px] text-on-surface uppercase font-semibold">Do your totes fit a 14-inch laptop?</h4>
                    <p class="mt-1">Yes, the Verona Tote is engineered with a dedicated padded sleeve that snugly accommodates up to a 14" MacBook Pro or equivalent laptop.</p>
                </div>
                <div>
                    <h4 class="font-label-caps text-[13px] text-on-surface uppercase font-semibold">Is the leather 100% genuine?</h4>
                    <p class="mt-1">Yes, all BALENTO bags are created from 100% ethically sourced genuine leather with custom brushed brass hardware.</p>
                </div>
                <div>
                    <h4 class="font-label-caps text-[13px] text-on-surface uppercase font-semibold">What payment modes do you support?</h4>
                    <p class="mt-1">We support all UPI applications (GPay, PhonePe, Paytm), Credit & Debit Cards, NetBanking, and Cash on Delivery.</p>
                </div>
            </div>
        `
    },
    sustainability: {
        title: "Our Sustainability Commitment",
        html: `
            <p>At BALENTO, sustainability is rooted in timeless durability. By creating enduring bags that last years rather than seasons, we counter fast fashion waste.</p>
            <p class="mt-2">Our leather tanneries adhere strictly to LWG (Leather Working Group) certified ethical standards, utilizing non-toxic dyes and zero single-use plastics in our packaging.</p>
        `
    },
    careers: {
        title: "Join The Balento Team",
        html: `
            <p>We are always looking for passionate designers, digital storytellers, and logistics innovators based in Bengaluru and Mumbai.</p>
            <p class="mt-2">Send your portfolio and resume to <strong class="text-on-surface">careers@balento.com</strong>.</p>
        `
    },
    privacy: {
        title: "Privacy Policy",
        html: `
            <p>BALENTO respects your personal privacy. We never sell or distribute your data to third-party brokers. Personal information collected during checkout is encrypted and used exclusively for shipment fulfillment and transactional communication.</p>
        `
    },
    terms: {
        title: "Terms of Service",
        html: `
            <p>By using this website, you agree to our standard terms of service. All editorial photography, branding, and bag silhouettes are proprietary to BALENTO India Pvt Ltd.</p>
        `
    },
    contact: {
        title: "Contact Concierge",
        html: `
            <p>Our dedicated client care team is available Monday to Saturday, 9:00 AM – 7:00 PM IST.</p>
            <p class="mt-2"><strong>Email:</strong> care@balento.com<br/><strong>WhatsApp / Phone:</strong> +91 (080) 4129-8400<br/><strong>Studio:</strong> Koramangala 4th Block, Bengaluru, KA 560034</p>
        `
    }
};
