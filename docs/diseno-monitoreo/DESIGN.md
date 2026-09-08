---
name: Obsidian Nexus
colors:
  surface: '#051424'
  surface-dim: '#051424'
  surface-bright: '#2c3a4c'
  surface-container-lowest: '#010f1f'
  surface-container-low: '#0d1c2d'
  surface-container: '#122131'
  surface-container-high: '#1c2b3c'
  surface-container-highest: '#273647'
  on-surface: '#d4e4fa'
  on-surface-variant: '#c6c6cd'
  inverse-surface: '#d4e4fa'
  inverse-on-surface: '#233143'
  outline: '#909097'
  outline-variant: '#45464c'
  surface-tint: '#c1c6db'
  primary: '#c1c6db'
  on-primary: '#2a3040'
  primary-container: '#0b1120'
  on-primary-container: '#777c90'
  inverse-primary: '#585e70'
  secondary: '#5de6ff'
  on-secondary: '#00363e'
  secondary-container: '#00cbe6'
  on-secondary-container: '#00515d'
  tertiary: '#d0bcff'
  on-tertiary: '#3c0091'
  tertiary-container: '#170042'
  on-tertiary-container: '#8d5ef8'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#dde2f8'
  primary-fixed-dim: '#c1c6db'
  on-primary-fixed: '#151b2b'
  on-primary-fixed-variant: '#414658'
  secondary-fixed: '#a2eeff'
  secondary-fixed-dim: '#2fd9f4'
  on-secondary-fixed: '#001f25'
  on-secondary-fixed-variant: '#004e5a'
  tertiary-fixed: '#e9ddff'
  tertiary-fixed-dim: '#d0bcff'
  on-tertiary-fixed: '#23005c'
  on-tertiary-fixed-variant: '#5516be'
  background: '#051424'
  on-background: '#d4e4fa'
  surface-variant: '#273647'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.05em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  container-margin: 24px
  gutter: 16px
  sidebar-width: 280px
  card-padding: 20px
---

## Brand & Style

The design system targets high-stakes corporate infrastructure monitoring where precision, uptime, and rapid data synthesis are paramount. The personality is "Command & Control"—authoritative, futuristic, and impeccably organized.

The aesthetic blends **Corporate Modern** structure with **Glassmorphism** and **Cyberpunk** accents. It utilizes deep, dark surfaces to reduce eye strain during long shifts, punctuated by vibrant, glowing accents that draw immediate attention to critical status changes. UI elements should feel like holographic panels layered over a digital void, using light and transparency to establish a sophisticated technical hierarchy.

## Colors

The palette is anchored by **Deep Navy (#0B1120)**, serving as the primary canvas to provide maximum contrast for illuminated data points. 

- **Primary Canvas:** Deep Navy is used for all base surfaces.
- **Action & Focus:** **Electric Cyan (#22D3EE)** is the primary functional color, used for active states, primary buttons, and healthy status indicators.
- **Analytic Accent:** **Vivid Purple (#8B5CF6)** is used for data visualization, secondary trends, and premium feature callouts.
- **Functional Glows:** Semantic colors (Success, Warning, Danger) should utilize a 20% opacity background tint combined with a high-saturation border and a 5px outer glow to simulate an "active LED" effect.

## Typography

The system utilizes **Inter** for all primary interface elements to ensure maximum legibility and a professional, neutral tone. For technical readouts, IDs, and system logs, **JetBrains Mono** is introduced to provide a "developer-centric" and precise feel.

- **Headlines:** Should use tighter letter spacing and semi-bold weights to appear authoritative.
- **Labels:** Always use the monospaced font for data values and status badges to maintain consistent character widths in real-time updating displays.
- **Contrast:** Use Pure White (#FFFFFF) for primary headers and Slate-400 (#94A3B8) for secondary body text to maintain a clear information hierarchy.

## Layout & Spacing

The layout follows a **Fixed-Fluid Hybrid** model. A fixed-width left sidebar handles global navigation and high-level status, while the main content area utilizes a fluid 12-column grid for dashboard widgets.

- **Grid:** 12 columns with 16px gutters. Widgets should span 3, 4, 6, or 12 columns.
- **Rhythm:** All spacing (padding, margins) must be multiples of 4px.
- **Breakpoints:** 
  - Desktop: 1440px+ (Standard dashboard view)
  - Tablet: 768px - 1439px (Sidebar collapses to icons-only)
  - Mobile: Under 767px (Cards stack vertically, sidebar becomes a bottom sheet or hamburger menu)

## Elevation & Depth

Depth is created through **Translucent Layering** rather than traditional shadows. 

1. **Background:** Deep Navy (#0B1120).
2. **Layer 1 (Cards/Panels):** Background color with 60% opacity and a `backdrop-filter: blur(12px)`.
3. **Layer 2 (Popovers/Modals):** Background color with 80% opacity and a 1px border of Slate-700.
4. **Borders:** Every glass element requires a "Subtle Inner Glow" border—a 1px solid stroke with 10% white opacity on the top/left and 5% white opacity on the bottom/right.
5. **Glows:** Use `box-shadow: 0 0 15px [color]22` for active status indicators to simulate light emission.

## Shapes

The shape language is "Soft-Industrial." Elements use a consistent **4px (0.25rem)** corner radius to maintain a precise, technical look without the aggression of sharp 90-degree corners. 

- **Standard Elements:** 4px radius (Buttons, Input fields, Small cards).
- **Large Containers:** 8px radius (Main dashboard widgets, Map containers).
- **Status Pills:** Fully rounded (Pill-shaped) to distinguish them from interactive buttons.

## Components

### Buttons
- **Primary:** Electric Cyan background, Navy text. No border. On hover, add a 10px Cyan outer glow.
- **Secondary:** Transparent background, Electric Cyan 1px border.
- **Ghost:** Transparent background, Slate-400 text.

### Sidebars & Navigation
- Permanent left-hand rail. Use **Electric Cyan** for the active indicator—a vertical 3px bar on the far left of the active menu item.
- Include a "Pulse" indicator (small 8px circle) next to the "Alerts" menu item that slowly breathes between 40% and 100% opacity.

### List Cards & Service Items
- List items should have a 1px bottom border of Slate-800.
- Left-align a status icon (Square with 2px radius) using the semantic color palette.
- Hover state: Increase background opacity to 15% white to create a "highlight" effect.

### Input Fields
- Darkest navy background (#020617), 1px Slate-700 border. 
- Focus state: Border changes to Electric Cyan with a subtle inner glow.

### Map Container
- Use a custom Mapbox/Leaflet style with "Midnight" tiles.
- Pathing and data points should use **Vivid Purple** for historical data and **Electric Cyan** for real-time movement.
- Tooltips on the map must follow the Glassmorphism rules (blur + translucent background).