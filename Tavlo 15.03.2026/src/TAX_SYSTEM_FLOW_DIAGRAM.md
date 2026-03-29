# Tavlo Tax System - Flow Diagrams

## System Architecture Flow

```
┌─────────────────────────────────────────────────────────────┐
│                     TAVLO TAX SYSTEM                         │
│                                                              │
│  ┌────────────────┐         ┌──────────────────┐           │
│  │  Tax Rules DB  │────────▶│  /utils/taxRules │           │
│  │   (Austria)    │         │                  │           │
│  │   (Germany)    │         │  - getVATRate()  │           │
│  └────────────────┘         │  - calculate()   │           │
│         │                   │  - format()      │           │
│         │                   └──────────────────┘           │
│         │                            │                      │
│         ▼                            ▼                      │
│  ┌────────────────────────────────────────────┐            │
│  │         Vendor Dashboard                    │            │
│  │                                             │            │
│  │  ┌──────────────────┐  ┌─────────────────┐│            │
│  │  │   Settings       │  │ Menu Management ││            │
│  │  │  - Select        │  │  - Categories   ││            │
│  │  │    Country       │  │  - Items        ││            │
│  │  │  - View VAT      │  │  - Tax Category ││            │
│  │  │    Rules         │  │                 ││            │
│  │  └──────────────────┘  └─────────────────┘│            │
│  └────────────────────────────────────────────┘            │
│                       │                                      │
│                       ▼                                      │
│  ┌────────────────────────────────────────────┐            │
│  │         Customer Receipt                    │            │
│  │  - VAT Breakdown by Category               │            │
│  │  - Compliance Badge                        │            │
│  └────────────────────────────────────────────┘            │
└─────────────────────────────────────────────────────────────┘
```

---

## User Journey: Restaurant Setup

```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  Vendor  │────▶│ Settings │────▶│  Select  │────▶│   VAT    │
│  Signs   │     │   Page   │     │ Country  │     │  Rules   │
│    Up    │     │          │     │ (AT/DE)  │     │  Loaded  │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
                                         │
                                         ▼
                              ┌────────────────────┐
                              │   System Loads:    │
                              │   AT: 10/20/20%    │
                              │   DE: 7/19/19%     │
                              └────────────────────┘
```

---

## Category Creation Flow

```
                    ┌─────────────────┐
                    │ Vendor Creates  │
                    │    Category     │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │   Enter Name    │
                    │  "Appetizers"   │
                    └────────┬────────┘
                             │
                    ┌────────▼────────────────┐
                    │  Select Tax Category   │
                    │  🍽 Food               │
                    │  🥤 Beverage (NA)      │
                    │  🍺 Beverage (Alc)     │
                    └────────┬────────────────┘
                             │
                    ┌────────▼────────┐
                    │  Icon Auto-Set  │
                    │   Based on      │
                    │  Tax Category   │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │   Category      │
                    │    Saved        │
                    │  with Default   │
                    │  Tax Category   │
                    └─────────────────┘
```

---

## Item Creation Flow (Tax Inheritance)

```
┌───────────────────┐
│  Vendor Adds New  │
│      Item         │
└─────────┬─────────┘
          │
┌─────────▼─────────┐
│  Selects Category │
│   "Appetizers"    │
└─────────┬─────────┘
          │
          ├─────────────────────────────────┐
          │                                 │
┌─────────▼─────────┐           ┌───────────▼──────────┐
│  Tax Category     │           │   VAT Rate           │
│  Auto-Inherits:   │           │   Auto-Calculated:   │
│  🍽 Food          │           │   10% (Austria)      │
└─────────┬─────────┘           │   7% (Germany)       │
          │                     └──────────────────────┘
          │
┌─────────▼─────────┐
│  Display:         │
│  "Applied VAT:    │
│  10% (Austria –   │
│  Food)"           │
│  (Read-Only)      │
└─────────┬─────────┘
          │
┌─────────▼─────────┐
│   Item Saved      │
│   with Tax        │
│   Category        │
└───────────────────┘
```

---

## VAT Calculation Flow

```
                    ┌────────────────┐
                    │  Customer      │
                    │  Orders Items  │
                    └───────┬────────┘
                            │
                    ┌───────▼────────┐
                    │  System Reads  │
                    │  Each Item:    │
                    │  - taxCategory │
                    │  - price       │
                    └───────┬────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
┌───────▼────────┐  ┌───────▼────────┐  ┌──────▼─────────┐
│   Food Items   │  │  Beverage (NA) │  │ Beverage (Alc) │
│   Tax Cat:     │  │  Tax Cat:      │  │  Tax Cat:      │
│   'food'       │  │  'bev-na'      │  │  'bev-alc'     │
└───────┬────────┘  └───────┬────────┘  └──────┬─────────┘
        │                   │                   │
┌───────▼────────┐  ┌───────▼────────┐  ┌──────▼─────────┐
│ Get VAT Rate:  │  │ Get VAT Rate:  │  │ Get VAT Rate:  │
│ getVATRate(    │  │ getVATRate(    │  │ getVATRate(    │
│  'AT', 'food') │  │  'AT','bev-na')│  │  'AT','bev-alc'│
│ = 10%          │  │ = 20%          │  │ = 20%          │
└───────┬────────┘  └───────┬────────┘  └──────┬─────────┘
        │                   │                   │
        └───────────────────┼───────────────────┘
                            │
                    ┌───────▼────────┐
                    │  Calculate     │
                    │  Net Amount    │
                    │  VAT Amount    │
                    │  per Category  │
                    └───────┬────────┘
                            │
                    ┌───────▼────────┐
                    │  Display       │
                    │  Receipt with  │
                    │  VAT Breakdown │
                    └────────────────┘
```

---

## Country Switch Flow

```
┌─────────────┐
│  Vendor in  │
│  Settings   │
└──────┬──────┘
       │
┌──────▼──────────┐
│ Currently: 🇦🇹  │
│  Austria        │
└──────┬──────────┘
       │
┌──────▼──────────┐
│  Clicks Germany │
│      🇩🇪         │
└──────┬──────────┘
       │
       ├────────────────────────────────────────┐
       │                                        │
┌──────▼──────────┐                  ┌──────────▼─────────┐
│  System Updates │                  │  Menu Items        │
│  VAT Rules:     │                  │  Automatically     │
│                 │                  │  Recalculate:      │
│  🍽 Food:       │                  │                    │
│  10% → 7%       │                  │  Item: Schnitzel   │
│                 │                  │  Was: 10% (AT)     │
│  🥤 Bev (NA):   │                  │  Now: 7% (DE)      │
│  20% → 19%      │                  │                    │
│                 │                  │  Tax Category:     │
│  🍺 Bev (Alc):  │                  │  'food' unchanged  │
│  20% → 19%      │                  └────────────────────┘
└─────────────────┘
```

---

## Tax Category Override Warning Flow

```
┌──────────────┐
│ Vendor Edits │
│    Item      │
└──────┬───────┘
       │
┌──────▼───────────┐
│ Item Category:   │
│ "Appetizers"     │
│ Default Tax Cat: │
│ 🍽 Food          │
└──────┬───────────┘
       │
┌──────▼──────────────┐
│ Vendor Changes Tax  │
│ Category to:        │
│ 🍺 Beverage (Alc)   │
└──────┬──────────────┘
       │
┌──────▼──────────────────────────────┐
│  ⚠️ WARNING DISPLAYED:              │
│  "Changing this may affect tax      │
│  compliance. Most items should use  │
│  the category default."             │
└──────┬──────────────────────────────┘
       │
       ├──────────────┬─────────────┐
       │              │             │
┌──────▼──────┐  ┌────▼─────┐  ┌────▼────┐
│ Vendor      │  │ Vendor   │  │ Vendor  │
│ Proceeds    │  │ Cancels  │  │ Changes │
│ Knowingly   │  │ Edit     │  │ Back to │
│             │  │          │  │ Default │
└──────┬──────┘  └──────────┘  └─────────┘
       │
┌──────▼──────┐
│ Save with   │
│ Override    │
│ (Logged)    │
└─────────────┘
```

---

## German VAT Split Flow (Combo Items)

```
┌────────────────┐
│  Restaurant    │
│  Country: 🇩🇪   │
└───────┬────────┘
        │
┌───────▼────────┐
│ Vendor Creates │
│  Combo Item:   │
│ "Lunch Menu"   │
│  Price: €10    │
└───────┬────────┘
        │
┌───────▼─────────────────┐
│  ⚖️ VAT Split Required  │
│                         │
│  German tax law         │
│  requires split for     │
│  food + beverage        │
└───────┬─────────────────┘
        │
┌───────▼─────────────┐
│  Vendor Enters:     │
│  🍽 Food: €7.50     │
│  🥤 Drink: €2.50    │
└───────┬─────────────┘
        │
        ├───────────────────────┐
        │                       │
┌───────▼──────┐      ┌─────────▼─────────┐
│  Validation  │      │  If Invalid:      │
│  €7.50 +     │      │  Show Error:      │
│  €2.50 =     │      │  "Split total     │
│  €10.00 ✅   │      │  must equal       │
└───────┬──────┘      │  item price"      │
        │             └───────────────────┘
┌───────▼──────┐
│  Calculate:  │
│  Food VAT:   │
│  €7.50 × 7%  │
│  = €0.53     │
│              │
│  Drink VAT:  │
│  €2.50 × 19% │
│  = €0.48     │
└───────┬──────┘
        │
┌───────▼──────┐
│  Receipt:    │
│  Food €7.50  │
│  (7% VAT)    │
│  Drink €2.50 │
│  (19% VAT)   │
│  ────────    │
│  Total €10   │
│  ✅ Compliant│
└──────────────┘
```

---

## Receipt Generation Flow

```
┌───────────────┐
│  Order Items  │
│               │
│  1. Schnitzel │─────▶ taxCategory: 'food'
│  2. Salad     │─────▶ taxCategory: 'food'
│  3. Cola      │─────▶ taxCategory: 'beverage-non-alcoholic'
│  4. Wine      │─────▶ taxCategory: 'beverage-alcoholic'
└───────┬───────┘
        │
┌───────▼─────────────────────────┐
│  Group by Tax Category + Rate   │
│                                 │
│  Group 1: food @ 10%            │
│    - Schnitzel €15.90           │
│    - Salad €8.50                │
│    Subtotal: €24.40             │
│    Net: €22.18                  │
│    VAT: €2.22                   │
│                                 │
│  Group 2: bev-na @ 20%          │
│    - Cola €2.50                 │
│    Net: €2.08                   │
│    VAT: €0.42                   │
│                                 │
│  Group 3: bev-alc @ 20%         │
│    - Wine €4.50                 │
│    Net: €3.75                   │
│    VAT: €0.75                   │
└───────┬─────────────────────────┘
        │
┌───────▼──────────────────────┐
│  Receipt Display:            │
│                              │
│  1× Schnitzel       €15.90  │
│  1× Salad            €8.50  │
│  1× Cola             €2.50  │
│  1× Wine             €4.50  │
│  ──────────────────────────  │
│  Net Total          €28.01  │
│  incl. VAT 10% (Food) €2.22 │
│  incl. VAT 20% (Drinks)€1.17│
│  ──────────────────────────  │
│  Total              €31.40  │
│                              │
│  ✅ Tax-compliant for Austria│
└──────────────────────────────┘
```

---

## Data Flow: Item Creation to Receipt

```
┌─────────────────────────────────────────────────────────────┐
│                      DATA LIFECYCLE                          │
└─────────────────────────────────────────────────────────────┘

1. CATEGORY CREATION
   ┌──────────────────┐
   │ category: {      │
   │   id: "mains",   │
   │   name: "Mains", │
   │   defaultTax     │
   │   Category:      │
   │   "food"         │
   │ }                │
   └─────────┬────────┘
             │
             ▼
2. ITEM CREATION
   ┌──────────────────┐
   │ item: {          │
   │   name: "Schnitz"│
   │   category:"main"│
   │   price: 15.90   │
   │   taxCategory:   │
   │   "food" (inherit)│
   │ }                │
   └─────────┬────────┘
             │
             ▼
3. CUSTOMER ORDER
   ┌──────────────────┐
   │ orderItem: {     │
   │   itemId: "..."  │
   │   quantity: 1    │
   │   price: 15.90   │
   │   taxCategory:   │
   │   "food"         │
   │ }                │
   └─────────┬────────┘
             │
             ▼
4. VAT CALCULATION (Runtime)
   ┌──────────────────┐
   │ country = "AT"   │
   │ taxCat = "food"  │
   │ ────────────     │
   │ vatRate =        │
   │ getVATRate(      │
   │   "AT", "food")  │
   │ = 10%            │
   └─────────┬────────┘
             │
             ▼
5. RECEIPT DISPLAY
   ┌──────────────────┐
   │ Schnitzel €15.90│
   │ Net: €14.45     │
   │ VAT 10%: €1.45  │
   │ ────────────    │
   │ Total: €15.90   │
   │ ✅ Tax-compliant│
   └─────────────────┘
```

---

## Decision Tree: Which Tax Category?

```
                    ┌──────────────────┐
                    │   What are you   │
                    │     selling?     │
                    └────────┬─────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
        ┌─────▼─────┐  ┌─────▼─────┐  ┌────▼────┐
        │   Food?   │  │  Drink?   │  │ Other?  │
        └─────┬─────┘  └─────┬─────┘  └────┬────┘
              │              │              │
        ┌─────▼─────┐        │        ┌─────▼────┐
        │   🍽      │        │        │ Default  │
        │   FOOD    │        │        │ to FOOD  │
        └───────────┘        │        └──────────┘
                             │
                    ┌────────▼────────┐
                    │  Alcoholic?     │
                    └────────┬────────┘
                             │
                    ┌────────┼────────┐
                    │                 │
              ┌─────▼─────┐     ┌─────▼─────┐
              │    YES    │     │    NO     │
              └─────┬─────┘     └─────┬─────┘
                    │                 │
            ┌───────▼────────┐  ┌─────▼────────┐
            │      🍺         │  │     🥤       │
            │   BEVERAGE      │  │  BEVERAGE    │
            │  (ALCOHOLIC)    │  │(NON-ALCOHOLIC)│
            └────────────────┘  └──────────────┘

EXAMPLES:
🍽 Food: Schnitzel, Burger, Salad, Pizza, Pasta, Dessert
🥤 Beverage (NA): Cola, Coffee, Tea, Juice, Water
🍺 Beverage (Alc): Beer, Wine, Cocktails, Spirits
```

---

## System State Diagram

```
┌────────────────────────────────────────────────────────────┐
│                     SYSTEM STATES                           │
└────────────────────────────────────────────────────────────┘

   ┌──────────────┐
   │   NO SETUP   │ (New vendor, no country set)
   └──────┬───────┘
          │ Vendor selects country
          ▼
   ┌──────────────┐
   │  CONFIGURED  │ (Country set, VAT rules loaded)
   └──────┬───────┘
          │ Create categories with tax categories
          ▼
   ┌──────────────┐
   │  CATEGORIES  │ (Categories exist with defaults)
   │    READY     │
   └──────┬───────┘
          │ Add items (inherit tax category)
          ▼
   ┌──────────────┐
   │    MENU      │ (Items classified, VAT auto-calculated)
   │   COMPLETE   │
   └──────┬───────┘
          │ Customer orders
          ▼
   ┌──────────────┐
   │   ORDER      │ (VAT calculated, receipt generated)
   │  PROCESSED   │
   └──────────────┘
          │
          ▼
   ┌──────────────┐
   │   INVOICE    │ (Tax-compliant invoice with breakdown)
   │  GENERATED   │
   └──────────────┘
```

---

## Error Handling Flow

```
                    ┌──────────────┐
                    │ User Action  │
                    └──────┬───────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
┌───────▼────────┐  ┌──────▼──────┐  ┌────────▼──────┐
│ Invalid        │  │ Missing     │  │  Network      │
│ Tax Category   │  │ Country     │  │  Error        │
└───────┬────────┘  └──────┬──────┘  └────────┬──────┘
        │                  │                  │
┌───────▼────────┐  ┌──────▼──────┐  ┌────────▼──────┐
│ Default to     │  │ Default to  │  │ Show error    │
│ 'food'         │  │ 'AT'        │  │ Retry option  │
│ Log warning    │  │ Log warning │  │ Preserve form │
└───────┬────────┘  └──────┬──────┘  └────────┬──────┘
        │                  │                  │
        └──────────────────┼──────────────────┘
                           │
                    ┌──────▼───────┐
                    │   Continue   │
                    │   Gracefully │
                    └──────────────┘
```

---

These flow diagrams provide a visual understanding of how the tax system works at every level, from architecture to individual user interactions.
