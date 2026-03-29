# Payment Settings - Visual Guide

## Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│                    PAYMENT SETTINGS                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  │
│  ┃ 🔵 HOW CUSTOMERS PAY                              ┃  │
│  ┃                                                     ┃  │
│  ┃ This setting defines how payments are collected    ┃  │
│  ┃ and how orders are confirmed.                      ┃  │
│  ┃                                                     ┃  │
│  ┃ ⚪ Customers pay on-site at the restaurant         ┃  │
│  ┃    Orders are placed via Tavlo and paid directly   ┃  │
│  ┃    at the restaurant (cash or external terminal).  ┃  │
│  ┃                                                     ┃  │
│  ┃ ⚪ Customers pay online via Tavlo                  ┃  │
│  ┃    Customers pay online during checkout. Tavlo     ┃  │
│  ┃    handles the payment process.                    ┃  │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ AVAILABLE PAYMENT METHODS                         │    │
│  ├───────────────────────────────────────────────────┤    │
│  │                                                   │    │
│  │  [Conditional: Shows based on selection above]   │    │
│  │                                                   │    │
│  │  IF ON-SITE:                                     │    │
│  │  ☑ 💵 Cash (Dine-in)                            │    │
│  │     Customer pays at restaurant. Order           │    │
│  │     confirmed immediately.                       │    │
│  │                                                   │    │
│  │  ☑ 🛍️ Cash for Takeaway Orders                 │    │
│  │     Customer pays when picking up. Order may     │    │
│  │     require manual confirmation.                 │    │
│  │                                                   │    │
│  │  IF ONLINE:                                      │    │
│  │  ┌─ Card Payments (Digital Wallets) ─────────┐  │    │
│  │  │ ☑ 💳 Apple Pay                            │  │    │
│  │  │ ☑ 💳 Google Pay                           │  │    │
│  │  └──────────────────────────────────────────┘  │    │
│  │                                                   │    │
│  │  ☑ 💳 Card Payments                             │    │
│  │     Credit / Debit Card                          │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ WHAT CUSTOMERS WILL SEE                           │    │
│  ├───────────────────────────────────────────────────┤    │
│  │ ✓ Dine-in: Pay at restaurant                     │    │
│  │ ✓ Takeaway: Cash on pickup available             │    │
│  │ ✓ Online payments: Card, Apple Pay, Google Pay   │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ Currency                                           │    │
│  │ ┌──────────────────────────────────────────────┐  │    │
│  │ │ EUR (€)                          [🔒 Locked] │  │    │
│  │ └──────────────────────────────────────────────┘  │    │
│  │ ℹ️ Currency is defined by the restaurant's       │    │
│  │   country and cannot be changed.                  │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  [Cancel]                                 [Save Settings]  │
└─────────────────────────────────────────────────────────────┘
```

---

## Section Details

### 🔵 Section 1: Payment Collection Model

**Visual Style:**
- Background: Light blue (`bg-blue-50`)
- Border: Blue (`border-blue-200`)
- Padding: Large (`p-6`)
- Position: Top of page (highest priority)

**Elements:**
- Title: Bold, dark text
- Description: Smaller, grey text
- Radio buttons: Two mutually exclusive options
- Sub-text: Indented, lighter color

**Interaction:**
- Only one radio can be selected
- Selecting one deselects the other
- Immediately affects what appears in Section 2

---

### 📋 Section 2: Available Payment Methods

**Visual Style:**
- Background: White
- Border: Standard grey
- Padding: Medium (`p-4`)

**Conditional Display:**

#### When "on-site" selected:
```
Available Payment Methods
├── Cash (Dine-in)
│   └── Helper: "Customer pays at restaurant..."
└── Cash for Takeaway Orders
    └── Helper: "Customer pays when picking up..."
```

#### When "online" selected:
```
Available Payment Methods
├── Card Payments (Digital Wallets)
│   ├── Apple Pay (checkbox)
│   └── Google Pay (checkbox)
└── Card Payments
    └── Credit / Debit Card
```

**Elements:**
- Checkboxes (not radio buttons)
- Icons: 💵 🛍️ 💳
- Helper text below each option
- Grouped items have border and indentation

---

### ✅ Section 3: Customer-Facing Preview

**Visual Style:**
- Background: Grey (`bg-gray-50`)
- Border: Grey (`border-gray-200`)
- Padding: Medium (`p-4`)

**Purpose:**
- Read-only
- Shows real-time preview
- Updates as checkboxes change

**Format:**
```
✓ [Payment option description]
✓ [Payment option description]
✓ [Payment option description]
```

**Example Outputs:**

```
On-site + Cash enabled:
✓ Dine-in: Pay at restaurant
✓ Takeaway: Cash on pickup available
```

```
Online + All cards enabled:
✓ Online payments: Card, Apple Pay, Google Pay
```

```
Nothing selected:
✓ No payment methods configured
```

---

### 🔒 Section 4: Currency

**Visual Style:**
- Background: Grey (`bg-gray-100`)
- Cursor: Not-allowed
- State: Disabled

**Elements:**
- Dropdown (locked, can't change)
- Lock icon indicator
- Helper text explaining why locked

**Why Locked:**
- Currency tied to restaurant country
- Legal/tax requirement
- Not a payment preference

---

## Color Coding

| Element | Color | Purpose |
|---------|-------|---------|
| Blue section | `bg-blue-50` | Most important decision |
| White sections | `bg-white` | Standard content |
| Grey preview | `bg-gray-50` | Read-only information |
| Grey disabled | `bg-gray-100` | Cannot be changed |
| Green checkmarks | `text-green-600` | Positive/enabled |
| Border grey | `border-gray-200` | Standard borders |
| Border blue | `border-blue-200` | Highlighted section |

---

## Typography Hierarchy

1. **Main Title** - `text-lg font-semibold` (18px, bold)
2. **Section Titles** - `text-lg font-semibold` (18px, bold)
3. **Option Titles** - `font-medium` (14px, medium)
4. **Body Text** - `text-sm` (14px, regular)
5. **Helper Text** - `text-sm text-gray-600` (14px, grey)
6. **Micro Text** - `text-xs text-gray-500` (12px, grey)

---

## Spacing

- Between sections: `space-y-8` (2rem / 32px)
- Within sections: `space-y-4` (1rem / 16px)
- Checkbox items: `space-y-3` (0.75rem / 12px)
- Padding large: `p-6` (1.5rem / 24px)
- Padding medium: `p-4` (1rem / 16px)

---

## Interaction States

### Radio Buttons
- **Default:** White circle, grey border
- **Selected:** Filled blue dot
- **Hover:** Slight background color change

### Checkboxes
- **Unchecked:** Empty square, grey border
- **Checked:** Blue with white checkmark
- **Hover:** Border darkens

### Disabled Field
- **Appearance:** Grey background
- **Cursor:** Not-allowed pointer
- **Text:** Darker grey (still readable)

---

## Responsive Behavior

**Desktop (> 768px):**
- Full width container
- Proper padding and spacing

**Tablet (768px - 1024px):**
- Slightly reduced padding
- Same layout structure

**Mobile (< 768px):**
- Stack all elements vertically
- Reduce padding
- Maintain readability

---

## Accessibility Features

✓ Semantic HTML (radio, checkbox inputs)  
✓ Proper label associations  
✓ Descriptive helper text  
✓ Color is not the only indicator (icons + text)  
✓ Disabled states clearly marked  
✓ Keyboard navigation supported  
✓ Screen reader friendly  

---

## Before vs After

### ❌ Before (Old Design)

```
Payment Settings
├── Accepted Payment Methods
│   ├── Apple Pay (checkbox)
│   ├── Google Pay (checkbox)
│   ├── Credit/Debit Card (checkbox)
│   ├── Cash (Dine-in) (checkbox)
│   └── Cash for Takeaway (checkbox)
├── Currency (dropdown - editable)
└── Stripe Integration
    ├── Enable Stripe (checkbox)
    └── API Keys (when enabled)
        ├── Public Key (input)
        └── Secret Key (input)
```

**Problems:**
- Not clear if payments are online or on-site
- Stripe exposed to vendor (PSP details)
- Currency editable (legal issue)
- No preview of customer experience

### ✅ After (New Design)

```
Payment Settings
├── 🔵 How Customers Pay (radio - PRIORITY)
│   ├── On-site at restaurant
│   └── Online via Tavlo
├── Available Payment Methods (conditional)
│   ├── [Shows cash OR digital based on above]
│   └── [With helper text for each]
├── What Customers Will See (preview)
│   └── [Dynamic list of enabled methods]
└── Currency (locked)
    └── [Helper: tied to country]
```

**Improvements:**
- ✓ Payment flow explicit (radio choice)
- ✓ No PSP details (Stripe hidden)
- ✓ Currency locked (legal compliance)
- ✓ Customer preview (UX clarity)
- ✓ Conditional display (less confusion)

---

**Last Updated:** January 2026
