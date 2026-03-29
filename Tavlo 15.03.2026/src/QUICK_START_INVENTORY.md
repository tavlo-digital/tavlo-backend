# Quick Start: Inventory & Menu Linkage

## The Question: How Does the System Know "Margherita Pizza Uses 0.1 kg of Tomatoes"?

### Short Answer
You set this in **Menu Management** when editing the menu item's recipe.

---

## 5-Step Setup

### 1️⃣ Add Ingredient to Inventory First
```
Go to: Inventory Tab → Add Item

Name: Tomatoes (Fresh)
Category: Vegetables
Unit: kg
Current Stock: 50
Reorder Level: 20
Supplier: Fresh Foods Co
Cost per Unit: €2.50

[Save]
```

### 2️⃣ Edit Your Menu Item
```
Go to: Menu Management → Find "Margherita Pizza" → Click Edit
```

### 3️⃣ Scroll to Recipe Section
```
In the edit dialog, find:
┌─────────────────────────────────┐
│ Recipe & Ingredients            │
│                                 │
│ [+ Add Ingredient]              │
└─────────────────────────────────┘
```

### 4️⃣ Link the Ingredient
```
Click "+ Add Ingredient"

┌────────────────────────────────┐
│ Select Ingredient:             │
│ [Tomatoes (Fresh)        ▼]    │
│                                │
│ Quantity per Serving:          │
│ [0.1] [kg ▼]                   │
│                                │
│ ☑ Critical Ingredient          │
│                                │
│ [Add Ingredient]               │
└────────────────────────────────┘
```

### 5️⃣ Verify the Linkage
```
Go to: Inventory → Click "Tomatoes (Fresh)"

You'll see in "Affected Menu Items":
┌─────────────────────────────────────┐
│ 🍕 Margherita Pizza    [Critical]   │
│    Uses 0.1 kg per serving          │
└─────────────────────────────────────┘
```

---

## What Happens Next?

### When Customer Orders 1 Pizza:
- ✅ Stock automatically deducts: 50 kg → 49.9 kg
- ✅ Activity log records: "Order #1234 - Margherita Pizza"
- ✅ Recipe cost calculated: €0.25 for tomatoes

### When Tomatoes Hit 0 kg:
- ⚠️ Margherita Pizza becomes "Unavailable"
- 🚫 Customers can't order it
- 📧 You get low stock alert

### When You Restock:
- ➕ Adjust Stock → Type: "Delivery" → +50 kg
- ✅ Pizza becomes available again
- 📝 Logged in activity history

---

## Key Concepts

### Critical vs Non-Critical

**Critical ✓**
- Essential ingredient (Tomatoes, Mozzarella, Dough)
- Pizza unavailable if out
- Customer sees "Currently Unavailable"

**Non-Critical ✗**
- Optional garnish (Basil)
- Pizza stays available
- Kitchen prepares without it

---

## Full Example: Complete Pizza Recipe

```
Menu Item: Margherita Pizza (€12.00)

Ingredients:
┌─────────────────┬─────────┬──────────┬──────────┐
│ Ingredient      │ Qty     │ Critical │ Cost     │
├─────────────────┼─────────┼──────────┼──────────┤
│ Tomatoes        │ 0.1 kg  │ ✓ Yes    │ €0.25    │
│ Mozzarella      │ 0.15 kg │ ✓ Yes    │ €1.34    │
│ Pizza Dough     │ 0.3 kg  │ ✓ Yes    │ €0.45    │
│ Basil           │ 0.02 kg │ ✗ No     │ €0.24    │
│ Olive Oil       │ 0.01 L  │ ✗ No     │ €0.15    │
└─────────────────┴─────────┴──────────┴──────────┘

Recipe Cost: €2.43
Selling Price: €12.00
Profit: €9.57 (79.8%)
```

When 2 pizzas are ordered:
- Tomatoes: -0.2 kg
- Mozzarella: -0.3 kg
- Dough: -0.6 kg
- Basil: -0.04 kg
- Olive Oil: -0.02 L

All automatic. All tracked.

---

## Common Mistakes

❌ **Adding ingredient in inventory but not linking in menu**
→ Stock won't deduct automatically

❌ **Wrong quantity in recipe (0.1 vs 1.0)**
→ Stock deducts too much or too little

❌ **Using different units (kg in inventory, g in recipe)**
→ System can't match, won't deduct

❌ **Forgetting to mark as critical**
→ Menu stays available when ingredient is out

---

## Where to Go Next

📖 **Complete Guides:**
- [MENU_INVENTORY_LINKAGE.md](./MENU_INVENTORY_LINKAGE.md) - Full linkage documentation
- [INVENTORY_DOCUMENTATION.md](./INVENTORY_DOCUMENTATION.md) - Complete inventory features

🎯 **Key Pages:**
- **Menu Management** - Set up recipes
- **Inventory** - Track stock levels
- **Settings → Inventory** - Configure automation

---

## Need Help?

**Can't find ingredient in dropdown?**
→ Add it to Inventory first

**Stock not deducting?**
→ Settings → Inventory → Enable "Auto Stock Deduction"

**Menu still available when ingredient is out?**
→ Edit menu item → Mark ingredient as "Critical"

**Wrong amount deducted?**
→ Edit menu item → Update quantity in recipe

---

**Quick Reference Card**

```
WHERE:    Menu Management → Edit Item → Recipe Section
WHAT:     Link ingredients + Set quantities + Mark critical
WHY:      Enables auto-deduction + Menu management + Costing
HOW:      + Add Ingredient → Select → Quantity → ☑ Critical
VERIFY:   Inventory → Click ingredient → See affected items
```
