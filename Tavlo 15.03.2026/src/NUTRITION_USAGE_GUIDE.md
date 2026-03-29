# Nutrition Automation System - Usage Guide

## Overview

The nutrition automation system calculates dish nutrition values automatically from ingredient data. This eliminates manual guessing and ensures accuracy.

## How to Use

### 1. Add Ingredients with Nutrition Data

**Location:** Vendor Dashboard → Inventory → Add Ingredient

**Steps:**
1. Click "Add Ingredient"
2. Enter ingredient name
3. In "Search nutrition database" field, type the ingredient name (e.g., "tomato")
4. Select from dropdown results (sourced from Open Food Facts)
5. Nutrition fields auto-fill with database values
6. Badge shows "Database" source
7. You can manually edit values if needed (changes source to "Custom")
8. Save ingredient

**Manual Entry:**
- If no database results found, enter nutrition values manually
- Values are per 100g/100ml/piece depending on unit
- Source badge will show "Custom"

---

### 2. Link Ingredients to Dishes

**Location:** Vendor Dashboard → Menu → Edit Item → Recipe & Ingredients

**Steps:**
1. Click "Add Ingredient" in recipe section
2. Select ingredient from dropdown
3. Enter quantity (e.g., 200g)
4. Save

The ingredient now contributes to automatic nutrition calculation.

---

### 3. View Auto-Calculated Nutrition

**Location:** Menu → Edit Item → Nutrition Section

**What You See:**
- Section title: "Nutrition (Automatically Calculated)"
- Badge: "Auto-calculated"
- Read-only values for: Calories, Fat, Protein, Carbs
- Values update instantly when you change ingredient quantities

**View Breakdown:**
- Click "View nutrition breakdown by ingredient"
- See per-ingredient contribution to total

---

### 4. Manual Override (Optional)

**When to Use:**
- You have more accurate lab-tested nutrition data
- Recipe preparation changes nutrition (cooking methods, etc.)
- You need to temporarily override values

**Steps:**
1. In nutrition section, find toggle: "Override nutrition manually"
2. Click to enable
3. Fields become editable
4. Yellow warning appears: "Manual override active"
5. Badge changes to "Manual"
6. Enter your values

**Important:** When manual override is active, ingredient changes will NOT update nutrition automatically.

To return to automatic calculation, toggle off the override.

---

## System Rules

1. **Ingredient nutrition is stored per 100g/100ml/piece**
   - This is standard in nutrition databases
   - Makes calculations consistent

2. **Dish nutrition is calculated from ingredient quantities**
   - Formula: (ingredient_nutrition_per_100g / 100) × quantity_in_grams
   - For pieces: ingredient_nutrition_per_piece × quantity

3. **Rounding:**
   - Calories: whole numbers
   - Macros (fat, protein, carbs): 1 decimal place

4. **Source tracking:**
   - "Database": from Open Food Facts
   - "Custom": manually entered by vendor
   - "Auto-calculated": computed from ingredients
   - "Manual": manually overridden by vendor

---

## Tips

✅ **Do:**
- Search for ingredients in database before manual entry
- Link ingredients to enable automatic calculation
- Use realistic quantities in recipes
- Review breakdown to verify accuracy

❌ **Don't:**
- Mix units inconsistently (use g/ml for precision)
- Override without reason (loses automatic updates)
- Forget to update ingredient nutrition when recipes change

---

## Example Workflow

**Scenario:** Adding "Grilled Chicken Salad"

1. **Add Ingredients:**
   - Search "chicken breast" → Select from database → 165 kcal/100g
   - Search "tomato" → Select from database → 18 kcal/100g
   - Search "lettuce" → Select from database → 15 kcal/100g
   - Search "olive oil" → Select from database → 884 kcal/100g

2. **Create Dish:**
   - Go to Menu → Add New Item
   - Name: "Grilled Chicken Salad"
   - Add ingredients:
     - Chicken breast: 200g
     - Tomato: 100g
     - Lettuce: 50g
     - Olive oil: 10ml

3. **View Results:**
   - Auto-calculated nutrition:
     - Calories: (165×2) + (18×1) + (15×0.5) + (884×0.1) = **445 kcal**
     - Fat, protein, carbs calculated same way
   - Click "View breakdown" to see per-ingredient contribution
   - Values update if you change quantities

4. **Save:**
   - Dish is now saved with auto-calculated nutrition
   - Future changes to chicken nutrition will auto-update this dish

---

## Troubleshooting

**Q: Nutrition shows 0 for all values**
A: Ingredient doesn't have nutrition data. Edit ingredient and add nutrition values.

**Q: Can't find ingredient in database**
A: Try different spellings, or enter manually with "Custom" source.

**Q: Nutrition doesn't update when I change quantities**
A: Check if manual override is enabled (yellow warning). Disable to return to automatic.

**Q: Values seem incorrect**
A: Click "View breakdown" to see per-ingredient contributions. Verify ingredient nutrition values.

---

## Info Tooltip

Throughout the system, you'll see this tooltip:

> "Nutrition is calculated from standard ingredient data per 100g. Values are estimates."

This reminds that nutrition values are estimates based on standard data, not lab-tested for your specific recipe.

---

**Last Updated:** January 2026  
**System Version:** 1.0
