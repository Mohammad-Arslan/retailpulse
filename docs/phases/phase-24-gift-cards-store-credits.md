# Phase 24 — Gift Cards & Store Credits

**SRS Reference:** §3.26
**Status:** Planned
**Depends on:** Phase 9 (Customers & Loyalty — store credits tied to customer profile), Phase 8 (Checkout — gift card as payment method)
**Feeds into:** Phase 28 (SaaS — gift card module gated per plan)

---

## Objective
Introduce gift cards (physical and digital) and store credits as first-class payment methods at the POS, with issuance, redemption, balance management, and expiry policies fully integrated into the existing checkout and return flows.

---

## 1. Data Model

### gift_cards
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| code | varchar(20) unique | Random alphanumeric, uppercase |
| branch_id | bigint FK | Issuing branch |
| initial_value | decimal(12,2) | |
| current_balance | decimal(12,2) | |
| currency | char(3) | Matches branch currency |
| issued_by | bigint FK → users | |
| issued_to_customer_id | bigint FK nullable | If issued to a known customer |
| issued_at | timestamp | |
| expires_at | timestamp nullable | Null = no expiry |
| status | enum | `active`, `fully_redeemed`, `expired`, `void` |
| is_digital | boolean | true = QR email, false = physical print |

### gift_card_transactions
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| gift_card_id | bigint FK | |
| type | enum | `issue`, `redeem`, `refund`, `void`, `expiry_debit` |
| amount | decimal(12,2) | Positive = credit, negative = debit |
| balance_after | decimal(12,2) | Snapshot after transaction |
| sale_id | bigint FK nullable | |
| performed_by | bigint FK → users | |
| created_at | timestamp | |

### store_credits
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| customer_id | bigint FK | |
| amount | decimal(12,2) | Current remaining balance |
| reason | varchar(255) | "Return — Sale #1045", "Goodwill credit" |
| issued_by | bigint FK → users | |
| issued_at | timestamp | |
| expires_at | timestamp nullable | |
| status | enum | `active`, `fully_used`, `expired`, `void` |

### store_credit_transactions
| Column | Type | Notes |
| :--- | :--- | :--- |
| id | bigint PK | |
| store_credit_id | bigint FK | |
| type | enum | `issue`, `redeem`, `void`, `expiry_debit` |
| amount | decimal(12,2) | |
| balance_after | decimal(12,2) | |
| sale_id | bigint FK nullable | |
| created_at | timestamp | |

---

## 2. Gift Card Issuance

**Physical Gift Card**
1. Admin → Gift Cards → Issue New Card.
2. Enter value, optional customer, optional expiry.
3. System generates a unique alphanumeric code (8 chars, uppercase, excluding confusable characters like O/0/I/1).
4. Print barcode label to a label printer (Phase 21 hardware layer).

**Digital Gift Card**
1. Same flow with `is_digital = true`.
2. `GiftCardIssuedEvent` dispatched → `SendDigitalGiftCardNotification` sends an email/WhatsApp message with a QR code image of the card code and a balance-check URL.

**Bulk Issuance**
- Admin can generate N gift cards of the same value in one operation (e.g., 500 × 1000 PKR cards for a promotional campaign).
- Bulk generation is queued; results downloadable as CSV.

---

## 3. Redemption at POS Checkout

1. Cashier selects "Gift Card" payment tab.
2. Enters code (keyboard or barcode scan).
3. `GiftCardService::validate($code, $cartTotal)` checks: active, not expired, balance > 0.
4. Displays current balance and amount to apply (min of balance and remaining cart total).
5. On sale confirmation: `GiftCardService::redeem($code, $amount, $sale)` atomically:
    - Decrements `gift_cards.current_balance` (using `SELECT ... FOR UPDATE`).
    - Creates `gift_card_transactions` record.
    - Updates `status` to `fully_redeemed` if balance reaches 0.
6. If the gift card covers only part of the cart, the remainder can be paid by any other method (split tender).

---

## 4. Store Credit Integration with Returns (Phase 14)

When a return is processed in "Store Credit" mode:
- `StoreCreditService::issue($customer, $amount, $reason, $saleId)` creates the `store_credits` record.
- Customer's 360° profile shows total available store credits.
- At POS, if the customer has active store credits, a "Store Credit (PKR X available)" tab appears in the payment methods panel.
- Redemption: `StoreCreditService::redeem($storeCredit, $amount, $sale)` with same atomic decrement pattern.

---

## 5. Balance Enquiry API

`GET /api/v1/gift-cards/{code}/balance` — public endpoint (no auth required).

Returns:
```json
{
  "code": "GC-ABCD-1234",
  "current_balance": 750.00,
  "currency": "PKR",
  "expires_at": "2027-06-04",
  "status": "active"
}
```

Rate-limited: 10 requests/minute per IP.

---

## 6. Expiry Jobs

- `ExpireGiftCardsJob` — daily; sets `status = expired` and creates an `expiry_debit` transaction for remaining balance on expired cards.
- `ExpireStoreCreditsJob` — daily; same pattern for store credits.

---

## 7. Admin UI

- **Gift Cards → Issue:** Single or bulk issue form.
- **Gift Cards → List:** Search by code, customer, status; view transaction history per card.
- **Gift Cards → Settings:** Default expiry period (days), code format, digital notification template.
- **Customers → [Profile] → Store Credits:** List of all store credits; manual issue (with reason); void individual credit.

---

## 8. API Endpoints

| Method | URI | Permission | Description |
| :--- | :--- | :--- | :--- |
| GET | /api/v1/gift-cards/{code}/balance | public | Balance enquiry |
| POST | /api/v1/gift-cards | gift-cards.issue | Issue single gift card |
| POST | /api/v1/gift-cards/bulk | gift-cards.issue | Bulk issue |
| POST | /api/v1/gift-cards/{code}/redeem | pos.access | Redeem at POS |
| POST | /api/v1/gift-cards/{id}/void | gift-cards.manage | Void a card |
| GET | /api/v1/customers/{id}/store-credits | customers.view | List store credits |
| POST | /api/v1/customers/{id}/store-credits | customers.manage | Issue store credit |

---

## 9. Services & Classes

- `GiftCardService` — issue (single/bulk), validate, redeem, void, expiry.
- `StoreCreditService` — issue (on return), redeem, void, expiry.
- `GiftCardCodeGenerator` — cryptographically random, collision-checked code generator.
- `GiftCardIssuedEvent` / `SendDigitalGiftCardNotification` — email/WhatsApp dispatch.
- `ExpireGiftCardsJob`, `ExpireStoreCreditsJob` — nightly scheduled jobs.
