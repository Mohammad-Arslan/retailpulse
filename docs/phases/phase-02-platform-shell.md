# Phase 2 — Platform Shell & Design System

**SRS Reference:** §2 (Frontend stack), §4.7 UX & Accessibility, §4.8 Extensibility (i18n prep)  
**Status:** Complete  
**Depends on:** Phase 1  
**Blocks:** All admin UIs from Phase 3 onward

---

## Objective

Upgrade Phase 1 admin screens and **Breeze auth pages** (`Pages/Auth/*`) into a cohesive **enterprise admin shell**: React 19, Inertia 2, shadcn/ui, Tailwind CSS 4, responsive sidebar layout, breadcrumbs, toasts, and a **command palette (⌘K)** skeleton.

## Deliverables

- shadcn/ui initialized with Tailwind 4 tokens (brand colors, typography)
- `AdminLayout` with collapsible sidebar, header (user menu, logout), branch switcher placeholder
- Data tables with sorting, pagination, row context menu (View / Edit / Delete)
- Global `<Toaster />`, form validation display (Inertia errors)
- `can()` hook wired to Phase 1 permissions for menu visibility
- `react-i18next` scaffold (en only; strings externalized)
- WCAG 2.1 AA: focus rings, aria labels on icon buttons

## Out of Scope

- Business data (branches, products)
- Real-time (Reverb)

## Acceptance Criteria

1. All Phase 1 pages render inside `AdminLayout` with consistent styling.
2. Sidebar items hidden when user lacks permission.
3. Command palette opens with Ctrl+K and lists navigable admin routes.
4. Lighthouse accessibility score ≥ 90 on admin dashboard.
