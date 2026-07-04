# Product Catalog — Provider Mapping Layer

PAY-020 introduces a provider-backed product catalog so checkout and fulfillment always use **validated VTPass service IDs and variation codes**. This prevents payment initialization with hardcoded frontend plan IDs that VTPass does not recognize (for example: `VARIATION CODE DOES NOT EXIST FOR SELECTED PRODUCT`).

---

## Why the catalog exists

| Problem | Catalog solution |
|---------|------------------|
| Frontend hardcoded data plan IDs | Plans loaded from `/api/v1/catalog/products` |
| Invalid variation at fulfillment | Checkout rejects unknown/inactive variations before Paystack |
| Ops debugging | Transaction detail shows provider, service_id, variation_code, plan name |

The catalog does **not** change Paystack logic, payment flow, or checkout UI layout.

---

## Data model

| Table | Purpose |
|-------|---------|
| `product_categories` | airtime, data, electricity |
| `provider_services` | VTPass service_id per network/disco |
| `provider_variations` | VTPass variation_code per data service |

Customer-facing display fields on `provider_variations` (PAY-020A):

| Field | Purpose |
|-------|---------|
| `display_name` | Clean checkout label (e.g. `500MB - 30 Days`) |
| `is_visible` | Whether plan appears in public checkout |
| `is_popular` | Optional badge in checkout |
| `sort_order` | Secondary sort after amount |
| `customer_category` | daily, weekly, monthly, yearly, voice, sme, corporate, unknown |
| `validity_label` | e.g. `30 Days` |
| `data_size_label` | e.g. `500MB` |
| `display_override` | When true, sync/classify preserves manual edits |

Baseline services are seeded by `ProductCatalogSeeder`. Data variations are synced from VTPass.

---

## Raw provider catalog vs customer-facing catalog

VTPass returns the **full** provider catalog (voice bundles, SME plans, long names, etc.). PAYLITY keeps every synced variation in `provider_variations` with the raw `name` and `raw_payload` unchanged for fulfillment and ops.

Checkout uses a **filtered customer-facing view**:

| Rule | Default visibility |
|------|-------------------|
| Voice / talk / Xtratalk / Xtradata / call / minutes | Hidden |
| SME / corporate / corporate gifting | Hidden |
| Amount > ₦50,000 | Hidden |
| Empty name or missing variation_code | Hidden |
| Normal data bundles (500MB, 1GB, 5GB, etc.) | Visible |

`display_name` is generated as `{data_size_label} - {validity_label}` when both can be extracted, otherwise the provider name is used.

---

## Reclassification command

After migration or when VTPass names change:

```bash
php artisan paylity:catalog-classify
```

Options:

- `--force-overrides` — overwrite variations with `display_override=true`

Prints: total processed, visible, hidden, voice hidden, SME/corporate hidden, enterprise hidden, unknown.

Recommended deploy sequence:

```bash
php artisan migrate --force
php artisan paylity:catalog-classify
php artisan paylity:catalog-sync vtpass
php artisan paylity:catalog-classify
php artisan optimize:clear
```

---

## Manual display overrides

Set `display_override=true` on a variation to preserve ops/manual edits to:

- `display_name`, `is_visible`, `is_popular`, `sort_order`
- `customer_category`, `validity_label`, `data_size_label`

Catalog sync and `paylity:catalog-classify` skip these rows unless `--force-overrides` is used. Raw `name`, `amount`, and `raw_payload` still update from VTPass on sync.

---

## VTPass variation sync

Command:

```bash
php artisan paylity:catalog-sync vtpass
```

Behavior:

- Syncs variations for all active VTPass **data** services
- Uses `VTPassService::getServiceVariations()`
- Upserts by `provider_service_id` + `variation_code`
- Stores `raw_payload` JSON
- Marks missing variations **inactive** (not deleted)
- Prints summary: services synced, added, updated, deactivated, failures

Requires `FEATURE_VTPASS=true` and valid VTPass credentials.

Optional env:

```env
CATALOG_SYNC_PROVIDER=vtpass
```

---

## Public catalog API

```http
GET /api/v1/catalog/products
GET /api/v1/catalog/products?category=data
GET /api/v1/catalog/products?category=airtime
GET /api/v1/catalog/products?category=electricity
GET /api/v1/catalog/products?category=data&include_hidden=1
```

Public checkout (default):

- Returns only **visible** data variations
- Sorts by network, then `amount` ascending, then `sort_order`
- Includes `catalog_meta`: `total_variations`, `visible_variations`, `hidden_variations`

`include_hidden=1` is honored only when a valid `X-Operator-Key` header is sent (same key as ops console). Public requests ignore this flag.

Example (abbreviated):

```json
{
  "success": true,
  "data": {
    "provider": "vtpass",
    "categories": [
      { "key": "data", "name": "Data", "is_active": true }
    ],
    "data_services": [
      {
        "service_name": "mtn",
        "service_id": "mtn-data",
        "display_name": "MTN",
        "network": "MTN",
        "variations": [
          {
            "variation_code": "mtn-10mb-100",
            "name": "MTN 10MB (raw provider name)",
            "display_name": "10MB - 1 Day",
            "amount": 100,
            "fixed_price": true,
            "validity_label": "1 Day",
            "data_size_label": "10MB",
            "customer_category": "daily"
          }
        ]
      }
    ],
    "catalog_meta": {
      "total_variations": 42,
      "visible_variations": 18,
      "hidden_variations": 24
    }
  }
}
```

---

## Checkout validation

Before creating a transaction:

- **Data:** `variation_code` must exist in `provider_variations` and be active
- **Airtime:** network must match an active `provider_services` row
- **Electricity:** disco must match an active `provider_services` row

Invalid mapping returns HTTP 422 with `errors.code: INVALID_PRODUCT_VARIATION`.

Fulfillment uses enriched `request_payload`:

- `service_id` from catalog
- `variation_code` for data
- recipient phone as `billersCode` for data
- meter number as `billersCode` for electricity
- catalog amount when `fixed_price=true`

---

## Staging checklist

1. `php artisan migrate --force`
2. `php artisan db:seed --class=ProductCatalogSeeder`
3. Set VTPass credentials and `FEATURE_VTPASS=true`
4. `php artisan paylity:catalog-sync vtpass`
5. `php artisan optimize:clear`
6. Verify `GET /api/v1/catalog/products?category=data` returns variations
7. Complete a data checkout with a synced plan only

---

## Production checklist

1. Run migrations and baseline seeder on production API
2. Configure live VTPass credentials
3. Run catalog sync after deploy and on a schedule (daily recommended)
4. Confirm checkout frontend loads catalog before allowing data payment
5. Monitor ops detail for `catalog_validated: true` on new transactions

---

## Troubleshooting: “variation code does not exist”

| Step | Action |
|------|--------|
| 1 | Check transaction ops detail: `variation_code`, `service_id`, `catalog_validated` |
| 2 | Compare variation_code to VTPass sandbox/live catalog |
| 3 | Re-run `php artisan paylity:catalog-sync vtpass` |
| 4 | Confirm variation is `is_active=true` in `provider_variations` |
| 5 | Ensure checkout used catalog API plan (not stale frontend hardcoded ID) |

If sync fails, check VTPass credentials and `FEATURE_VTPASS=true`, then inspect command summary for per-service failures.

---

## Troubleshooting bad plan names in checkout

| Symptom | Action |
|---------|--------|
| Voice/Xtratalk plans still visible | Run `php artisan paylity:catalog-classify`; confirm `is_visible=false` in DB |
| Valid plan missing from checkout | Check `is_visible`, `is_active`; set `display_override=true` to preserve manual visibility |
| Wrong customer label | Update `display_name` and set `display_override=true`, or fix classifier and re-run classify |
| Ops shows raw name but checkout differs | Expected — ops shows `provider_variation_name` + `display_name` |

---

## Related docs

- [STAGING-SMOKE-TESTS.md](../deployment/STAGING-SMOKE-TESTS.md)
- [VTPass Integration Checklist](./VTPASS-INTEGRATION-CHECKLIST.md)
