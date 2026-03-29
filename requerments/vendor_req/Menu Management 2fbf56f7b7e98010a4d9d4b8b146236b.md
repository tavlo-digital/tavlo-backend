# Menu Management

The **Menu Management** tab allows vendors to create, edit, organize, and manage their restaurant menu, including categories, dishes, pricing, customization options, and inventory connections.

The vendor can:

1. Create a new category
2. Edit an existing category
3. Delete an existing category
4. Create a new menu item (dish)
5. Edit an existing menu item
6. Upload the full menu using bulk upload (Excel / CSV)

---

# 1. Create a New Category

The vendor can create menu categories to organize dishes.

Examples:

- Appetizers
- Main Dishes
- Salads
- Sandwiches
- Drinks
- Desserts

### Category Fields

**Category Name**

Vendor enters the category name.

**Tax Category (Dropdown)**

Vendor selects the tax category applied to all items in this category.

Example options:

- Food
- Beverage (Non-Alcoholic)
- Beverage (Alcoholic)

### Tax Rules

Taxes are **configured per country and cannot be overridden by vendors**.

Example (Austria):

Food → 10%

Drinks → 20%

The system automatically applies the correct VAT based on:

Country → Tax Category

---

# 2. Edit an Existing Category

Vendor can edit:

- Category name
- Tax category

When the tax category changes, the VAT applied to items within that category updates automatically.

---

# 3. Delete an Existing Category

Deletion rules:

- Built-in system categories cannot be deleted.
- Vendor-created categories can be deleted.

However:

A category **cannot be deleted if it contains menu items**.

If attempted, the system shows a message:

"Category cannot be deleted because menu items are assigned to it."

---

# 4. Create a New Menu Item (Dish)

Vendor can add a new dish to the menu.

---

# Basic Information

### Item Name

Example:

- Pizza Margherita
- Grilled Salmon
- Mansaf Karaky

### Category

Dropdown containing existing categories.

### Price

Base price of the dish.

Example:

12.50 €

### Availability Toggle

Vendor can mark the item as:

Available

Unavailable (sold out)

### Description

Short description of the dish.

Example:

"Grilled lamb meat with oriental spices served with seasonal vegetables and lemon butter sauce."

### Dish Image

Vendor uploads one image.

Supported formats:

PNG

JPG

GIF

Maximum size: 5MB.

---

# Nutrition Information

Fields:

- Calories (kcal)
- Fat (g)
- Carbs (g)
- Protein (g)

Two calculation methods:

**Phase 1**

Vendor enters values manually.

**Phase 2**

Nutrition calculated automatically based on recipe ingredients.

---

# Dietary Information

Vendor selects dietary preference.

Examples:

- None
- Vegetarian
- Vegan
- Pescetarian

---

# Allergens (Multi-Select)

Vendor selects applicable allergens.

Examples:

- Gluten
- Dairy
- Nuts
- Eggs
- Peanuts
- Sesame
- Soy
- Fish

---

# Special Tags

Vendor can apply tags to highlight dishes.

Examples:

- Recommended
- Chef’s Pick
- Today’s Special
- Organic / Bio
- Halal

---

# Special Discount

Vendor can apply a temporary discount.

Example:

Original price: 12 €

Discount: 20%

Displayed price becomes:

9.60 €

---

# Customization Options (Dish Modifiers)

Menu items may include optional modifications.

Three types currently supported in the UI.

---

## Paid Add-Ons (Extra Charge)

Customer adds extra items with additional cost.

Example:

Extra Cheese → 2.50 €

Extra Meat → 4.00 €

Gluten Free Bun → 3.50 €

Vendor defines:

- Name
- Price

Maximum allowed: **10 items**

---

## Free Add-Ons (No Charge)

Optional items without extra cost.

Examples:

Extra Ketchup

Extra Mayo

Extra Basil

Maximum allowed: **10 items**

---

## Removable Items

Ingredients customers can remove from the dish.

Example:

Remove Onions

Remove Garlic

Remove Tomatoes

Maximum allowed: **10 items**

---

# Recipe & Ingredients (Inventory Integration)

This section links menu items with inventory ingredients.

This feature is **optional**.

Benefits:

- Nutrition calculation
- Stock tracking
- Ingredient cost tracking

---

### Add Ingredient

Vendor selects ingredient from inventory database.

Example:

Chicken Breast

Tomato

Basil

Olive Oil

Vendor then enters:

Quantity per serving

Example:

Chicken Breast → 250 g

---

### Critical Ingredient

Vendor can mark ingredient as **critical**.

If a critical ingredient runs out of stock:

- Menu item becomes automatically unavailable
- Vendor receives notification to restock

---

### Ingredient Management

Vendor can:

Add ingredients

Edit quantities

Delete ingredients

Deleting ingredients may affect nutrition calculations.

---

# Tax Information

Each item inherits the tax category from its category.

Example:

Category → Food

Applied VAT → 10% (Austria)

Vendors **cannot override VAT values**.

Taxes are controlled at the system level.

---

# Multi-Language Translations

Menu items can be translated into multiple languages.

Two methods:

Manual translation

Automatic translation using AI

---

### Important Languages (Austria)

English

German

Turkish

Additional languages:

Italian

Spanish

French

Arabic

Chinese

Japanese

Russian

Serbian

Czech

---

### Fields That Require Translation

Only customer-visible text must be translated:

Item name

Description

Paid add-ons

Free add-ons

Removable items

Example:

Item Name

Kebab Adana

Description

"Grilled lamb skewer with oriental spices served with vegetables and lemon butter sauce."

---

# 5. Edit Existing Menu Item

Vendor can edit all fields described above.

Additional actions:

Toggle availability

Delete item

---

### Item Deletion Rule

Items **cannot be deleted if they exist in orders**.

Instead of deleting, the system performs a **soft delete**.

The item becomes hidden from the menu but remains in the database.

This preserves:

Order history

Invoices

Analytics

---

# 6. Bulk Menu Upload (Excel / CSV)

Vendor can upload many menu items at once.

Supported formats:

CSV

Excel (.xlsx)

---

### Bulk Upload Template

The system provides a downloadable template.

Template includes fields such as:

Category

Item Name

Description

Price

VAT Rate

Dietary Preference

Allergens

Discount %

Image URL

---

### Upload Rules

Items are **added to the menu**, not replaced.

Prices must be entered without currency symbols.

Allergens and tags must be comma separated.

Example:

Gluten, Dairy, Nuts

---

### Validation

If errors exist in the uploaded file:

The system highlights incorrect rows

Vendor receives explanation message

---

# Important UX Elements

Menu page includes:

Metrics:

Total items

Categories count

Average price

Average rating

Search:

Search menu items by name.

Filter:

Filter items by category.

Item cards display:

Image

Name

Price

Tags

Order count

Availability toggle

Edit button

---

# Structural Improvements Required

The following improvements should be considered during development.

These prevent major scaling issues later.

---

# 1. Modifier System Should Support Modifier Groups

Current structure:

Paid add-ons

Free add-ons

Removable items

This works for simple dishes but breaks for complex menus.

Example pizza:

Size

Small

Medium

Large

Toppings

Mushrooms

Olives

Salami

**Correct design should include Modifier Groups.**

Example:

Modifier Group

Size

Options

Small

Medium

Large

Modifier Group

Toppings

Options

Mushrooms

Olives

Salami

This system supports:

Pizza

Burgers

Coffee options

Bowls

Steak cooking level

---

# 2. Bulk Upload Must Support Multiple Sheets

Real menus include:

Modifiers

Translations

Ingredients

A single Excel sheet cannot support this.

Correct approach:

Sheet 1 → Menu Items

Sheet 2 → Modifier Groups

Sheet 3 → Modifier Options

Sheet 4 → Item-Modifier Mapping

Sheet 5 → Ingredients

---

# 3. Tax System Must Be Country Configurable

Taxes depend on country rules.

Example Austria

Food → 10%

Alcohol → 20%

Future systems may require:

Dine-in tax

Takeaway tax

Tax engine should support expansion.

---

# 4. Translation Must Be Field-Based

Translations must be stored separately.

Example:

Menu Item

Menu Item Translation

This allows unlimited languages without duplicating menu items.

---

# 5. Items Must Use Soft Deletion

Menu items should not be permanently deleted.

Instead:

is_active = false

This prevents breaking order history.

# Database Architecture (Menu System)

The following tables define the recommended menu database structure.

# 1. Restaurants

Each vendor owns one or more restaurants.

```
restaurants
```

| field | type |
| --- | --- |
| id | uuid |
| vendor_id | uuid |
| name | text |
| country | text |
| currency | text |
| created_at | timestamp |

---

# 2. Menu Categories

Menu sections.

```
menu_categories
```

| field | type |
| --- | --- |
| id | uuid |
| restaurant_id | uuid |
| name | text |
| tax_category_id | uuid |
| sort_order | integer |
| is_active | boolean |
| created_at | timestamp |

Relationship

```
restaurant → categories → items
```

---

# 3. Tax Categories (System Controlled)

Taxes must **not be editable by vendors**.

```
tax_categories
```

| field | type |
| --- | --- |
| id | uuid |
| country | text |
| name | text |
| vat_rate | decimal |

Example:

| name | VAT |
| --- | --- |
| Food | 10% |
| Non Alcoholic Drinks | 20% |
| Alcohol | 20% |

Categories reference these.

---

# 4. Menu Items

The core dish table.

```
menu_items
```

| field | type |
| --- | --- |
| id | uuid |
| restaurant_id | uuid |
| category_id | uuid |
| price | decimal |
| image_url | text |
| calories | int |
| protein | decimal |
| fat | decimal |
| carbs | decimal |
| dietary_preference | enum |
| availability | boolean |
| discount_percent | decimal |
| is_active | boolean |
| created_at | timestamp |

Important

```
is_active = false
```

means item hidden but **not deleted**.

---

# 5. Menu Item Translations

All customer visible text must be here.

```
menu_item_translations
```

| field | type |
| --- | --- |
| id | uuid |
| menu_item_id | uuid |
| language | varchar |
| name | text |
| description | text |

Example languages:

EN

DE

TR

IT

FR

AR

---

# 6. Modifier Groups

This replaces your current add-ons model.

```
modifier_groups
```

| field | type |
| --- | --- |
| id | uuid |
| restaurant_id | uuid |
| name | text |
| min_selection | integer |
| max_selection | integer |
| is_required | boolean |

Examples

Size

Toppings

Cooking Level

Milk Type

---

# 7. Modifier Options

Options inside each group.

```
modifier_options
```

| field | type |
| --- | --- |
| id | uuid |
| modifier_group_id | uuid |
| name | text |
| price_adjustment | decimal |
| sort_order | integer |
| is_active | boolean |

Example

Group: Size

| option | price |
| --- | --- |
| Small | 0 |
| Medium | +2 |
| Large | +4 |

---

# 8. Item Modifier Mapping

Which dish uses which modifier group.

```
menu_item_modifier_groups
```

| field | type |
| --- | --- |
| id | uuid |
| menu_item_id | uuid |
| modifier_group_id | uuid |

Example

Pizza → Size

Pizza → Toppings

---

# 9. Ingredients

Inventory database.

```
ingredients
```

| field | type |
| --- | --- |
| id | uuid |
| restaurant_id | uuid |
| name | text |
| unit | enum |
| cost_per_unit | decimal |
| stock_quantity | decimal |
| critical_stock_level | decimal |

Example units

g

kg

ml

liter

piece

---

# 10. Recipe Mapping

Links menu item to ingredients.

```
menu_item_ingredients
```

| field | type |
| --- | --- |
| id | uuid |
| menu_item_id | uuid |
| ingredient_id | uuid |
| quantity | decimal |
| is_critical | boolean |

Critical ingredient rule

If stock = 0

item becomes unavailable automatically.

---

# 11. Allergens

System list.

```
allergens
```

| field | type |
| --- | --- |
| id | uuid |
| name | text |

Examples

Gluten

Dairy

Eggs

Peanuts

Soy

Fish

Sesame

---

# 12. Item Allergens Mapping

```
menu_item_allergens
```

| field | type |
| --- | --- |
| id | uuid |
| menu_item_id | uuid |
| allergen_id | uuid |

---

# 13. Tags

Special labels.

```
menu_tags
```

| field | type |
| --- | --- |
| id | uuid |
| name | text |

Examples

Recommended

Chef Pick

Today's Special

Organic

---

# 14. Item Tags

```
menu_item_tags
```

| field | type |
| --- | --- |
| id | uuid |
| menu_item_id | uuid |
| tag_id | uuid |

---

# 15. Orders (Future Connection)

Important for item deletion rule.

```
orders
```

| field | type |
| --- | --- |
| id | uuid |
| restaurant_id | uuid |
| status | enum |
| total_price | decimal |
| created_at | timestamp |

---

# 16. Order Items

```
order_items
```

| field | type |
| --- | --- |
| id | uuid |
| order_id | uuid |
| menu_item_id | uuid |
| price | decimal |
| quantity | integer |

Never delete menu items referenced here.

---

# Menu System Relationship Overview

```
restaurant
   │
   ├── categories
   │        │
   │        └── menu_items
   │                 │
   │                 ├── translations
   │                 ├── modifier_groups
   │                 │        └── modifier_options
   │                 ├── allergens
   │                 ├── tags
   │                 └── ingredients
```