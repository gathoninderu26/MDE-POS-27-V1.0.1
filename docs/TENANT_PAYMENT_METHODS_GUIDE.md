# MDE-POS Multi-Tenant Payment Methods & Custom Channels Guide

## 1. Executive Summary & Architecture

In MDE-POS (Version 1.0.1+), each registered tenant store can operate with its own unique payment methods and transaction credentials. This accommodates a broad spectrum of commercial operations—from standard retail stores using Safaricom M-Pesa Buy Goods Tills and Cash Drawers, to corporate distributors using Paybill shortcodes, bank pesalink accounts, customer credit balances, and PDQ card terminals.

### Hybrid Configuration Model
1. **Turnkey Super Admin Onboarding:**
   When the Super Admin registers a new tenant in `/super-admin/tenants`, the wizard features a dedicated **"2. Payment Channels"** tab. The Super Admin inputs the tenant's primary Till number, Paybill shortcode, default cash drawer, and PDQ settings. On account creation, the tenant's store is immediately ready for transactions.
2. **Tenant Store Self-Service:**
   Store Owners and Managers can navigate to `/settings` -> **"Payment Methods & Tills"** to toggle methods on/off, edit Till numbers, update Paybill accounts, adjust PDQ bank labels, or add custom channels at any time.
3. **Super Admin Live Maintenance:**
   Super Admins can view and adjust payment configurations on demand from `/super-admin/tenants/{id}`.
4. **Dynamic POS Checkout Terminal:**
   The POS checkout modal dynamically loads the tenant's active payment channels, creates dynamic hotkeys (`1`..`N`), displays on-screen Till / Paybill numbers for cashiers, and routes Express STK Push prompts directly to Safaricom Daraja using the store's credentials.

---

## 2. Data Structure & Storage

Payment channels are stored non-destructively in the existing `tenants.settings` JSON column. No database migrations or schema adjustments are required.

```json
{
  "mpesa_till_number": "523910",
  "payment_methods": [
    {
      "id": "cash",
      "name": "Cash",
      "type": "Cash",
      "enabled": true,
      "details": {
        "drawer_label": "Main Drawer 1",
        "allow_change": true
      }
    },
    {
      "id": "mpesa-till",
      "name": "M-Pesa Buy Goods",
      "type": "Mobile Money",
      "enabled": true,
      "details": {
        "till_number": "523910",
        "store_name": "Nairobi Fresh Mart",
        "stk_push": true
      }
    },
    {
      "id": "mpesa-paybill",
      "name": "M-Pesa Paybill",
      "type": "Mobile Money",
      "enabled": false,
      "details": {
        "business_number": "247247",
        "account_number": "NFM-001"
      }
    },
    {
      "id": "card-pdq",
      "name": "Card (PDQ Terminal)",
      "type": "Card",
      "enabled": true,
      "details": {
        "terminal_id": "POS-98102",
        "bank_name": "Equity Bank",
        "surcharge_percent": 0
      }
    },
    {
      "id": "bank-transfer",
      "name": "Bank Transfer / Pesalink",
      "type": "Bank",
      "enabled": false,
      "details": {
        "bank_name": "KCB Bank",
        "account_number": "1122334455"
      }
    },
    {
      "id": "credit",
      "name": "Customer Credit",
      "type": "Credit",
      "enabled": true,
      "details": {}
    }
  ]
}
```

### Fallback Defaults
If an existing or legacy tenant account has an empty or unconfigured `payment_methods` array, `useTenant()` automatically resolves the East African retail defaults (`Cash`, `M-Pesa Buy Goods (Till 523910)`, `Card`, `Bank Transfer`, and `Customer Credit`), ensuring 100% backward compatibility.

---

## 3. Super Admin Tenant Provisioning

**Location:** `resources/js/pages/super-admin/Tenants.vue` & `app/Services/TenantProvisioningService.php`

When registering a tenant store, the modal wizard provides 4 configuration phases:
1. **1. Profile & Admin**: Store Name, Subdomain, Currency (KES/USD/UGX/TZS), Business Type, Initial Admin User.
2. **2. Payment Channels**:
   - **Cash Drawer**: Enabled by default; custom drawer label (e.g. `Register 1 Drawer`).
   - **M-Pesa Buy Goods Till**: Toggle switch, Till Number input (e.g. `523910`), Store Branding Name, and STK push toggle.
   - **M-Pesa Paybill**: Optional shortcode and account number.
   - **Card / PDQ Terminal**: Bank name (KCB, Equity, Stanbic, etc.) and Terminal ID.
   - **Bank Transfer / Pesalink**: Bank name and Account number.
   - **Customer Credit Account**: Toggle credit sales capability.
3. **3. Devices & POS**: PC Hardware ID locking, auto thermal printing, offline sync intervals.
4. **4. Network & Domain**: Dedicated domain and IP restrictions.

The backend `TenantProvisioningService::provision()` receives these parameters, validates them, and writes the structured configuration into the newly created tenant's record.

---

## 4. Tenant Store Self-Service Configuration

**Location:** `resources/js/pages/settings/SettingsIndex.vue` -> **"Payment Methods & Tills"**

Store Owners and Managers can modify their store's channels at any time:
- **Enable / Disable Toggles**: Instantly activate or deactivate any payment channel with immediate persistence.
- **Edit Modal**: Click "Edit" on any method to update its details:
  - Update Till number when changing Safaricom SIM/Till cards.
  - Change Paybill account numbers or business numbers.
  - Update Card terminal IDs when changing merchant banks.
- **Custom Methods**: Store owners can add bespoke channels (e.g., "Pesalink Wire", "Voucher", "Corporate Check").
- **Delete Protection**: Default essential channels are protected against accidental removal.

---

## 5. POS Terminal Checkout Integration

**Location:** `resources/js/components/pos/PaymentModal.vue`

The POS checkout terminal dynamically adapts to whatever payment methods are active for the store:

### 1. Dynamic Method Buttons & Hotkeys
- The selector grid dynamically renders all active methods for the store.
- Numeric badges (`1`, `2`, `3`, ..., `N`) appear on each button.
- Keyboard hotkeys (`1` through `N`) instantly switch payment methods.
- Standard shortcuts (`M` for M-Pesa, `S` for STK push, `P` or `F12` for Print & Confirm) are preserved.

### 2. Live On-Screen Till / Paybill Guidance
When a cashier selects M-Pesa or a Till method, an emerald callout banner appears at the top of the payment screen displaying:
- **Buy Goods Till:** `523910` (with store name)
- Or **Paybill:** `247247`, Account: `STORE`
This allows the cashier to immediately read out the Till or Paybill number to customers who wish to pay manually from their own phone.

### 3. Integrated Safaricom Daraja STK Push
- Cashiers can type the customer's phone number (`07XX...` or `2547XX...`) and press **"Send STK Push (S)"**.
- The prompt is routed to the customer's phone via Safaricom Daraja.
- When the customer enters their PIN, the POS automatically captures the confirmation code (e.g. `QK89123847`), validates the amount, and marks the payment as complete.

### 4. Split Payment Mode
In Split mode, the payment method dropdown loops through the store's configured channels. Cashiers can divide a bill across Cash and M-Pesa Till, or Card and Customer Credit.

---

## 6. Safaricom Daraja Multi-Tenant Routing

**Location:** `app/Http/Controllers/MpesaController.php`

The Safaricom Daraja STK Push controller operates with tenant awareness:
1. When `/mpesa/stk-push` is called, it inspects the current tenant's settings:
   - If the tenant has configured their own `mpesa_consumer_key`, `mpesa_consumer_secret`, `mpesa_till_number`, and `mpesa_passkey`, those credentials are used.
   - If not set, it transparently falls back to the system's central `.env` Daraja credentials (`MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, etc.).
2. The checkout request ID and tenant configuration are cached in Redis/Cache for 15 minutes.
3. When polling via `/mpesa/stk-query` or receiving Safaricom callbacks via `/mpesa/callback`, the transaction is matched to the originating tenant configuration.

---

## 7. Thermal Receipt Output

**Location:** `resources/js/components/pos/ReceiptModal.vue`

The 80mm thermal receipt automatically displays:
- Store name, branch, address, telephone, and customer details.
- Active payment method name (e.g. `Payment (M-Pesa Buy Goods): KES 2,500.00`).
- M-Pesa confirmation code (e.g. `M-Pesa Code: QKL83910`).
- Terminal Authorization Code for Card/Bank transactions (e.g. `Ref / Auth: POS-98102`).
- Barcode 39 vector for scanner lookup.
