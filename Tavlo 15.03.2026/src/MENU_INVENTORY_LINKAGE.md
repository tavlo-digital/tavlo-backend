# Menu-Inventory Linkage Guide

## How Menu Items Connect to Inventory

The Tavlo system links menu items to inventory ingredients to enable:
1. **Automatic stock deduction** when orders are placed
2. **Menu availability management** when ingredients run out
3. **Recipe costing** based on ingredient prices
4. **Inventory forecasting** based on menu popularity

---

## Setting Up Ingredient Linkage

### In Menu Management

When you **create or edit a menu item**, you define its recipe by adding ingredients:

#### Step 1: Edit Menu Item
1. Go to **Menu Management**
2. Find the menu item (e.g., "Margherita Pizza")
3. Click the **Edit** button (pencil icon)

#### Step 2: Add Ingredients Section

In the edit dialog, scroll to the **"Recipe & Ingredients"** section:

```
┌─────────────────────────────────────────┐
│ Recipe & Ingredients                    │
├─────────────────────────────────────────┤
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ + Add Ingredient                    │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ Currently used ingredients:             │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 🧀 Tomatoes (Fresh)                 │ │
│ │    0.1 kg per serving               │ │
│ │    ☑ Critical  [Remove]             │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 🧀 Mozzarella                       │ │
│ │    0.15 kg per serving              │ │
│ │    ☑ Critical  [Remove]             │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─────────────────────────────────────┐ │
│ │ 🧀 Basil                            │ │
│ │    0.02 kg per serving              │ │
│ │    ☐ Critical  [Remove]             │ │
│ └─────────────────────────────────────┘ │
│                                         │
└─────────────────────────────────────────┘
```

#### Step 3: Add an Ingredient

Click **"+ Add Ingredient"** to open the ingredient picker:

**Ingredient Picker Dialog:**
```
┌────────────────────────────────────────┐
│ Add Ingredient to Recipe               │
├────────────────────────────────────────┤
│                                        │
│ Select Ingredient *                    │
│ ┌────────────────────────────────────┐ │
│ │ Tomatoes (Fresh)              ▼    │ │
│ └────────────────────────────────────┘ │
│                                        │
│ Quantity per Serving *                 │
│ ┌──────────────┬──────────────────────┐│
│ │ 0.1          │ kg                ▼ │││
│ └──────────────┴──────────────────────┘│
│                                        │
│ ☑ Critical Ingredient                  │
│ (Menu unavailable if out of stock)     │
│                                        │
│ ┌────────────────────────────────────┐ │
│ │ Current Stock: 25 kg               │ │
│ │ Cost Impact: €0.25 per serving     │ │
│ └────────────────────────────────────┘ │
│                                        │
│        [Cancel]  [Add Ingredient]      │
└────────────────────────────────────────┘
```

**Fields Explained:**

1. **Select Ingredient** (Required)
   - Dropdown showing all ingredients from your inventory
   - Only shows ingredients you've already added to inventory
   - If ingredient doesn't exist, you must add it to inventory first

2. **Quantity per Serving** (Required)
   - How much of this ingredient ONE serving uses
   - Example: Margherita Pizza uses 0.1 kg of tomatoes
   - Unit automatically matches the inventory unit

3. **Critical Ingredient** (Checkbox)
   - ☑ **Checked**: Menu item becomes unavailable if this ingredient is out
   - ☐ **Unchecked**: Menu stays available even if ingredient is out
   - Use for essential ingredients with no substitutes

4. **Live Feedback** (Info Panel)
   - Shows current stock of selected ingredient
   - Calculates cost impact per serving
   - Helps you verify the linkage is correct

---

## Understanding Critical vs Non-Critical

### Critical Ingredient (Red "Critical" badge)

**When to use:**
- Ingredient is essential to the dish
- No substitute is acceptable
- Examples:
  - Tomatoes in Margherita Pizza
  - Mozzarella in Margherita Pizza
  - Beef in Beef Burger

**What happens when out:**
- Menu item automatically marked as **"Unavailable"**
- Customers cannot order it
- Dashboard shows alert: "X items unavailable due to stock"

### Non-Critical Ingredient (No badge)

**When to use:**
- Ingredient is optional or has substitutes
- Can be omitted if necessary
- Examples:
  - Basil garnish (can be skipped)
  - Optional toppings
  - Side ingredients

**What happens when out:**
- Menu item remains **"Available"**
- Kitchen can prepare without it or substitute
- No customer-facing impact

---

## Example: Margherita Pizza Recipe

```
Menu Item: Margherita Pizza
Price: €12.00
Serves: 1

Ingredients:
┌──────────────────┬──────────┬──────────┬─────────────┐
│ Ingredient       │ Qty/Unit │ Critical │ Cost Impact │
├──────────────────┼──────────┼──────────┼─────────────┤
│ Tomatoes (Fresh) │ 0.1 kg   │ ✓ Yes    │ €0.25       │
│ Mozzarella       │ 0.15 kg  │ ✓ Yes    │ €1.34       │
│ Basil            │ 0.02 kg  │ ✗ No     │ €0.24       │
│ Pizza Dough      │ 0.3 kg   │ ✓ Yes    │ €0.45       │
│ Olive Oil        │ 0.01 L   │ ✗ No     │ €0.15       │
└──────────────────┴──────────┴──────────┴─────────────┘

Total Recipe Cost: €2.43
Profit Margin: €9.57 (79.8%)
```

When a customer orders 1 Margherita Pizza:
- Tomatoes: -0.1 kg
- Mozzarella: -0.15 kg
- Basil: -0.02 kg
- Pizza Dough: -0.3 kg
- Olive Oil: -0.01 L

---

## How Automatic Stock Deduction Works

### Prerequisite Settings

**Enable Auto-Deduction:**
1. Settings → Inventory → General Tab
2. Toggle ON: **"Enable Auto Stock Deduction"**

### Order Flow

```
Customer Orders          Stock Deduction           Inventory Updated
Margherita Pizza    →    -0.1 kg Tomatoes    →    Tomatoes: 50 → 49.9 kg
(Quantity: 2)            -0.15 kg Mozzarella       Mozzarella: 25 → 24.7 kg
                         -0.02 kg Basil            Basil: 10 → 9.96 kg
                         (× 2 servings)            (All in real-time)
```

### Viewing the Deduction

**In Stock Activity Log:**
```
┌────────────────────────────────────────────────┐
│ Stock Activity Log - Tomatoes (Fresh)          │
├────────────────────────────────────────────────┤
│                                                │
│ [Order] -0.2 kg                                │
│ Order #1234 - Margherita Pizza (qty: 2)        │
│ Jan 1, 2026 • Auto                             │
│                                                │
│ [Manual] +50 kg                                │
│ Delivery - Fresh Foods Co                      │
│ Jan 1, 2026 • Manager                          │
└────────────────────────────────────────────────┘
```

Each order deduction is logged with:
- Source: **Order** badge
- Order ID and menu item name
- Quantity deducted
- Timestamp and "Auto" as user

---

## Menu Availability Rules

### Scenario 1: Critical Ingredient Out

**Current Stock:**
- Tomatoes: 0 kg (OUT OF STOCK)
- Mozzarella: 10 kg (In Stock)

**Menu Status:**
- ❌ Margherita Pizza: **Unavailable**
- ❌ Pasta Pomodoro: **Unavailable** (also uses tomatoes critically)
- ✅ Caprese Salad: **Available** (tomatoes non-critical)

**Customer View:**
- Margherita Pizza shows "Currently Unavailable"
- Pasta Pomodoro shows "Currently Unavailable"
- Caprese Salad shows normally (kitchen omits tomatoes)

### Scenario 2: Non-Critical Ingredient Out

**Current Stock:**
- Basil: 0 kg (OUT OF STOCK)
- Tomatoes: 50 kg (In Stock)
- Mozzarella: 10 kg (In Stock)

**Menu Status:**
- ✅ Margherita Pizza: **Available** (basil is non-critical)
- ✅ Caprese Salad: **Available**

**Kitchen Note:**
- Prepare without basil or substitute with parsley
- Customers can still order
- No system-level blocking

### Scenario 3: Multiple Critical Ingredients Out

**Current Stock:**
- Tomatoes: 0 kg (OUT)
- Mozzarella: 0 kg (OUT)

**Menu Status:**
- ❌ Margherita Pizza: **Unavailable**
- ❌ Caprese Salad: **Unavailable**
- ❌ Pasta Pomodoro: **Unavailable**

**Dashboard Alert:**
- "⚠️ 3 menu items unavailable due to stock shortages"
- Links to inventory page for quick action

---

## Ingredient Detail Modal - Affected Menu Items

When you open an ingredient (e.g., Tomatoes) in the inventory:

```
┌─────────────────────────────────────────────────┐
│ Tomatoes (Fresh)                    [Low Stock] │
├─────────────────────────────────────────────────┤
│                                                 │
│ Affected Menu Items                             │
│ If this ingredient runs out, these items will   │
│ be auto-unavailable.                            │
│                                                 │
│ ┌─────────────────────────────────────────────┐ │
│ │ 🍕 Margherita Pizza           [Critical]    │ │
│ │    Uses 0.1 kg per serving                  │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│ ┌─────────────────────────────────────────────┐ │
│ │ 🥗 Caprese Salad                            │ │
│ │    Uses 0.15 kg per serving                 │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│ ┌─────────────────────────────────────────────┐ │
│ │ 🍝 Pasta Pomodoro             [Critical]    │ │
│ │    Uses 0.2 kg per serving                  │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│ ⚠️ 2 menu items currently unavailable due to    │
│    this stock shortage.                         │
└─────────────────────────────────────────────────┘
```

**This screen shows:**
1. All menu items using this ingredient
2. Quantity used per serving
3. Which are marked as critical (red badge)
4. Real-time impact if ingredient is out

**Where this data comes from:**
- Set when editing each menu item in Menu Management
- Automatically calculated from all menu items' recipes
- Updated in real-time when you change recipes

---

## Recipe Costing & Profit Analysis

### Automatic Cost Calculation

When ingredients are linked, the system automatically calculates:

**Per-Dish Cost:**
```
Margherita Pizza

Ingredient Costs:
- Tomatoes: 0.1 kg × €2.50/kg = €0.25
- Mozzarella: 0.15 kg × €8.90/kg = €1.34
- Basil: 0.02 kg × €12.00/kg = €0.24
- Pizza Dough: 0.3 kg × €1.50/kg = €0.45
- Olive Oil: 0.01 L × €15.00/L = €0.15
──────────────────────────────────────
Total Recipe Cost: €2.43

Selling Price: €12.00
Gross Profit: €9.57
Margin: 79.8%
```

### Viewing Costing Data

**In Menu Management:**
- Each menu item shows calculated cost
- Real-time profit margin percentage
- Updates automatically when ingredient prices change

**In Analytics:**
- Most profitable items report
- Cost vs revenue breakdown
- Margin trends over time

---

## Setting Up Your First Recipe

### Step-by-Step Walkthrough

**Goal:** Link Margherita Pizza to inventory ingredients

#### 1. Ensure Ingredients Exist in Inventory

First, verify all ingredients are in inventory:
- Go to **Inventory** tab
- Check for: Tomatoes, Mozzarella, Basil, Pizza Dough, Olive Oil
- If missing, add them via "Add Item" or Excel import

#### 2. Open Menu Item Editor

- Go to **Menu Management**
- Find "Margherita Pizza"
- Click **Edit** (pencil icon)

#### 3. Scroll to Recipe Section

- Scroll down in the edit dialog
- Find **"Recipe & Ingredients"** section

#### 4. Add Each Ingredient

**For Tomatoes:**
1. Click "+ Add Ingredient"
2. Select "Tomatoes (Fresh)" from dropdown
3. Enter quantity: `0.1`
4. Unit auto-fills as `kg`
5. Check ☑ "Critical Ingredient"
6. Click "Add Ingredient"

**For Mozzarella:**
1. Click "+ Add Ingredient"
2. Select "Mozzarella"
3. Enter quantity: `0.15`
4. Check ☑ "Critical"
5. Click "Add"

**For Basil:**
1. Click "+ Add Ingredient"
2. Select "Basil"
3. Enter quantity: `0.02`
4. Leave ☐ "Critical" unchecked (optional garnish)
5. Click "Add"

**Repeat for remaining ingredients...**

#### 5. Save Menu Item

- Review all ingredients listed
- Click "Save Changes"
- Success toast: "Menu item updated"

#### 6. Verify Linkage

**Test in Inventory:**
1. Go to **Inventory** tab
2. Click on "Tomatoes (Fresh)"
3. Scroll to "Affected Menu Items"
4. Should see "Margherita Pizza - Uses 0.1 kg per serving - [Critical]"

**Test Auto-Deduction:**
1. Set Tomatoes stock to a known value (e.g., 50 kg)
2. Go to **Orders** and create a test order for 1 Margherita Pizza
3. Return to Inventory → Tomatoes
4. Stock should now be 49.9 kg
5. Activity log shows: "[Order] -0.1 kg - Order #XXX"

---

## Common Issues & Solutions

### Issue 1: Menu Item Not Showing in "Affected Menu Items"

**Cause:** Ingredient not linked to menu item recipe

**Solution:**
1. Go to Menu Management
2. Edit the menu item
3. Add the ingredient in Recipe section
4. Save changes

### Issue 2: Stock Not Deducting on Orders

**Causes:**
- Auto-deduction disabled in settings
- Ingredients not linked to menu item
- Order was placed before linkage

**Solutions:**
1. Enable: Settings → Inventory → General → "Enable Auto Stock Deduction"
2. Verify ingredient linkage in Menu Management
3. Only new orders will trigger deduction

### Issue 3: Wrong Quantity Deducted

**Cause:** Incorrect quantity set in recipe

**Solution:**
1. Menu Management → Edit menu item
2. Find ingredient in recipe list
3. Click "Edit" next to ingredient
4. Update quantity
5. Save - future orders will use new quantity

### Issue 4: Can't Find Ingredient in Dropdown

**Cause:** Ingredient doesn't exist in inventory yet

**Solution:**
1. Go to Inventory tab first
2. Add the ingredient via "Add Item"
3. Return to Menu Management
4. Ingredient now appears in dropdown

### Issue 5: Menu Unavailable But Ingredient In Stock

**Causes:**
- Multiple ingredients required
- One critical ingredient is out
- Availability rules misconfigured

**Solutions:**
1. Check ALL critical ingredients for that menu item
2. Inventory → Click menu item name to see all ingredients
3. Verify each critical ingredient has stock
4. Settings → Inventory → Availability Rules → Verify enabled

---

## Best Practices

### 1. Mark Essentials as Critical

**Do mark as critical:**
- Primary proteins (beef in burger, salmon in salmon dish)
- Base sauces with no substitute (tomato sauce in pasta pomodoro)
- Signature ingredients (truffle in truffle risotto)

**Don't mark as critical:**
- Garnishes (parsley, chives)
- Optional toppings (extra cheese)
- Easily substitutable items (one type of lettuce vs another)

### 2. Be Accurate with Quantities

- Use exact measurements, not estimates
- Measure ingredient quantity in finished dish
- Account for cooking loss/waste
- Example: Raw chicken 0.2 kg → Cooked 0.15 kg → Use 0.2 kg

### 3. Keep Recipes Updated

- When you change a recipe in kitchen, update system
- Review quarterly for accuracy
- Document portion size changes
- Consistency helps with inventory prediction

### 4. Use Consistent Units

- Don't mix kg and g for same ingredient type
- Choose one unit and stick to it
- Makes recipe calculations clearer
- Easier for kitchen staff

### 5. Test Linkages

After setting up:
1. Place test order
2. Verify stock deduction
3. Check activity log
4. Confirm amounts are correct

---

## Advanced: Bulk Recipe Upload

For restaurants with many menu items, you can upload recipes via CSV:

**CSV Format:**
```csv
Menu Item Name,Ingredient Name,Quantity,Unit,Critical
Margherita Pizza,Tomatoes (Fresh),0.1,kg,Yes
Margherita Pizza,Mozzarella,0.15,kg,Yes
Margherita Pizza,Basil,0.02,kg,No
Margherita Pizza,Pizza Dough,0.3,kg,Yes
Caprese Salad,Tomatoes (Fresh),0.15,kg,No
Caprese Salad,Mozzarella,0.2,kg,Yes
```

**Upload Process:**
1. Menu Management → Click "Import Recipes"
2. Upload CSV file
3. Map columns (auto-detected)
4. Review linkages
5. Confirm import

**Requirements:**
- Menu items must already exist
- Ingredients must exist in inventory
- Quantities must be numeric
- Units must match inventory units

---

## Summary

**To make the system know "Margherita Pizza uses 0.1 kg of Tomatoes":**

1. ✅ Add "Tomatoes" to **Inventory** first
2. ✅ Go to **Menu Management**
3. ✅ Edit "Margherita Pizza"
4. ✅ In "Recipe & Ingredients" section, click "+ Add Ingredient"
5. ✅ Select "Tomatoes (Fresh)", enter `0.1 kg`, mark as Critical
6. ✅ Save the menu item

**Result:**
- Orders deduct 0.1 kg per pizza automatically
- When tomatoes hit 0 kg, pizza becomes unavailable
- Ingredient detail shows pizza uses 0.1 kg per serving
- Recipe cost is calculated automatically

---

**Next:** See [INVENTORY_DOCUMENTATION.md](./INVENTORY_DOCUMENTATION.md) for full inventory features.
