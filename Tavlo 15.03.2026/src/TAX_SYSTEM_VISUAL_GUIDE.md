# Tavlo Tax System - Visual Guide

## Before vs. After Comparison

### 1. Settings → Tax & Receipts

#### ❌ BEFORE (Vendor-Editable VAT)
```
┌─────────────────────────────────────────┐
│ Tax Configuration                        │
│                                          │
│ VAT Rate (%)        Service Fee Rate (%) │
│ [___13___]          [___5___]           │
│ Standard VAT rate                        │
│ in Austria: 13%                          │
└─────────────────────────────────────────┘
```
**Problem:** Vendors could enter any percentage, causing compliance issues.

---

#### ✅ AFTER (System-Controlled VAT)
```
┌──────────────────────────────────────────────────┐
│ Country & Tax Rules                               │
│                                                   │
│ Restaurant Country                                │
│ ┌──────────────┐  ┌──────────────┐              │
│ │ 🇦🇹 Austria   │  │ 🇩🇪 Germany   │              │
│ │ ✓ Selected   │  │              │              │
│ └──────────────┘  └──────────────┘              │
│                                                   │
│ Applied VAT Rules (Read-Only)                    │
│ ┌────────────┐ ┌────────────┐ ┌────────────┐   │
│ │ 🍽 Food     │ │ 🥤 Drinks   │ │ 🍺 Alcohol  │   │
│ │ Prepared   │ │ Non-Alc     │ │ Alcoholic  │   │
│ │ meals      │ │ beverages   │ │ beverages  │   │
│ │            │ │             │ │            │   │
│ │ 10% VAT    │ │ 20% VAT     │ │ 20% VAT    │   │
│ └────────────┘ └────────────┘ └────────────┘   │
│                                                   │
│ ℹ️  VAT rates are defined by local tax law       │
│    and automatically applied by Tavlo.           │
│    You classify your items by tax category,      │
│    and Tavlo ensures legal compliance.           │
│                                                   │
│ ✅ Loaded from Tavlo Tax Rules Database          │
└──────────────────────────────────────────────────┘
```
**Solution:** Country-based, read-only VAT rules with clear authority.

---

### 2. Menu → Add/Edit Category

#### ❌ BEFORE
```
┌─────────────────────────────┐
│ Add New Category             │
│                              │
│ Category Name                │
│ [___________________]        │
│                              │
│      [Cancel]  [Add]         │
└─────────────────────────────┘
```

---

#### ✅ AFTER
```
┌─────────────────────────────────────────────┐
│ Add New Category                             │
│                                              │
│ Category Name                                │
│ [___Appetizers___]                          │
│                                              │
│ Tax Category (Default)                       │
│ ┌──────────────────────────────────┐        │
│ │ 🍽 Food                           │        │
│ │ Prepared meals, main dishes,     │        │
│ │ appetizers, desserts             │        │
│ └──────────────────────────────────┘        │
│                                              │
│ All items in this category will inherit     │
│ this tax classification. Tax categories     │
│ determine VAT automatically based on your   │
│ country.                                     │
│                                              │
│          [Cancel]  [Add Category]            │
└─────────────────────────────────────────────┘
```
**New Feature:** Every category has a default tax classification.

---

### 3. Menu → Edit Item (Tax Section)

#### ❌ BEFORE
```
┌─────────────────────────────┐
│ Tax Information              │
│                              │
│ VAT Rate (%)                 │
│ [___20___]                   │
│                              │
└─────────────────────────────┘
```
**Problem:** Free-form input, no context, easy to make mistakes.

---

#### ✅ AFTER
```
┌──────────────────────────────────────────────────┐
│ Tax Information                                   │
│                                                   │
│ Tax Category                                      │
│ ┌───────────────────────────────────────────┐    │
│ │ 🍽 Food                                    │    │
│ │    Prepared meals, main dishes,           │    │
│ │    appetizers, desserts                   │    │
│ └───────────────────────────────────────────┘    │
│                                                   │
│ ┌───────────────────────────────────────────┐    │
│ │ Applied VAT Rate                           │    │
│ │ 10% (Austria – Food)                       │    │
│ └───────────────────────────────────────────┘    │
│                                                   │
│ ⚠️  Changing this may affect tax compliance.     │
│    Most items should use the category default.   │
└──────────────────────────────────────────────────┘
```
**Improvement:** Clear classification, automatic rate calculation, compliance warnings.

---

### 4. Receipt Preview (NEW)

```
┌────────────────────────────────┐
│ 📄 Receipt Preview              │
│                                 │
│ 1× Wiener Schnitzel    €15.90  │
│ 1× Caesar Salad         €8.50  │
│ 2× Coca Cola            €5.00  │
│ ────────────────────────────── │
│ Net Total              €26.55  │
│ incl. VAT 10% (Food)    €2.23  │
│ incl. VAT 20% (Drinks)  €0.82  │
│ ────────────────────────────── │
│ Total                  €29.60  │
│                                 │
│ ✅ Tax-compliant for Austria    │
└────────────────────────────────┘
```
**Trust Builder:** Shows customers exactly how VAT is calculated.

---

### 5. German Combo Items (NEW)

```
┌────────────────────────────────────────────┐
│ ⚖️  VAT Split Required                      │
│                                             │
│ German tax law requires VAT to be split    │
│ for food and drinks sold together.         │
│                                             │
│ 🍽 Food Component     🥤 Beverage Component │
│ €[__7.50__]          €[__2.50__]          │
│ VAT: 7%              VAT: 19%              │
│                                             │
│ ✅ Split matches total price: €10.00       │
└────────────────────────────────────────────┘
```
**Compliance:** Forces correct VAT split for German restaurants.

---

## Tax Category Options

When selecting a tax category, vendors see:

```
┌─────────────────────────────────────────────┐
│ Select Tax Category                          │
├─────────────────────────────────────────────┤
│ ⬇️ Inherit from category (Food)             │
├─────────────────────────────────────────────┤
│ 🍽 Food                                      │
│    Prepared meals, main dishes,             │
│    appetizers, desserts                     │
├─────────────────────────────────────────────┤
│ 🥤 Beverage (Non-Alcoholic)                 │
│    Soft drinks, juices, water,              │
│    coffee, tea                              │
├─────────────────────────────────────────────┤
│ 🍺 Beverage (Alcoholic)                     │
│    Beer, wine, spirits, cocktails           │
└─────────────────────────────────────────────┘
```

---

## VAT Rates by Country

### Austria 🇦🇹
| Category | Icon | VAT Rate | Description |
|----------|------|----------|-------------|
| Food | 🍽 | **10%** | Prepared meals and food products |
| Non-Alcoholic | 🥤 | **20%** | Soft drinks, juices, water, coffee |
| Alcoholic | 🍺 | **20%** | Beer, wine, spirits |

### Germany 🇩🇪
| Category | Icon | VAT Rate | Description |
|----------|------|----------|-------------|
| Food | 🍽 | **7%** | Prepared meals and food products |
| Non-Alcoholic | 🥤 | **19%** | Soft drinks, juices, water, coffee |
| Alcoholic | 🍺 | **19%** | Beer, wine, spirits |

---

## User Journey: Adding a New Menu Item

### Step 1: Select Category
```
Category: [Appetizers ▼]
         (Default tax category: 🍽 Food)
```

### Step 2: Tax Category Auto-Set
```
Tax Category: 🍽 Food
             (Inherited from Appetizers)
```

### Step 3: See Applied Rate
```
Applied VAT Rate
10% (Austria – Food)
```

### Step 4: Optional Override
```
If vendor changes to 🍺 Beverage (Alcoholic):

⚠️ Warning: Changing this may affect tax compliance.
   Most items should use the category default.

Applied VAT Rate
20% (Austria – Alcoholic Beverages)
```

---

## Design Language

### Colors
- **Trust/Compliance**: Green (#10b981)
- **Warning**: Amber (#f59e0b)
- **Info**: Blue (#3b82f6)
- **Error**: Red (#ef4444)
- **Neutral**: Gray (#6b7280)

### Icons
- ✅ Compliance confirmed
- ⚠️ Warning/caution
- ℹ️ Information
- 📄 Receipt/document
- 🍽 Food
- 🥤 Non-alcoholic beverage
- 🍺 Alcoholic beverage
- 🇦🇹 Austria
- 🇩🇪 Germany
- ⚖️ Balance/split

### Typography
- System-controlled values: **Monospace font**
- User input: Regular sans-serif
- Read-only displays: Gray background, subtle border

---

## Key Microcopy

### Authority Messages
> "VAT rates are defined by local tax law and automatically applied by Tavlo."

> "Loaded from Tavlo Tax Rules Database"

> "You classify your items by tax category, and Tavlo ensures legal compliance."

### Helper Text
> "All items in this category will inherit this tax classification."

> "Tax categories determine VAT automatically based on your country."

### Warnings
> "Changing this may affect tax compliance. Most items should use the category default."

> "German tax law requires VAT to be split for food and drinks sold together."

### Trust Badges
> "✅ Tax-compliant for Austria"

> "✅ Tax-compliant for Germany"

---

## Summary

The redesigned system transforms complex tax compliance into simple product classification:

1. **Vendor selects country** → System loads appropriate tax rules
2. **Vendor creates categories** → Assigns tax classification to each
3. **Vendor adds items** → Inherits tax category from menu category
4. **System calculates VAT** → Automatically, based on country + category
5. **Receipts show breakdown** → Full transparency for customers

**Result:** Zero room for error, full legal compliance, maximum trust.
