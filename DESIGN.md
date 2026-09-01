---
name: Balento Visual Identity
colors:
  surface: '#fdf8f7'
  surface-dim: '#ddd9d8'
  surface-bright: '#fdf8f7'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f7f3f1'
  surface-container: '#f1edec'
  surface-container-high: '#ebe7e6'
  surface-container-highest: '#e5e2e0'
  on-surface: '#1c1b1b'
  on-surface-variant: '#474741'
  inverse-surface: '#313030'
  inverse-on-surface: '#f4f0ef'
  outline: '#787770'
  outline-variant: '#c8c7be'
  surface-tint: '#5f5e5b'
  primary: '#5f5e5b'
  on-primary: '#ffffff'
  primary-container: '#f5f2ed'
  on-primary-container: '#6f6e6a'
  inverse-primary: '#c9c6c2'
  secondary: '#605e5c'
  on-secondary: '#ffffff'
  secondary-container: '#e5e2df'
  on-secondary-container: '#666462'
  tertiary: '#695c52'
  on-tertiary: '#ffffff'
  tertiary-container: '#fff0e6'
  on-tertiary-container: '#7a6c61'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e5e2dd'
  primary-fixed-dim: '#c9c6c2'
  on-primary-fixed: '#1c1c19'
  on-primary-fixed-variant: '#474743'
  secondary-fixed: '#e5e2df'
  secondary-fixed-dim: '#c9c6c3'
  on-secondary-fixed: '#1c1c1a'
  on-secondary-fixed-variant: '#484744'
  tertiary-fixed: '#f2dfd2'
  tertiary-fixed-dim: '#d5c3b7'
  on-tertiary-fixed: '#231a12'
  on-tertiary-fixed-variant: '#51443b'
  background: '#fdf8f7'
  on-background: '#1c1b1b'
  surface-variant: '#e5e2e0'
typography:
  display-lg:
    fontFamily: Bodoni Moda
    fontSize: 64px
    fontWeight: '400'
    lineHeight: '1.1'
    letterSpacing: -0.02em
  display-sm:
    fontFamily: Bodoni Moda
    fontSize: 40px
    fontWeight: '400'
    lineHeight: '1.2'
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Bodoni Moda
    fontSize: 32px
    fontWeight: '400'
    lineHeight: '1.3'
  headline-sm:
    fontFamily: Bodoni Moda
    fontSize: 24px
    fontWeight: '400'
    lineHeight: '1.4'
  body-lg:
    fontFamily: DM Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
    letterSpacing: 0.01em
  body-md:
    fontFamily: DM Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
    letterSpacing: 0.01em
  label-caps:
    fontFamily: DM Sans
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1'
    letterSpacing: 0.15em
  nav-link:
    fontFamily: DM Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1'
    letterSpacing: 0.05em
spacing:
  unit: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 48px
  xxl: 80px
  container-max: 1440px
  gutter: 24px
---

## Brand & Style

This design system embodies the "quiet luxury" ethos—an aesthetic defined by restraint, exceptional quality, and timeless sophistication. It is tailored for a high-fashion editorial context where the product is the protagonist, supported by a UI that feels like a curated gallery space.

The style is a blend of **High-Fashion Minimalism** and **Editorial Contrast**. It avoids the aggressive tropes of traditional e-commerce in favor of a boutique digital experience. The atmosphere is calm, airy, and feminine, achieved through a rhythmic use of whitespace and a rejection of heavy shadows or rounded corners. Every interaction should feel intentional and effortless, evoking the tactile sensation of fine leather and artisanal craftsmanship.

## Colors

The palette is rooted in organic, warm neutrals that mimic natural materials like linen, sand, and soft stone. 

- **Primary (#F5F2ED):** An oatmeal-inspired "soft white" used for the main background to reduce eye strain and provide a premium, paper-like feel.
- **Secondary (#3E3D3B):** A deep charcoal used for typography and iconography. We intentionally avoid pure black (#000000) to maintain a softer, more sophisticated contrast.
- **Tertiary (#C5B4A8):** A dusty rose/taupe used for subtle UI elements, hover states, or secondary categorizations.
- **Accent (#A8B2A7):** A muted sage green used sparingly for functional highlights, such as "In Stock" indicators or active selection states.

The color application should remain monochromatic where possible, using the accent colors only to draw the eye to specific, high-value actions.

## Typography

The typographic hierarchy relies on the dramatic contrast between the high-fashion serif and the utilitarian sans-serif.

- **Headlines:** `Bodoni Moda` is used for all editorial headings. Its extreme contrast between thick and thin strokes provides an immediate luxury feel. Large display sizes should use tight tracking to emphasize the verticality of the letterforms.
- **Body & UI:** `DM Sans` provides a clean, airy counterpoint. Its low-contrast, geometric structure ensures legibility at small sizes for product descriptions and navigation.
- **Labels:** Small labels and "Overlines" should always be set in uppercase `DM Sans` with generous letter spacing (0.15em) to create a sense of organized, architectural precision.

## Layout & Spacing

The layout philosophy follows a **Fixed-Fluid Hybrid Grid**. Content is housed within a 1440px max-width container, utilizing a 12-column grid for desktop and a 4-column grid for mobile.

- **Rhythm:** We use a base-4 unit system, but emphasize the larger increments (`xl` and `xxl`) to ensure the "generous whitespace" required by the brand. 
- **The "Breathe" Rule:** Every major section should be separated by at least `xxl` (80px) spacing. High-end fashion imagery requires room to "breathe" to avoid looking cluttered.
- **Alignment:** Typography is predominantly left-aligned or centered for editorial impact. Right-aligned elements are reserved strictly for utility navigation or price points.

## Elevation & Depth

This design system avoids traditional drop shadows to maintain a flat, modern-editorial aesthetic. Depth is communicated through:

- **Tonal Layering:** Using the slight shift between the neutral background (`#F5F2ED`) and pure white (`#FFFFFF`) surfaces to indicate hierarchy.
- **Razor-Thin Borders:** A 1px solid border in a slightly darker neutral tone (`#E5E2DD`) defines containers without adding visual weight.
- **Overlays:** Full-bleed image backgrounds with semi-transparent light overlays (70-90% opacity) for text legibility. 
- **Subtle Interactions:** Hover states do not lift elements; instead, they utilize a slight opacity shift or a delicate color fill change to the tertiary tone.

## Shapes

To maintain the architectural and sophisticated tone of the brand, all UI elements utilize **Sharp (0px)** corners. This includes buttons, input fields, product cards, and modal windows. 

Square edges convey a sense of structure, strength, and premium craftsmanship. This rigidity is balanced by the organic curves found within the product photography and the `Bodoni Moda` typeface.

## Components

### Buttons
- **Primary:** Sharp-edged, solid `Secondary` color (#3E3D3B) with `Neutral Surface` text. No icons unless necessary for flow.
- **Secondary/Ghost:** 1px solid border (#3E3D3B), no fill, text in the same color. 
- **Text Link:** `DM Sans` bold, 12px, uppercase with a 1px underline that disappears on hover.

### Input Fields
- Underline style only: A 1px solid bottom border. No bounding box.
- Labels are placed above the line in `label-caps` style.
- Focus state: The border-bottom thickens to 2px or changes to the `Accent` color.

### Product Cards
- No borders or shadows. The image takes up 100% of the card width.
- Product name in `DM Sans` (body-md), price in `Bodoni Moda` (headline-sm) below.
- Subtle "Quick Add" button appears only on hover.

### Navigation
- Global navigation is minimal. Use large, thin icons for Bag and Search.
- Desktop menu items are center-aligned with high tracking.
- Mobile menu uses a full-screen "Curtain" overlay in the `Primary` color.

### Chips/Filters
- Rectangular blocks with 1px borders. When active, they fill with the `Tertiary` color.