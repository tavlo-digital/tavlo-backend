# Tavlo Inventory Management - Complete Documentation

## Table of Contents
1. [Overview](#overview)
2. [Inventory Overview Page](#inventory-overview-page)
3. [Ingredient Management](#ingredient-management)
4. [Excel Import & Export](#excel-import--export)
5. [Inventory Settings](#inventory-settings)
6. [Best Practices](#best-practices)

---

## Overview

The Tavlo Inventory Management system helps vendors track ingredient stock levels, manage suppliers, automate reordering, and prevent menu items from being sold when ingredients are unavailable. It's designed for operational speed and reliability.

> **📖 Related Guide:** See [MENU_INVENTORY_LINKAGE.md](./MENU_INVENTORY_LINKAGE.md) for how to connect menu items to inventory ingredients (e.g., "Margherita Pizza uses 0.1 kg of Tomatoes per serving").

### Key Principles
- **Fast & Dense**: Designed for daily operational use, not leisurely browsing
- **Zero Ambiguity**: Every status, alert, and action has clear meaning
- **Cause → Effect**: Changes to inventory immediately affect menu availability
- **Trust & Safety**: Every destructive or automated action requires confirmation

---

## Inventory Overview Page

The main inventory page displays all ingredients with real-time stock status, filtering, and bulk actions.

### Stats Dashboard

Four key metrics at the top:

1. **Total Items** - Total number of ingredients in your inventory
2. **Low Stock** - Ingredients below reorder level (amber warning)
3. **Out of Stock** - Ingredients with zero stock (red alert)
4. **Total Value** - Combined value of all current stock (calculated from current stock × cost per unit)

### Search & Filters

**Search Bar**
- Search by ingredient name or supplier name
- Real-time filtering as you type
- Clear button to reset search

**Category Filters**
- All (default)
- Vegetables
- Meat & Poultry
- Dairy
- Grains
- Spices
- Beverages
- Other

**Stock Status Filters**
- **All** - Show all ingredients
- **In Stock** - Only items at or above reorder level
- **Low Stock** - Items below reorder level but not zero
- **Out of Stock** - Items with zero stock
- **Critical Impact** - Items that affect menu availability when out

### Primary Actions

**Add Item (Primary CTA)**
- Orange button, most prominent
- Opens modal to manually add single ingredient
- Use for quick additions or unique items

**Import (Secondary)**
- Upload Excel/CSV file
- 3-step guided flow with validation
- Best for bulk additions or updates

**Export (Secondary)**
- Download current inventory as CSV
- Includes all filtered items
- Use for reporting or backup

### Inventory Table

**Columns:**
- **Ingredient** - Name with critical impact indicator
- **Category** - Organizational grouping
- **Stock Level** - Current stock with unit, shows shortage amount if low
- **Reorder At** - Threshold for automatic low-stock alerts
- **Supplier** - Primary supplier name (if set)
- **Cost/Unit** - Purchase cost per unit
- **Status** - Visual badge (In Stock/Low Stock/Out of Stock)
- **Actions** - Quick edit button

**Row Coloring:**
- Default: White background
- Low Stock: Amber tint (bg-amber-50/30)
- Out of Stock: Red tint (bg-red-50/50)
- Hover: Slightly darker tint for feedback

**Critical Impact Indicator:**
- Small orange "Critical" badge under ingredient name
- Means this ingredient affects menu item availability
- If it runs out, menu items using it will be auto-unavailable

### Empty States

**No Inventory Yet:**
- Large upload icon
- Message: "Upload your ingredient list from Excel to get started in minutes, or add items individually"
- Two CTAs: "Import from Excel" (primary) and "Add Item" (secondary)

**No Results Found:**
- Package icon
- Message: "No items match your filters"
- "Clear all filters" button

---

## Ingredient Management

### Adding Ingredients

Click "Add Item" to open the add ingredient modal.

**Required Fields:**
- **Ingredient Name** - Unique identifier (e.g., "Tomatoes (Fresh)")
- **Unit** - kg, g, liters, ml, pieces, bunches, boxes, cans, bottles

**Optional Fields:**
- **Category** - For organization and filtering
- **Current Stock** - Starting stock level
- **Reorder Level** - Trigger low-stock alerts when stock falls below this
- **Reorder Quantity** - How much to order when restocking
- **Supplier** - Primary supplier name
- **Cost per Unit** - Purchase price per unit (in €)

**Categories:**
- Vegetables
- Meat & Poultry
- Dairy
- Grains
- Spices
- Beverages
- Other

### Viewing Ingredient Details

Click any ingredient row to open the detail modal.

**Ingredient Summary (Dominant Section):**
- Large, bold numbers for Current Stock, Reorder Level, Reorder Quantity, Cost per Unit
- Visual comparison showing stock status vs reorder level
- Total inventory value calculation
- Color-coded status indicators

**Status Comparisons:**
- **In Stock**: Green badge, no warning
- **Low Stock**: Amber badge + "X below reorder level"
- **Out of Stock**: Red badge + "Restock X immediately"

### Adjusting Stock

Click "Adjust Stock" button in detail modal.

**Required:**
- **Adjustment Type** - Must select one:
  - **Waste** - Spoilage, damage, expired items
  - **Delivery** - Received from supplier
  - **Correction** - Manual inventory count correction

- **Adjustment Amount** - Positive (+50) or negative (-5)
  - Shows new stock preview in real-time

**Optional:**
- **Reason** - Free text note (e.g., "Spoiled batch", "Weekly delivery")

**Validation:**
- Cannot proceed without selecting adjustment type
- Cannot submit zero adjustment
- Real-time preview of new stock level

**Activity Tracking:**
All adjustments are logged with:
- Date and time
- Amount changed
- Source label (Manual, Supplier Delivery, Order, Excel Import)
- User who made the change
- Note/reason (if provided)

### Affected Menu Items

Shows which menu items use this ingredient and impact status.

**Critical vs Non-Critical:**
- **Critical Badge (Red)** - Menu item becomes unavailable when ingredient is out
- **No Badge** - Menu item uses ingredient but has alternatives

**Helper Text:**
"If this ingredient runs out, these items will be auto-unavailable."

**Alert When Out of Stock:**
Red banner appears showing: "X menu item(s) currently unavailable due to this stock shortage"

### Stock Activity Log

Chronological history of all stock changes.

**Source Labels:**
- **Order** - Deducted by customer order (automatic)
- **Supplier Delivery** - Added by receiving shipment
- **Manual** - Adjusted by staff member
- **Excel Import** - Updated via bulk import

**Display:**
- Color-coded amount (green for positive, red for negative)
- Source badge
- Description/note
- Date and user

### Deleting Ingredients

"Delete Ingredient" button in modal footer (red, left-aligned).

**Safety:**
- Requires confirmation dialog
- Cannot be undone
- Shows warning if ingredient is used in menu items

---

## Excel Import & Export

### Export to Excel/CSV

**How to Export:**
1. (Optional) Apply filters to select which items to export
2. Click "Export" button
3. CSV file downloads automatically
4. Filename: `inventory-YYYY-MM-DD.csv`

**Exported Data:**
- Ingredient Name
- Category
- Current Stock
- Unit
- Reorder Level
- Supplier
- Cost per Unit
- Status

**Use Cases:**
- Backup inventory data
- Share with accountant
- Analyze in Excel
- Print for physical inventory count

### Import from Excel/CSV

A 3-step guided flow with validation and safety checks.

#### Step 1: Upload

**Supported Formats:**
- `.xlsx` (Excel)
- `.csv` (Comma-separated values)

**Reassurance:**
- Blue info banner: "Nothing will change until you confirm"
- "Review all changes in step 3 before any inventory data is updated"

**Template Download:**
- "Download Template" button always visible
- Pre-formatted with correct column names
- Includes sample row for reference

**File Validation:**
- Rejects unsupported file types
- Shows selected filename

#### Step 2: Map Columns

**Auto-Mapping:**
- System automatically maps common column names
- Shows green success banner: "Auto-mapping detected"
- You can review and adjust mappings

**Column Mapping Interface:**
- Three columns: Your Column | Maps To | Sample Value
- Dropdown for each column with options:
  - Skip this column
  - Ingredient Name *
  - Category
  - Unit *
  - Current Stock
  - Reorder Level
  - Reorder Quantity
  - Supplier Name
  - Cost per Unit (€)

**Required Fields:**
- Ingredient Name (marked with *)
- Unit (marked with *)

**Inline Warnings:**
- Amber warning icon for unusual data
- Examples:
  - "Contains currency symbol - will be stripped"
  - "Unusual unit detected"
  - "Missing required fields"

**Validation:**
- "Continue" button disabled until required fields are mapped
- Red error banner if trying to proceed without required fields
- Clear message: "Please map all required fields before continuing"

#### Step 3: Review

**Summary Cards:**
Three color-coded cards at top:
- **Green Card**: X New Ingredients
- **Blue Card**: X Updated Ingredients
- **Amber Card**: X Skipped Rows

**New Ingredients Table:**
- Green "New" badge
- Shows all items that will be added
- Columns: Name, Category, Stock, Unit, Supplier
- Expandable table for easy review

**Updated Ingredients Table:**
- Blue "Update" badge
- Shows existing items that will be updated
- Highlights which values are changing
- New stock values in blue

**Skipped Rows:**
- Amber "Skipped" badge
- Shows rows with errors/conflicts
- Each row displays specific conflict reason:
  - "Missing required field: Ingredient Name"
  - "Same name, different unit (existing: kg)"
  - "Invalid data format"

**Conflict Highlighting:**
- Amber background with warning icon
- Clear explanation of why row is skipped
- No partial imports - fix issues or skip row

**Final Confirmation:**
- Orange banner with alert icon
- "This will update your inventory stock levels. This action cannot be undone."
- Forces conscious decision before proceeding

**Import Button:**
- Disabled if no valid rows to import
- Shows count: "Import X Items"
- Shows "Importing..." during process
- Success toast: "Import complete! X added, Y updated"

#### Excel Import Behavior

**For Existing Ingredients:**
- Matches by exact ingredient name
- Updates ALL mapped fields (overwrites existing data)
- Stock values are replaced, not added
- Unit must match or row is skipped

**For New Ingredients:**
- Creates new inventory item
- Sets all provided fields
- Uses defaults for unmapped fields

**Error Handling:**
- Invalid data types skipped with reason
- Duplicate rows skipped with warning
- Unit mismatches flagged as conflicts

---

## Inventory Settings

Access via **Settings** → **Inventory** tab in the vendor dashboard.

The settings page has 5 sub-tabs for different aspects of inventory configuration.

### General Tab

**Enable Inventory Tracking**
- Toggle: On/Off
- When ON: Tracks stock levels, shows alerts, affects menu availability
- When OFF: Inventory system disabled entirely

**Enable Auto Stock Deduction**
- Toggle: On/Off
- When ON: Customer orders automatically reduce ingredient stock
- When OFF: Stock must be adjusted manually
- Recommended: Keep ON for accuracy

**Allow Negative Stock**
- Toggle: On/Off (Default: OFF)
- When ON: Stock can go below zero (debt inventory)
- When OFF: Prevents negative values, shows warnings
- ⚠️ Warning displayed: "Allowing negative stock may cause inaccurate reports. Use only if you have a specific workflow requiring this."

**Excel Upload Behavior**
- Info text: "Excel uploads overwrite stock values"
- Clarifies that imports replace data, not merge
- Prevents confusion about import behavior

### Automation Tab (Enterprise Only)

**Feature Gate:**
- Only available on Enterprise plan
- Shows upgrade prompt for other plans
- Focus on value: "Save 10+ hours per week with automated inventory"

**Enable AI Stock Prediction**
- Toggle: On/Off
- Uses historical order data to predict future needs
- Suggests optimal reorder levels
- Updates weekly based on trends

**Enable Low Stock Alerts**
- Toggle: On/Off (Default: ON)
- Triggers alerts when stock falls below reorder level
- Works with alert frequency settings (see Alerts tab)

**Enable Auto-Generated Purchase Orders**
- Toggle: On/Off (Default: OFF)
- Automatically creates purchase orders when stock is low
- Requires suppliers to be configured

**Auto Order Approval Mode**
- Dropdown: "Draft" | "Auto Send"
- **Draft**: Creates PO but waits for manual review/approval
- **Auto Send**: Sends PO to supplier immediately (Email only)
- Recommended: Start with Draft

**Budget Cap per Order**
- Number field (€)
- Maximum value for auto-generated orders
- Safety limit to prevent overspending
- Orders above cap saved as draft regardless of approval mode

**Automation Grouping:**
Toggles are logically grouped:
- Prediction section
- Alerts section
- Auto purchase orders section

### Availability Rules Tab

Controls how menu items are affected by stock levels.

**Auto-mark unavailable when critical ingredient is out**
- Toggle: On/Off (Default: ON)
- When ON: Menu items marked "Critical" become unavailable when key ingredient is zero
- When OFF: Menu stays available regardless of stock
- Shows "Auto-managed" badge on affected menu items
- Helper text: "Availability is recalculated in real time based on stock changes"

**Auto-mark unavailable when ANY ingredient is out**
- Toggle: On/Off (Default: OFF)
- More aggressive than critical-only mode
- Menu item unavailable if ANY ingredient (critical or not) is out
- Use if your menu has no ingredient substitutions

**Preview Section:**
- Shows which menu items are currently auto-managed
- "Auto-managed" badge on each item
- Number of critical vs non-critical ingredients per item

**Per-Item Overrides:**
- (Future feature placeholder)
- Would allow custom rules per menu item

### Suppliers Tab

Manage supplier contacts and ordering information.

**Supplier List:**
Each supplier card shows:
- **Name** - Supplier company name
- **Ordering Method Badge** - Email / API / Phone
- **Status Badge** - Active (green) / Inactive (gray)
- **Lead Time** - Days between order and delivery
- **Supported Ingredients** - List of items they supply
- **Contact Info** - Email and/or phone number

**Supplier Card Layout:**
- Dense, table-like rows
- Clear ordering method badge (helps staff know how to order)
- Status toggle (Active/Inactive) without deleting supplier

**Actions:**
- **Edit** - Modify supplier details
- **Disable** - Set to inactive (keeps history, can re-enable)
- **Delete** - Permanently remove (requires confirmation)

**Adding Suppliers:**
- "Add Supplier" button (orange, top-right)
- Form fields:
  - Name (required)
  - Email
  - Phone
  - Ordering Method (Email/API/Phone)
  - Lead Time (days)
  - Supported Ingredients (multi-select from inventory)

**Ordering Methods:**
- **Email Badge (Blue)** - Orders sent via email
- **API Badge (Green)** - Direct integration (advanced)
- **Phone Badge (Gray)** - Call to place orders

**Enable/Disable vs Delete:**
- Disable keeps supplier in system but excludes from auto-ordering
- Delete removes permanently (loses history)
- Recommended: Disable instead of delete for seasonal suppliers

### Alerts & Notifications Tab

Configure how and when you're notified about stock issues.

**Alert Channels:**

- **Dashboard Alerts**
  - Toggle: On/Off (Default: ON)
  - Red alert banner appears in dashboard when low/out of stock
  - Always visible when logged in

- **Email Alerts**
  - Toggle: On/Off (Default: ON)
  - Sends email to vendor contact address
  - Subject: "Low Stock Alert: [Ingredient Name]"

- **SMS Alerts**
  - Toggle: On/Off (Default: OFF)
  - Requires phone number on file
  - Charged separately (premium feature)

**Alert Frequency:**

Dropdown with three options:

1. **Immediate** (Default)
   - Sends alert as soon as stock falls below reorder level
   - Best for high-volume restaurants
   - Can result in multiple alerts per day

2. **Daily**
   - Batches all alerts into daily summary
   - Sent once per day at configured time
   - Reduces notification fatigue

3. **Weekly**
   - Batches all alerts into weekly summary
   - Sent every Monday at configured time
   - For lower-volume operations

**Daily Low Stock Summary:**
- Toggle: On/Off (Default: ON)
- Separate from alert frequency
- Sends daily digest at specified time
- Lists all items currently below reorder level
- Good overview even if using immediate alerts

**Daily Summary Time:**
- Time picker: Default 09:00
- Time zone: Uses vendor account time zone
- Recommended: Set to when kitchen staff arrives

**Priority Overrides:**
- Microcopy: "Urgent alerts are always sent immediately"
- Certain alerts bypass frequency setting:
  - Critical ingredient goes to zero
  - Multiple items out simultaneously
  - Stock goes negative (if allowed)

**Alert Hierarchy Visualization:**
- Clear priority structure displayed
- Helps understand which alerts are urgent vs informational

### Saving Settings

**Save Button:**
- Fixed at top or bottom of each tab
- Orange color (primary action)
- Shows "Saving..." during API call
- Success toast: "Settings saved successfully"
- Error toast: "Failed to save settings. Please try again."

**Auto-Save:**
- Not implemented (requires manual save)
- Prevents accidental changes
- User maintains control

---

## Best Practices

### Setting Up Inventory

1. **Start with Excel Import**
   - Download template
   - Fill in all ingredients at once
   - Much faster than manual entry
   - Easier to review in spreadsheet

2. **Set Accurate Reorder Levels**
   - Base on lead time + buffer
   - Example: 2-day lead time → 3 days of stock as reorder level
   - Review and adjust over first month

3. **Categorize Consistently**
   - Use same category names across similar items
   - Makes filtering easier
   - Helps with reporting

4. **Configure Suppliers Early**
   - Add all regular suppliers
   - Set accurate lead times
   - Enables auto-ordering features

### Daily Operations

1. **Check Dashboard on Arrival**
   - Review low stock alerts
   - Place orders for items at reorder level
   - Mark urgent items

2. **Adjust Stock After Deliveries**
   - Use "Delivery" adjustment type
   - Record supplier name in note
   - Keeps activity log accurate

3. **Record Waste Properly**
   - Use "Waste" adjustment type
   - Note reason (spoilage, drop, expire)
   - Helps identify problem ingredients

4. **Monitor Critical Items**
   - Use "Critical Impact" filter
   - These affect customer-facing menu
   - Prioritize ordering for these items

### Weekly Review

1. **Export Inventory**
   - Weekly CSV backup
   - Compare week-over-week
   - Identify high-consumption items

2. **Review Stock Activity Logs**
   - Check for unusual patterns
   - Verify waste levels are acceptable
   - Ensure deliveries are logged

3. **Adjust Reorder Levels**
   - Increase if frequently out of stock
   - Decrease if consistently over-stocked
   - Based on actual consumption data

### Excel Import Tips

1. **Use Template**
   - Always start from official template
   - Column names must match exactly
   - Don't add extra columns

2. **Consistent Units**
   - Use same unit for same ingredient
   - Don't mix "kg" and "kilograms"
   - Check unit mismatches in Step 3

3. **Clean Data**
   - Remove currency symbols from prices
   - Use numbers only for quantities
   - Check for empty required fields

4. **Review Before Import**
   - Always check Step 3 summary
   - Verify updates are intentional
   - Fix conflicts before proceeding

### Automation Setup (Enterprise)

1. **Start with Alerts Only**
   - Enable low stock alerts
   - Get comfortable with notifications
   - Don't auto-order yet

2. **Test AI Predictions**
   - Enable prediction after 1 month of data
   - Compare AI suggestions to your intuition
   - Adjust reorder levels based on AI insights

3. **Use Draft Mode First**
   - Set auto-orders to "Draft" approval
   - Review several auto-generated POs
   - Switch to "Auto Send" once confident

4. **Set Conservative Budget Cap**
   - Start with low budget cap (€200-€500)
   - Increase as you trust automation
   - Prevents expensive mistakes

### Stock Status Management

**In Stock (Green):**
- Current stock ≥ reorder level
- No action needed
- Monitor consumption rate

**Low Stock (Amber):**
- Current stock < reorder level but > 0
- Place order soon
- Not yet affecting menu
- Check lead time

**Out of Stock (Red):**
- Current stock = 0
- Urgent: Order immediately
- May affect menu availability
- Check affected menu items

**Critical Impact:**
- Focus on these ingredients
- Keep extra buffer stock
- Consider backup suppliers
- Monitor more frequently

---

## Troubleshooting

### "Import Failed" Error

**Causes:**
- File format not supported (use .xlsx or .csv)
- Required fields not mapped
- Column names don't match template

**Solutions:**
1. Download fresh template
2. Copy data to template
3. Ensure Ingredient Name and Unit are mapped
4. Remove special characters from ingredient names

### Stock Not Deducting Automatically

**Check:**
1. Settings → Inventory → General → "Enable Auto Stock Deduction" is ON
2. Menu items have ingredients linked
3. Ingredient units match between menu and inventory

**Fix:**
- Enable auto deduction in settings
- Re-link ingredients in menu management
- Standardize units across system

### Menu Item Not Auto-Unavailable

**Check:**
1. Settings → Inventory → Availability Rules → "Auto-mark unavailable when critical ingredient is out" is ON
2. Ingredient is marked as "Critical Impact"
3. Menu item has ingredient properly linked

**Fix:**
- Enable availability rule
- Edit menu item to mark ingredient as critical
- Save changes and verify linkage

### Alerts Not Sending

**Check:**
1. Settings → Inventory → Alerts → Email Alerts is ON
2. Alert frequency is appropriate
3. Email address is correct in account settings
4. Check spam folder

**Fix:**
- Enable email alerts
- Test with "immediate" frequency
- Update email address
- Add noreply@tavlo.at to contacts

### Negative Stock Showing

**Cause:**
- "Allow Negative Stock" is enabled
- Orders processed before delivery recorded

**Fix:**
1. Adjust stock to correct positive value
2. Settings → General → Disable "Allow Negative Stock"
3. Record deliveries immediately upon receipt

---

## Glossary

**Critical Impact** - An ingredient that, when out of stock, causes menu items to become unavailable

**Reorder Level** - The stock threshold that triggers low-stock alerts

**Reorder Quantity** - The amount to order when restocking an ingredient

**Lead Time** - Days between placing an order and receiving delivery

**Auto-managed** - Menu items whose availability is automatically controlled by stock levels

**Purchase Order (PO)** - An order placed with a supplier for ingredients

**Stock Deduction** - Automatic reduction of inventory when orders are placed

**Conflict** - During Excel import, a row that cannot be processed due to errors

**Adjustment Type** - Category of stock change (Waste, Delivery, Correction)

---

## Support

For questions or issues with the Inventory Management system:

- **In-app Help**: Click ? icon in top navigation
- **Email Support**: support@tavlo.at
- **Documentation**: tavlo.at/docs/inventory
- **Video Tutorials**: tavlo.at/learn

**Enterprise Customers:**
- Dedicated account manager
- Priority support: support-enterprise@tavlo.at
- Phone support available

---

**Last Updated**: January 2026  
**Version**: 2.0  
**System**: Tavlo Vendor Dashboard - Inventory Management