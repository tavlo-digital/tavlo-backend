# AGENTS.md

This file provides guidance to Codex (Codex.ai/code) when working with code in this repository.

## Rules

### API Documentation
After writing or changing an API endpoint, update the corresponding documentation file in the sibling `../tavlo-api-docs/` project:
- Customer API → `../tavlo-api-docs/customer-api.md`
- Vendor API → match the relevant file (e.g. `../tavlo-api-docs/orders-management-api.md`, `../tavlo-api-docs/menu-management-api.md`, etc.)

Document every new route: method, URL, auth requirement, request body, and example response.

### Testing
After implementing any new API function, run its tests:
```bash
php artisan test tests/Feature/Customer/   # for customer endpoints
php artisan test tests/Feature/            # full feature suite
```

### Database Changes
**Always create a new migration** when adding or modifying tables — never edit existing migration files. Name migrations descriptively, e.g. `php artisan make:migration add_status_to_orders_table`.

### Vendor Next.js Project
`vendor-nextjs/` is a separate Next.js project that consumes the vendor API. When a vendor API is added or changed, the frontend implementation in `vendor-nextjs/` must also be updated — instructions for each task will be provided.

## Architecture

### Backend — Laravel 12 REST API + Inertia SSR

**Three separate API surfaces**, each registered in `bootstrap/app.php` via `withRouting()`:

| Prefix | Route file | Guard | Actor |
|--------|-----------|-------|-------|
| `/api/customer/*` | `routes/api/customer.php` | `auth:customer` | `Customer` model |
| `/api/vendor/*` | `routes/api/vendor.php` | `auth:vendor` | `Vendor` model |
| `/api/admin/*` | `routes/api/admin.php` | `admin` middleware | `User` model |

All API routes return JSON. `Customer` and `Vendor` each have their own Sanctum guard defined in `config/auth.php` — tokens are scoped per model. The `User` model is for the admin panel only.

Controllers live under `app/Http/Controllers/Api/{Customer,Vendor}/`. There is no shared base controller beyond Laravel's default.

