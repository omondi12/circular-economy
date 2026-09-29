# Implementation Plan: Westport Industrial City Redesign

## 1. Product Summary & Scope
Deliver an exceptional, highly animated, and deeply engaging single-page marketing website for **Westport Industrial City — Circular Economy Platform**. The redesign preserves 100% of the real content, official institutional mapping statistics (668+ institutions, 47 counties, 24 ministries, 24 Relationship Managers in the field), verified contact endpoints, and brand identity, while dramatically elevating the aesthetic to an award-winning, interactive experience.

## 2. Serving & Architecture
- **Architecture**: Static frontend single-page application served via Vite / modern static delivery.
- **Routes**:
  - `/` (Home landing page)
  - `/manus-routes.json` (Required Webdev route manifest)
- **Deployment & Caching**:
  - Static build output in `dist/` with immutable hashed asset caching for JS/CSS/images.
  - Immediate HTML delivery with strict semantic markup and meta tags for search discoverability.

## 3. Visual & Animation Design System
- **Engine**: Pure vanilla modern JavaScript, CSS Custom Properties, Canvas 2D / SVG micro-interactions, and GSAP-grade cubic-bezier animation loops.
- **Components**:
  1. **Top Glass Navigation**: Translucent frosted glass bar, active section tracking, kinetic logo with continuous counter-rotating energy glyph, and quick-action platform CTA.
  2. **Hero Section**:
     - Punchy editorial typography with dynamic staggered reveal.
     - Dual-ring interactive Circular Flow visual with glowing orbital particle streams representing paper, metal, plastic, e-waste, and furniture flows.
     - Central counter displaying "668 Institutions" with an interactive hover breakdown.
     - High-impact stat rail with counting numbers and animated micro-sparklines.
  3. **Real-time Live Ticker / Marquee**: Infinite smooth scrolling tape showing nationwide coverage, real-time material recovery stats, and active county engagements.
  4. **What We Do (Operating Layer Showcase)**:
     - 4 interactive spotlight cards with 3D tilt response and custom vector icons.
     - Expanded details on institutional mapping, relationship manager field logging, sorted recovery, and public audit trails.
  5. **Interactive How It Works (Four-Step Flow)**:
     - 4-phase sequential flow with interactive step progression, animated connector lines, and visual timeline indicators.
     - Interactive material filter chips (Paper, Metal, Plastic, Furniture, E-Waste, Other) updating recovery pathways dynamically.
  6. **Interactive National Coverage Matrix**:
     - Visual map & dot-matrix grid with 168+ node cells representing the 24 ministries and 47 counties.
     - Interactive filter controls to illuminate ministries vs counties vs institutions.
     - Live inspection card showing detailed coverage metadata on hover.
  7. **Impact Calculator / Field Simulation**:
     - An engaging interactive module allowing visitors to select an institutional tier (e.g. County Department, National Ministry, State Corporation) and see estimated recoverable materials and operational milestones.
  8. **Call to Action & Footer**:
     - Magnetic buttons with reactive physics.
     - Direct mailto link preserved: `mailto:admin@affordablehousingmarketing.co.ke?subject=Partnership%20enquiry%20%E2%80%94%20Westport%20Industrial%20City`.
     - External platform link: `https://circular.affordablehousingmarketing.co.ke`.

## 4. Verification & QA Plan
- Verify HTTP response and clean console in local sandbox preview on configured port (3000).
- Confirm `/manus-routes.json` serves correct JSON.
- Verify visual aesthetics via `webdev.take_screenshot` (both desktop 1280x720 and mobile 375x812).
- Ensure reduced-motion media queries gracefully disable aggressive transforms while preserving full readability.
