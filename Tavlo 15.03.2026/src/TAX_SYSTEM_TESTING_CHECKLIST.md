# Tavlo Tax System - Testing Checklist

## Pre-Testing Setup
- [ ] Clear browser cache
- [ ] Start fresh with clean vendor account
- [ ] Have two test restaurants: one Austrian, one German

---

## 1. Settings → Tax & Receipts

### Country Selection
- [ ] Navigate to vendor dashboard → Settings → Tax & Receipts
- [ ] Verify country selector shows Austria 🇦🇹 and Germany 🇩🇪
- [ ] Select Austria
- [ ] Verify VAT rules display correctly:
  - [ ] Food: 10%
  - [ ] Non-Alcoholic Beverages: 20%
  - [ ] Alcoholic Beverages: 20%
- [ ] Switch to Germany
- [ ] Verify VAT rules update:
  - [ ] Food: 7%
  - [ ] Non-Alcoholic Beverages: 19%
  - [ ] Alcoholic Beverages: 19%

### Visual Elements
- [ ] Verify info banner displays:
  > "VAT rates are defined by local tax law and automatically applied by Tavlo."
- [ ] Verify trust badge shows:
  > "✅ Loaded from Tavlo Tax Rules Database"
- [ ] Verify all three tax category cards display with icons
- [ ] Verify no manual VAT percentage input fields exist

### Service Fee
- [ ] Verify service fee input field is separate from VAT
- [ ] Verify helper text clarifies it's not subject to VAT

### Invoice Settings
- [ ] Verify invoice settings section displays
- [ ] Verify compliance message adjusts based on country:
  - Austria: "...as required by Austrian tax law"
  - Germany: "...as required by German tax law"

### Save Functionality
- [ ] Change country to Austria
- [ ] Click Save
- [ ] Refresh page
- [ ] Verify Austria is still selected
- [ ] Verify VAT rules show Austrian rates

---

## 2. Menu → Add Category

### Basic Functionality
- [ ] Navigate to Menu → Management
- [ ] Click "Add Category" button
- [ ] Verify dialog opens with two fields:
  - Category Name
  - Tax Category (Default)

### Tax Category Selector
- [ ] Open Tax Category dropdown
- [ ] Verify three options display:
  - [ ] 🍽 Food with description
  - [ ] 🥤 Beverage (Non-Alcoholic) with description
  - [ ] 🍺 Beverage (Alcoholic) with description
- [ ] Verify each option shows icon + label + description

### Helper Text
- [ ] Verify helper text displays:
  > "All items in this category will inherit this tax classification. Tax categories determine VAT automatically based on your country."

### Create Categories
- [ ] Create category "Appetizers" with tax category "Food"
  - [ ] Verify category appears in list
  - [ ] Verify icon is 🍽
- [ ] Create category "Soft Drinks" with tax category "Beverage (Non-Alcoholic)"
  - [ ] Verify icon is 🥤
- [ ] Create category "Wine & Beer" with tax category "Beverage (Alcoholic)"
  - [ ] Verify icon is 🍺

---

## 3. Menu → Add Item

### Tax Category Inheritance
- [ ] Click "Add New Item"
- [ ] Select category "Appetizers" (Food category)
- [ ] Scroll to "Tax Information" section
- [ ] Verify tax category defaults to "Food"
- [ ] Verify "Applied VAT Rate" displays:
  - Austria: "10% (Austria – Food)"
  - Germany: "7% (Germany – Food)"

### Tax Category Override
- [ ] Change category to "Appetizers" (Food)
- [ ] Verify tax category is "Food"
- [ ] Change tax category to "Beverage (Alcoholic)"
- [ ] Verify warning appears:
  > "⚠️ Changing this may affect tax compliance. Most items should use the category default."
- [ ] Verify "Applied VAT Rate" updates:
  - Austria: "20% (Austria – Alcoholic Beverages)"
  - Germany: "19% (Germany – Alcoholic Beverages)"

### Create Items
- [ ] Create "Bruschetta" in "Appetizers" category
  - Price: €8.50
  - Tax Category: Food (inherited)
  - [ ] Verify saves successfully
- [ ] Create "Coca Cola" in "Soft Drinks" category
  - Price: €2.50
  - Tax Category: Beverage (Non-Alcoholic) (inherited)
  - [ ] Verify saves successfully
- [ ] Create "House Wine" in "Wine & Beer" category
  - Price: €4.50
  - Tax Category: Beverage (Alcoholic) (inherited)
  - [ ] Verify saves successfully

### No Manual VAT Input
- [ ] In Add Item dialog, scroll through entire form
- [ ] Verify NO input field for "VAT Rate (%)" exists
- [ ] Verify only tax category selector exists

---

## 4. Menu → Edit Item

### Tax Information Display
- [ ] Click Edit on "Bruschetta" (Food item)
- [ ] Scroll to "Tax Information" section
- [ ] Verify tax category shows "Food"
- [ ] Verify "Applied VAT Rate" shows correct rate for country
- [ ] Verify no manual VAT input exists

### Tax Category Change
- [ ] Change tax category from "Food" to "Beverage (Non-Alcoholic)"
- [ ] Verify warning appears
- [ ] Verify "Applied VAT Rate" updates
- [ ] Click Save
- [ ] Re-open item for editing
- [ ] Verify tax category change persisted

### Different Categories
- [ ] Edit "Coca Cola" (Beverage Non-Alcoholic)
  - [ ] Verify correct tax category and rate
- [ ] Edit "House Wine" (Beverage Alcoholic)
  - [ ] Verify correct tax category and rate

---

## 5. Receipt Preview (if implemented in UI)

### Basic Display
- [ ] Navigate to checkout or receipt preview area
- [ ] Add items from different tax categories to basket
- [ ] Verify receipt shows:
  - [ ] Item list with prices
  - [ ] Net Total
  - [ ] VAT breakdown by category
  - [ ] Grand Total

### VAT Breakdown
Example basket:
- 1× Bruschetta (Food) €8.50
- 2× Coca Cola (Beverage NA) €5.00
- 1× House Wine (Beverage Alc) €4.50

For Austria, verify:
- [ ] Net Total calculates correctly
- [ ] "incl. VAT 10% (Food)" shows food VAT amount
- [ ] "incl. VAT 20% (Drinks)" shows beverage VAT amounts
- [ ] Total matches sum of items

### Compliance Badge
- [ ] Verify badge shows: "✅ Tax-compliant for [Country]"
- [ ] Switch vendor country in settings
- [ ] Verify badge updates to show new country

---

## 6. German-Specific: Combo Items

### VAT Split UI (Germany Only)
- [ ] Set vendor country to Germany
- [ ] Create or edit a combo/menu item
- [ ] Verify VAT Split Input appears
- [ ] Verify two input fields:
  - [ ] 🍽 Food Component (with VAT: 7%)
  - [ ] 🥤 Beverage Component (with VAT: 19%)

### Split Validation
- [ ] Set item price to €10.00
- [ ] Enter food component: €7.00
- [ ] Enter beverage component: €2.00
- [ ] Verify error shows: "Split total (€9.00) must equal item price (€10.00)"
- [ ] Adjust beverage to €3.00
- [ ] Verify success message: "✅ Split matches total price: €10.00"

### Not Shown for Austria
- [ ] Switch vendor country to Austria
- [ ] Edit combo/menu item
- [ ] Verify VAT Split UI does NOT appear
- [ ] Verify only standard tax category selector shows

---

## 7. Country Switching

### Austria → Germany
- [ ] Set vendor country to Austria
- [ ] Create item "Test Schnitzel" with Food category
- [ ] Note displayed VAT: "10% (Austria – Food)"
- [ ] Go to Settings → Tax & Receipts
- [ ] Change country to Germany
- [ ] Save settings
- [ ] Go back to Menu Management
- [ ] Edit "Test Schnitzel"
- [ ] Verify VAT updates to: "7% (Germany – Food)"

### Germany → Austria
- [ ] Repeat above test in reverse
- [ ] Verify all VAT rates update correctly

---

## 8. Edge Cases

### Empty Tax Category
- [ ] Try to create category without selecting tax category
- [ ] Verify appropriate error/validation

### Category Without Default Tax Category
- [ ] Manually create/import category without defaultTaxCategory
- [ ] Add item to that category
- [ ] Verify system defaults to "Food" gracefully

### Item Without Tax Category
- [ ] Manually create/import item without taxCategory
- [ ] Edit that item
- [ ] Verify system shows warning or defaults to category default

### Legacy Items (with vatRate but no taxCategory)
- [ ] Import/create item with vatRate: 20 but no taxCategory
- [ ] Edit item
- [ ] Verify system infers or allows selection of tax category
- [ ] Save and verify taxCategory is now set

---

## 9. Multi-Language Support

### Tax Category Labels
- [ ] Switch platform language to German
- [ ] Open Add Category dialog
- [ ] Verify tax category options display in German (if translations exist)
- [ ] Switch to Arabic
- [ ] Verify appropriate RTL layout and translations

### VAT Display Strings
- [ ] Verify "Applied VAT Rate" displays correctly in all languages
- [ ] Verify compliance messages are translated
- [ ] Verify helper text is translated

---

## 10. Data Persistence

### After Page Refresh
- [ ] Create category with tax category "Food"
- [ ] Refresh page
- [ ] Edit category
- [ ] Verify tax category persists

### After Logout/Login
- [ ] Set vendor country to Germany
- [ ] Create items with various tax categories
- [ ] Log out
- [ ] Log back in
- [ ] Verify country setting persists
- [ ] Verify all items show correct tax categories and rates

### API Save Verification
- [ ] Open browser dev tools → Network tab
- [ ] Create new item with tax category
- [ ] Save item
- [ ] Check API request payload
- [ ] Verify `taxCategory` field is included
- [ ] Verify `defaultTaxCategory` in category creation requests

---

## 11. UI/UX Verification

### Visual Consistency
- [ ] All tax category icons (🍽, 🥤, 🍺) display correctly
- [ ] Country flags (🇦🇹, 🇩🇪) display correctly
- [ ] Warning icon (⚠️) displays in override warnings
- [ ] Checkmark icon (✅) displays in compliance badges

### Responsive Design
- [ ] Test on desktop (1920px)
  - [ ] All elements align properly
  - [ ] Tax rules cards in grid layout
- [ ] Test on tablet (768px)
  - [ ] Cards stack appropriately
  - [ ] Dropdowns work correctly
- [ ] Test on mobile (375px)
  - [ ] All content readable
  - [ ] Touch targets large enough
  - [ ] Scrolling works smoothly

### Color & Typography
- [ ] Read-only fields have gray background
- [ ] Compliance badges use green (#10b981)
- [ ] Warnings use amber (#f59e0b)
- [ ] System values use monospace font

### Accessibility
- [ ] Tab through all form fields
- [ ] Verify logical tab order
- [ ] Verify dropdowns are keyboard accessible
- [ ] Test with screen reader (if available)
- [ ] Verify sufficient color contrast

---

## 12. Integration Testing

### Full Workflow: New Restaurant Setup
- [ ] Create new vendor account
- [ ] Complete onboarding
- [ ] Go to Settings → Tax & Receipts
- [ ] Select country (Austria)
- [ ] Verify VAT rules display
- [ ] Go to Menu Management
- [ ] Create 3 categories (Food, Beverages NA, Beverages Alc)
- [ ] Create 5 items across categories
- [ ] Verify all tax categories inherit correctly
- [ ] Create order with mixed items
- [ ] Verify receipt shows correct VAT breakdown
- [ ] Generate invoice
- [ ] Verify invoice includes correct VAT by category

### Customer Order Flow
- [ ] Customer scans QR code
- [ ] Customer adds items from different tax categories
- [ ] Customer proceeds to checkout
- [ ] Verify order summary shows prices
- [ ] Verify receipt (if shown) has VAT breakdown
- [ ] Complete order
- [ ] Verify confirmation shows tax-compliant receipt

---

## 13. Performance Testing

### Load Times
- [ ] Open Settings → Tax & Receipts
- [ ] Measure load time (should be < 1 second)
- [ ] Open Menu Management
- [ ] Measure render time with 50+ items (should be < 2 seconds)

### Tax Calculation Speed
- [ ] Create order with 20 items
- [ ] Measure VAT calculation time (should be near-instant)
- [ ] Verify no UI lag when changing quantities

---

## 14. Error Handling

### Invalid Country
- [ ] Manually set country to invalid value in dev tools
- [ ] Refresh page
- [ ] Verify system defaults to 'AT' gracefully

### Invalid Tax Category
- [ ] Manually set item taxCategory to invalid value
- [ ] Edit item
- [ ] Verify system shows validation error or defaults gracefully

### Network Error
- [ ] Disconnect internet
- [ ] Try to save tax settings
- [ ] Verify appropriate error message
- [ ] Reconnect internet
- [ ] Retry save
- [ ] Verify success

---

## 15. Browser Compatibility

Test in:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Mobile Chrome (Android)

For each browser, verify:
- [ ] Tax category dropdowns work
- [ ] Country selector works
- [ ] Icons/emojis display correctly
- [ ] Layouts render properly

---

## Acceptance Criteria

All items must pass ✅ for system to be production-ready:

### Critical (Must Pass)
- [ ] No manual VAT percentage inputs exist anywhere
- [ ] VAT rates are read-only and system-controlled
- [ ] Tax categories are selectable and functional
- [ ] Country selection works and persists
- [ ] VAT rates update correctly when country changes
- [ ] All existing menu items can be edited without errors

### Important (Should Pass)
- [ ] Receipt preview shows VAT breakdown
- [ ] Compliance badges display correctly
- [ ] Warning messages appear when appropriate
- [ ] German VAT split works (for DE country)
- [ ] Helper text is clear and helpful

### Nice-to-Have (Good to Pass)
- [ ] Animations are smooth
- [ ] Responsive design works on all devices
- [ ] Multi-language support functional
- [ ] Icons render consistently

---

## Sign-Off

**Tested by:** ___________________________  
**Date:** ___________________________  
**Version:** ___________________________  

**Overall Status:**
- [ ] ✅ All tests passed - Ready for production
- [ ] ⚠️ Minor issues found - Needs fixes before production
- [ ] ❌ Critical issues found - Not ready for production

**Notes:**
_____________________________________________
_____________________________________________
_____________________________________________
