# Strict Versioned POS Commerce API (v1) Specification & Verification Guide

> **Note**: Backward compatibility has been completely eliminated. Both the POS (`skynet_digital_pos`) and Ecommerce (`skynet_pos_ecommerce`) systems communicate strictly via the versioned API route prefix:
> ```
> /api/commerce/v1/
> ```
> No unversioned fallback aliases are used or required.

---

## 1. Overview & Connection Architecture

The Ecommerce application (`skynet_pos_ecommerce`) connects to the POS application (`skynet_digital_pos`) with the configured Base URL:
```text
http://127.0.0.1:8000/api/commerce/v1
```

All API requests are sent with strict relative paths against `/api/commerce/v1/` and include security & tenant identification headers on every call.

---

## 2. Authentication & Request Headers

Every request from Ecommerce (`PosApiClient`) to POS includes the following headers:

| Header Name | Type | Description |
|---|---|---|
| `Accept` | `string` | `application/json` |
| `Authorization` | `string` | `Bearer <api_secret>` |
| `X-Commerce-Key` | `string` | `<api_secret>` (Used by custom POS API token middleware) |
| `X-Client-Id` | `string` | Client / Pairing Identifier (e.g. `ECOMM-01`) |
| `X-Branch-Id` | `string` | Target POS Branch ID (e.g. `1`) |
| `X-Integration-Id` | `string` | POS Integration UUID / ID |
| `X-Timestamp` | `string` | Current Unix timestamp (e.g. `1792495373`) |
| `X-Signature` | `string` | HMAC-SHA256 signature generated with `api_secret` over `client_id:timestamp` |

---

## 3. Strict Versioned Endpoints Checklist (POS Side)

The POS project (`skynet_digital_pos`) registers and confirms these **12 versioned endpoints** under prefix `/api/commerce/v1`:

### A. Health & Handshake
1. **`GET /api/commerce/v1/ping`**
   - **Purpose**: Verify network connectivity, branch availability, and credentials.
   - **Response (200 OK)**:
     ```json
     {
       "success": true,
       "status": true,
       "message": "POS connection verified successfully.",
       "branch_id": 1,
       "branch_name": "Main Store",
       "version": "1.0.0",
       "data": {
         "status": "connected",
         "branch_id": 1,
         "branch_name": "Main Store",
         "integration_id": 1,
         "client_id": "ECOMM-01",
         "platform": "custom",
         "timestamp": "2026-10-20T22:00:00Z"
       }
     }
     ```

---

### B. Catalog & Product Inventory
2. **`GET /api/commerce/v1/catalog`**
   - **Query Parameters**:
     - `page` (integer, default `1`)
     - `per_page` (integer, default `50`, max `200`)
     - `since` or `updated_after` (string, ISO 8601 or `Y-m-d H:i:s`, optional incremental filter)
     - `search` (string, optional query filter)
     - `in_stock_only` (boolean, optional)
   - **Response (200 OK)**:
     ```json
     {
       "success": true,
       "status": true,
       "data": [
         {
           "id": 101,
           "name": "Men Casual T-Shirt",
           "pos_item_name": "Men Casual T-Shirt",
           "sku": "TSHIRT-001",
           "barcode": "123456789012",
           "item_reference": "TSHIRT-001",
           "description": "100% cotton crew neck",
           "is_active": true,
           "price": 25.00,
           "standard_price": 25.00,
           "selling_price": 25.00,
           "wholesale_price": 20.00,
           "base_unit_name": "Piece",
           "unit_name": "Piece",
           "front_store_qty": 45,
           "reserved_qty": 0,
           "available_qty": 45,
           "available_quantity": 45,
           "effective_availability": 45,
           "quantity": 45,
           "imei_tracking_enabled": false,
           "units": [
             {
               "id": 201,
               "pos_item_unit_id": 201,
               "unit_name": "Pack of 3",
               "quantity_in_base_units": 3.0,
               "multiplier": 3.0,
               "selling_price": 65.00,
               "standard_price": 65.00,
               "barcode": "123456789099"
             }
           ]
         }
       ],
       "pagination": {
         "current_page": 1,
         "last_page": 15,
         "per_page": 50,
         "total": 750
       },
       "meta": {
         "current_page": 1,
         "last_page": 15,
         "per_page": 50,
         "total": 750
       },
       "current_page": 1,
       "last_page": 15,
       "per_page": 50,
       "total": 750,
       "next_page_url": "http://127.0.0.1:8000/api/commerce/v1/catalog?page=2"
     }
     ```

3. **`GET /api/commerce/v1/catalog/{id}`**
   - **Purpose**: Retrieve single POS item detail including multi-selling units.
   - **Response (200 OK)**:
     ```json
     {
       "success": true,
       "status": true,
       "pos_item_id": 101,
       "available_quantity": 45,
       "data": {
         "id": 101,
         "item_id": 101,
         "pos_item_id": 101,
         "name": "Men Casual T-Shirt",
         "pos_item_name": "Men Casual T-Shirt",
         "sku": "TSHIRT-001",
         "barcode": "123456789012",
         "price": 25.00,
         "standard_price": 25.00,
         "selling_price": 25.00,
         "front_store_qty": 45,
         "reserved_qty": 0,
         "available_qty": 45,
         "available_quantity": 45,
         "units": [
           {
             "id": 201,
             "pos_item_unit_id": 201,
             "unit_name": "Pack of 3",
             "multiplier": 3.0,
             "quantity_in_base_units": 3.0,
             "price": 65.00,
             "selling_price": 65.00,
             "standard_price": 65.00,
             "barcode": "123456789099"
           }
         ]
       }
     }
     ```

4. **`GET /api/commerce/v1/catalog/{id}/stock`**
   - **Purpose**: Fast fallback stock check for a single item.
   - **Response (200 OK)**:
     ```json
     {
       "success": true,
       "status": true,
       "pos_item_id": 101,
       "available_quantity": 45,
       "data": {
         "id": 101,
         "item_id": 101,
         "pos_item_id": 101,
         "name": "Men Casual T-Shirt",
         "front_store_qty": 45,
         "reserved_qty": 0,
         "available_qty": 45,
         "available_quantity": 45
       }
     }
     ```

---

### C. Live Stock Availability
5. **`POST /api/commerce/v1/availability`**
   - **Payload**:
     ```json
     {
       "item_ids": [101, 102, 103, 104]
     }
     ```
   - **Response (200 OK)**:
     ```json
     {
       "success": true,
       "status": true,
       "data": [
         {
           "item_id": 101,
           "pos_item_id": 101,
           "front_store_qty": 45,
           "reserved_qty": 0,
           "available_qty": 45,
           "available_quantity": 45,
           "effective_availability": 45
         },
         {
           "item_id": 102,
           "pos_item_id": 102,
           "front_store_qty": 12,
           "reserved_qty": 0,
           "available_qty": 12,
           "available_quantity": 12,
           "effective_availability": 12
         }
       ]
     }
     ```

---

### D. Stock Reservations (Cart & Checkout)
6. **`POST /api/commerce/v1/reservations`**
   - **Purpose**: Authoritatively hold stock during checkout before payment.
   - **Deadlock Protection**: Execution on POS is wrapped in `CommerceWebhookDispatcher::withoutWebhooks(...)` to guarantee outbound stock change webhooks are suppressed during reservations, preventing cURL 28 timeout deadlocks.
   - **Payload**:
     ```json
     {
       "external_reference": "ECOM-ORD-20261020-00123",
       "expiry_minutes": 30,
       "items": [
         {
           "pos_item_id": 101,
           "pos_item_unit_id": null,
           "quantity": 2
         }
       ]
     }
     ```
   - **Response (201 Created)**:
     ```json
     {
       "success": true,
       "status": true,
       "message": "Inventory successfully reserved.",
       "reservation_id": 849,
       "reference": "ECOM-ORD-20261020-00123",
       "external_order_reference": "ECOM-ORD-20261020-00123",
       "expires_at": "2026-10-20T22:30:00Z",
       "data": {
         "reservation_id": 849,
         "reference": "ECOM-ORD-20261020-00123",
         "external_order_reference": "ECOM-ORD-20261020-00123",
         "status": "pending",
         "expires_at": "2026-10-20T22:30:00Z",
         "items": [
           {
             "item_id": 101,
             "pos_item_id": 101,
             "name": "Men Casual T-Shirt",
             "quantity": 2,
             "reserved_quantity": 2,
             "unit_price": 25.00
           }
         ]
       }
     }
     ```
   - **Error (422 Unprocessable Entity - Insufficient Stock)**:
     ```json
     {
       "success": false,
       "status": false,
       "message": "Insufficient stock for item #101. Available: 1, Requested: 2",
       "error_code": "INSUFFICIENT_STOCK",
       "failures": [
         {
           "item_id": 101,
           "name": "Men Casual T-Shirt",
           "requested": 2,
           "available": 1
         }
       ]
     }
     ```

7. **`GET /api/commerce/v1/reservations/{reference}`**
   - **Purpose**: Query reservation status (`pending`, `confirmed`, `expired`, `released`).
   - **Response (200 OK)**:
     ```json
     {
       "success": true,
       "status": true,
       "reference": "ECOM-ORD-20261020-00123",
       "reservation_status": "pending",
       "expires_at": "2026-10-20T22:30:00Z",
       "data": {
         "reservation_id": 849,
         "external_order_reference": "ECOM-ORD-20261020-00123",
         "status": "pending",
         "is_expired": false,
         "expires_at": "2026-10-20T22:30:00Z",
         "confirmed_at": null,
         "released_at": null,
         "items": [
           {
             "item_id": 101,
             "pos_item_id": 101,
             "name": "Men Casual T-Shirt",
             "quantity": 2,
             "unit_price": 25.00
           }
         ],
         "metadata": null
       }
     }
     ```

8. **`POST /api/commerce/v1/reservations/{reference}/confirm`**
   - **Purpose**: Authoritatively deduct stock upon successful payment or confirmed COD order.
   - **Deadlock Protection**: Webhooks are suppressed during confirmation so no circular HTTP request blocks port 8002.
   - **Response (200 OK)**:
     ```json
     {
       "success": true,
       "status": true,
       "message": "Order inventory successfully confirmed and deducted.",
       "reference": "ECOM-ORD-20261020-00123",
       "data": {
         "reservation_id": 849,
         "external_order_reference": "ECOM-ORD-20261020-00123",
         "status": "confirmed",
         "confirmed_at": "2026-10-20T22:05:00Z",
         "items": [
           {
             "item_id": 101,
             "pos_item_id": 101,
             "name": "Men Casual T-Shirt",
             "quantity_deducted": 2,
             "current_front_store_qty": 43
           }
         ]
       }
     }
     ```

9. **`POST /api/commerce/v1/reservations/{reference}/release`**
   - **Purpose**: Release held stock back to available inventory (payment cancelled, abandoned checkout).
   - **Response (200 OK)**:
     ```json
     {
       "success": true,
       "status": true,
       "message": "Reservation successfully released.",
       "reference": "ECOM-ORD-20261020-00123",
       "data": {
         "reservation_id": 849,
         "external_order_reference": "ECOM-ORD-20261020-00123",
         "status": "released",
         "released_at": "2026-10-20T22:10:00Z"
       }
     }
     ```

---

### E. Product Mapping Registry & POS Deletion Protection
10. **`POST /api/commerce/v1/mappings/register`**
    - **Purpose**: Inform POS that specific items/units are actively mapped to online products. POS prevents cashier or branch managers from deleting these items.
    - **Payload**:
      ```json
      {
        "mappings": [
          {
            "pos_item_id": 101,
            "pos_item_unit_id": null,
            "ecommerce_product_id": 45,
            "ecommerce_variant_id": null,
            "sync_pricing": true,
            "sync_stock": true
          }
        ]
      }
      ```
    - **Response (200 OK)**:
      ```json
      {
        "success": true,
        "status": true,
        "message": "Successfully registered 1 mappings with POS.",
        "registered": 1
      }
      ```

11. **`POST /api/commerce/v1/mappings/unregister`**
    - **Purpose**: Unregister protection when an online product is deleted, unmapped, or changed to "Ecommerce Only".
    - **Payload**:
      ```json
      {
        "pos_item_ids": [101]
      }
      ```
    - **Response (200 OK)**:
      ```json
      {
        "success": true,
        "status": true,
        "message": "Unregistered 1 item mappings.",
        "unregistered": 1
      }
      ```

12. **`POST /api/commerce/v1/mappings/reconcile`**
    - **Purpose**: Sync the entire active mapping table to heal any registry drift between POS and Ecommerce.
    - **Payload**:
      ```json
      {
        "mappings": [
          {
            "pos_item_id": 101,
            "pos_item_unit_id": null,
            "ecommerce_product_id": 45,
            "ecommerce_variant_id": null,
            "sync_pricing": true,
            "sync_stock": true
          }
        ]
      }
      ```
    - **Response (200 OK)**:
      ```json
      {
        "success": true,
        "status": true,
        "message": "Mapping registry reconciled successfully.",
        "reconciled": 1
      }
      ```

---

## 4. Verification & Testing

### Verification Commands on POS Terminal:
```bash
php artisan route:list --path=commerce/v1
```

### Automated Feature Test Suite:
```bash
php artisan test tests/Feature/CommerceApiV1VersioningTest.php \
                 tests/Feature/ReservationDeadlockAndWebhookSuppressionTest.php \
                 tests/Feature/EcommerceMappingAndDeletionProtectionTest.php \
                 tests/Feature/CommerceIntegrationTest.php
```

All 31 tests pass with 100% success:
- `CommerceApiV1VersioningTest`: 5 passed
- `ReservationDeadlockAndWebhookSuppressionTest`: 3 passed
- `EcommerceMappingAndDeletionProtectionTest`: 7 passed
- `CommerceIntegrationTest`: 16 passed
