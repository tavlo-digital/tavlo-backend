# Business Info Settings Page - Redesign Documentation

## Overview

The Business Info settings page has been redesigned to clearly separate **legal identity**, **public-facing profile**, and **operational information**, making it easier for vendors to understand what information is used where and reducing mistakes.

---

## Design Principles

1. **Clear Separation** - Each type of information is grouped into distinct, clearly labeled sections
2. **Contextual Clarity** - Helper text explains what each section is used for
3. **Visual Hierarchy** - Color-coded cards with icons for easy scanning
4. **No Behavior Changes** - Only UI/UX improvements, no backend changes
5. **Responsibility Awareness** - Vendors understand the impact of their changes

---

## What Changed

### **Before:**

```
[Restaurant Details]
  - Restaurant Name
  - Business Registration Number
  - VAT Number
  - Company Type
  
[Description]

[Email] [Phone]

[Website]

[Address]

[Business Hours]
  - Days/times...

[Branding]
  - Logo
  - Cover Photo
```

**Problems:**
- ❌ Legal and public info mixed together
- ❌ No clear indication of what's shown to customers
- ❌ No context for business hours purpose
- ❌ Branding section doesn't explain where images appear

---

### **After:**

```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃ 🔒 LEGAL BUSINESS INFORMATION      ┃
┃ (For invoices, tax, legal ID)      ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃ - Business Registration Number     ┃
┃ - VAT Number                        ┃
┃ - Company Type                      ┃
┃ - Legal Address                     ┃
┃                                     ┃
┃ ⚠️ Changes affect invoices/tax     ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃ 👁️  RESTAURANT PROFILE              ┃
┃ (Visible to Customers)              ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃ - Restaurant Name                   ┃
┃   Note: Admin approval after first  ┃
┃ - Description                        ┃
┃ - Email                             ┃
┃ - Phone                             ┃
┃ - Website                           ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃ 🎨 BRANDING & APPEARANCE            ┃
┃ (Shown on page, menu, ordering)     ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃ - Logo                              ┃
┃ - Cover Photo                       ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃ 🕐 BUSINESS HOURS                   ┃
┃ (Controls orders & reservations)    ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃ - Days/times...                     ┃
┃                                     ┃
┃ ℹ️ Ordering disabled outside hours ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
```

**Benefits:**
- ✅ Clear separation by purpose
- ✅ Vendors understand what customers see
- ✅ Context for each section's purpose
- ✅ Visual cues (colors, icons)

---

## Section-by-Section Breakdown

### **SECTION 1: Legal Business Identity**

#### **Visual Treatment:**
- **Card style:** Border-2, more formal appearance
- **Header background:** Gray (`bg-gray-50`)
- **Icon:** Lock icon in gray badge
- **Color scheme:** Gray (formal, serious)

#### **Title:**
> "Legal Business Information"

#### **Helper text:**
> "This information is used for invoices, tax compliance, and legal identification."

#### **Fields:**

1. **Business Registration Number**
   - Input field (text)
   - Used for official documents

2. **VAT Number**
   - Input field (text)
   - Required for invoicing

3. **Company Type**
   - Dropdown select
   - Options: GmbH, AG, OG, KG, Einzelunternehmen
   - Pulled from taxSettings.companyType

4. **Legal Address**
   - Textarea (2 rows)
   - Full span (md:col-span-2)
   - MapPin icon
   - Official registered address

#### **Bottom Warning Box:**
- **Background:** Amber (`bg-amber-50`)
- **Border:** Amber (`border-amber-200`)
- **Icon:** Shield icon
- **Text (Two statements):**
  1. > "Note: Any changes to legal information require Tavlo admin approval for verification."
  2. > "Changes to legal information may affect invoices and tax compliance."

**Purpose:**
- Makes vendors aware that legal changes need approval
- Explains the impact on invoices and tax compliance
- No actual approval workflow needed (informational only)
- Raises awareness of consequences and approval requirement

---

### **SECTION 2: Public Restaurant Profile**

#### **Visual Treatment:**
- **Card style:** Border-2
- **Header background:** Blue (`bg-blue-50`)
- **Icon:** Eye icon in blue badge
- **Color scheme:** Blue (public-facing, visibility)

#### **Title:**
> "Restaurant Profile (Visible to Customers)"

#### **Helper text:**
> "This information is shown on your Tavlo page and digital menu."

#### **Fields:**

1. **Restaurant Name**
   - Input field (text)
   - **Special note below:**
     > "Note: Restaurant name can be changed once, then any modification needs Tavlo admin's approval."
   - ⚠️ **Important:** This is informational only. No actual approval workflow implemented.

2. **Description**
   - Textarea (3 rows)
   - Placeholder: "Tell customers about your restaurant..."
   - Used on restaurant profile page

3. **Email**
   - Input field (email type)
   - Mail icon
   - Contact email shown to customers

4. **Phone**
   - Input field (tel type)
   - Phone icon
   - Contact phone shown to customers

5. **Website**
   - Input field (url type)
   - Globe icon
   - Placeholder: "https://..."
   - External link to restaurant website

**Purpose:**
- Makes it crystal clear what customers will see
- Vendor can confidently update customer-facing info
- Reduces risk of accidentally changing legal info

---

### **SECTION 3: Branding & Appearance**

#### **Visual Treatment:**
- **Card style:** Border-2
- **Header background:** Purple (`bg-purple-50`)
- **Icon:** Palette icon in purple badge
- **Color scheme:** Purple (creative, visual)

#### **Title:**
> "Branding & Appearance"

#### **Helper text:**
> "These images are shown on your restaurant page, QR menu, and ordering experience."

#### **Fields:**

1. **Logo**
   - Upload zone with dashed border
   - Hover effect: Border darkens
   - Accepts: image/* (PNG, JPG)
   - Max size: 5MB
   - Remove button if uploaded
   - Preview shown when uploaded

2. **Cover Photo**
   - Upload zone with dashed border
   - Hover effect: Border darkens
   - Accepts: image/* (PNG, JPG)
   - Max size: 10MB
   - Remove button if uploaded
   - Preview shown when uploaded

**Upload behavior:**
- Click anywhere in dashed box to upload
- File reader converts to base64 for preview
- No changes to existing upload logic

**Purpose:**
- Contextualizes where branding appears
- Vendors understand the impact of these images
- Clear file size limits

---

### **SECTION 4: Business Hours**

#### **Visual Treatment:**
- **Card style:** Border-2
- **Header background:** Green (`bg-green-50`)
- **Icon:** Clock icon in green badge
- **Color scheme:** Green (operational, active)

#### **Title:**
> "Business Hours"

#### **Helper text:**
> "Business hours control when customers can place orders and make reservations."

#### **Fields:**

Each day of the week:
- Day name (capitalized, font-medium)
- "Open" checkbox
- Start time input (time type)
- "to" separator
- End time input (time type)
- "Closed" text when unchecked

**Bottom Info Box:**
- **Background:** Blue (`bg-blue-50`)
- **Border:** Blue (`border-blue-200`)
- **Text:**
  > "Note: Outside these hours, ordering may be disabled depending on your Ordering settings."

**Purpose:**
- Clarifies what business hours control
- Links to Ordering settings behavior
- Vendors understand operational impact
- No logic changes, only clarity

---

## Visual Color Coding

| Section | Color | Icon | Meaning |
|---------|-------|------|---------|
| Legal Business Identity | Gray | 🔒 Lock | Formal, official, serious |
| Restaurant Profile | Blue | 👁️ Eye | Public-facing, visible |
| Branding & Appearance | Purple | 🎨 Palette | Creative, visual identity |
| Business Hours | Green | 🕐 Clock | Operational, active hours |

**Why color coding:**
- Quick visual scanning
- Easy to remember sections
- Reinforces purpose of each section
- Accessible (not relying on color alone - text + icons too)

---

## Layout Structure

### **Card Pattern (All Sections):**

```tsx
<Card className="border-2">
  <CardHeader className="bg-[COLOR]-50 border-b">
    <div className="flex items-center gap-3">
      <div className="w-10 h-10 bg-[COLOR]-200 rounded-lg flex items-center justify-center">
        <Icon className="w-5 h-5 text-[COLOR]-700" />
      </div>
      <div>
        <CardTitle className="text-lg">[TITLE]</CardTitle>
        <CardDescription className="text-sm mt-1">
          [HELPER TEXT]
        </CardDescription>
      </div>
    </div>
  </CardHeader>
  <CardContent className="pt-6">
    [FIELDS]
  </CardContent>
</Card>
```

### **Spacing:**

- Between sections: `space-y-8` (2rem / 32px)
- Within cards: `pt-6` (1.5rem / 24px)
- Grid gaps: `gap-4` (1rem / 16px)
- Card padding: Standard CardContent padding

---

## Typography

### **Section Headers:**
- Title: `text-lg` (18px)
- Description: `text-sm` (14px)

### **Field Labels:**
- Size: `text-sm font-medium text-gray-700` (14px, medium weight)
- Includes icons where relevant

### **Helper Text:**
- Size: `text-xs text-gray-500` (12px)
- Used below inputs for context

### **Warning/Info Boxes:**
- Text: `text-sm` (14px)
- Consistent with system messaging

---

## Responsive Behavior

### **Desktop (≥ 768px):**
- Legal section: 2-column grid
- Restaurant profile: Full width, then 2-column for email/phone
- Branding: 2-column grid (logo + cover side by side)
- Business hours: Single column (readable)

### **Tablet (768px - 1024px):**
- Same as desktop (maintained)

### **Mobile (< 768px):**
- All grids collapse to single column
- Fields stack vertically
- Card headers remain readable
- Icons stay visible

---

## Helper Text Strategy

### **Top Helper Text (Purpose):**
Each section starts with helper text explaining:
- What the information is used for
- Where it appears
- Who sees it

**Examples:**
- Legal: "used for invoices, tax compliance, and legal identification"
- Profile: "shown on your Tavlo page and digital menu"
- Branding: "shown on your restaurant page, QR menu, and ordering experience"
- Hours: "control when customers can place orders and make reservations"

### **Bottom Helper Text (Consequences):**
Some sections end with helper text explaining:
- Impact of changes
- Limitations or restrictions
- Related settings

**Examples:**
- Legal: "may affect invoices and tax compliance"
- Profile (Restaurant Name): "needs Tavlo admin's approval" after first change
- Hours: "ordering may be disabled" outside hours

---

## Field Reorganization

### **Field Movements:**

| Field | Before | After |
|-------|--------|-------|
| Restaurant Name | Mixed with legal | Public Profile |
| Business Reg # | Mixed with name | Legal Identity |
| VAT Number | Mixed with name | Legal Identity |
| Company Type | Mixed with name | Legal Identity |
| Address | Separate | Legal Identity (renamed "Legal Address") |
| Description | Separate | Public Profile |
| Email | Separate | Public Profile |
| Phone | Separate | Public Profile |
| Website | Separate | Public Profile |
| Logo | Under "Branding" | Branding & Appearance |
| Cover Photo | Under "Branding" | Branding & Appearance |
| Business Hours | Separate | Business Hours (contextualized) |

---

## Icon Usage

| Icon | Where Used | Purpose |
|------|------------|---------|
| `<Lock />` | Legal section header | Indicates official/locked nature |
| `<Eye />` | Profile section header | Indicates public visibility |
| `<Palette />` | Branding section header | Indicates visual/creative |
| `<Clock />` | Hours section header | Indicates time-based |
| `<Shield />` | Legal warning box | Indicates protection/compliance |
| `<MapPin />` | Legal Address label | Indicates location |
| `<Mail />` | Email label | Indicates email type |
| `<Phone />` | Phone label | Indicates phone type |
| `<Globe />` | Website label | Indicates external link |
| `<Upload />` | Logo/Cover upload | Indicates upload action |

---

## Constraints Met

✅ **Do NOT add new fields** - All fields preserved from original  
✅ **Do NOT change backend behavior** - Only UI/UX changes  
✅ **Do NOT introduce approval workflows** - Note about admin approval is informational only  
✅ **Do NOT split into multiple pages** - Everything remains on one page  
✅ **Keep Save/Cancel unchanged** - Footer behavior unchanged  

---

## Important Notes

### **Restaurant Name Approval:**

The text says:
> "Restaurant name can be changed once, then any modification needs Tavlo admin's approval."

**⚠️ This is INFORMATIONAL ONLY.**
- No actual approval workflow implemented
- No backend check for "changed once"
- Serves as a warning/guidance to vendors
- Actual enforcement would require backend changes

**Why include it?**
- Sets expectations for vendors
- Prevents casual name changes
- Reduces support tickets about "stuck" names

---

### **Business Hours Note:**

The text says:
> "Outside these hours, ordering may be disabled depending on your Ordering settings."

**This is CONTEXTUAL LINKING.**
- Links Business Hours to Ordering settings
- Explains operational impact
- No logic changes
- Just helps vendors understand relationships

---

## Before/After Comparison

### **Before: Mixed Information**

```
Restaurant Name: ____________
Business Reg: ____________
VAT: ____________
Company Type: [dropdown]

Description: ________________

Email: ____________  Phone: ____________
Website: ____________
Address: ________________

[Business Hours grid]

[Logo upload]  [Cover upload]
```

**Issues:**
- Legal and public info mixed
- No indication of visibility
- No context for purpose
- Hard to scan/understand

---

### **After: Clear Separation**

```
┌─────────────────────────────────┐
│ 🔒 LEGAL BUSINESS INFORMATION  │ GRAY
│ For invoices, tax, legal ID     │
├─────────────────────────────────┤
│ [Legal fields...]               │
│ ⚠️ Changes affect compliance    │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 👁️ RESTAURANT PROFILE           │ BLUE
│ Visible to Customers            │
├─────────────────────────────────┤
│ [Public fields...]              │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 🎨 BRANDING & APPEARANCE        │ PURPLE
│ Shown on page, menu, ordering   │
├─────────────────────────────────┤
│ [Image uploads...]              │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 🕐 BUSINESS HOURS               │ GREEN
│ Controls orders & reservations  │
├─────────────────────────────────┤
│ [Days/times...]                 │
│ ℹ️ Disabled outside these hours │
└─────────────────────────────────┘
```

**Benefits:**
- Clear purpose for each section
- Easy to scan with color + icons
- Explicit about visibility
- Contextual warnings where needed

---

## User Benefits

### **1. Reduced Mistakes**
- Legal fields clearly separated from public info
- Vendors less likely to accidentally change wrong field
- Warnings about consequences

### **2. Better Understanding**
- Each section explains its purpose
- Vendors know what customers see
- Operational impact is clear

### **3. Confidence**
- Can safely update public profile
- Knows when to be careful (legal changes)
- Understands image usage

### **4. Time Savings**
- Quick visual scanning with colors
- No confusion about field purposes
- Less support needed

---

## Testing Checklist

### **Visual:**
- [ ] All 4 sections use Card component
- [ ] Color scheme correct (gray, blue, purple, green)
- [ ] Icons display correctly in headers
- [ ] Helper text visible in all sections
- [ ] Warning/info boxes display correctly

### **Functional:**
- [ ] All input fields work as before
- [ ] Business hours checkboxes toggle
- [ ] Logo upload works
- [ ] Cover photo upload works
- [ ] Remove buttons work
- [ ] Save/Cancel buttons work

### **Content:**
- [ ] Legal section shows correct helper text
- [ ] Profile section mentions admin approval for name
- [ ] Branding section explains where images appear
- [ ] Hours section explains operational impact

### **Responsive:**
- [ ] Desktop: 2-column grids work
- [ ] Mobile: Single column layout
- [ ] Cards stack properly
- [ ] Text remains readable

---

## Accessibility

### **Screen Readers:**
- ✓ CardTitle provides section heading
- ✓ CardDescription provides context
- ✓ All inputs have labels
- ✓ Icons have semantic parent elements

### **Keyboard Navigation:**
- ✓ All inputs focusable
- ✓ Tab order logical (top to bottom)
- ✓ Upload zones keyboard accessible

### **Visual:**
- ✓ Color + text + icons (not color alone)
- ✓ Sufficient contrast on backgrounds
- ✓ Icons supplement, not replace text

---

## File Changes Summary

### **Modified Files:**

**1. `/components/vendor/Settings.tsx`**
- Added `Lock` and `Eye` icons to imports
- Completely rewrote `renderBusinessInfo()` function
- Introduced 4-card structure
- Added contextual helper text throughout
- Improved visual hierarchy with color coding

**No other files modified.**

---

## Future Considerations

### **Possible Enhancements (Not Implemented):**

1. **Restaurant Name Enforcement**
   - Track if name was changed before
   - Add backend flag: `nameChangeRequiresApproval: boolean`
   - Show approval status in UI

2. **Legal Field Validation**
   - Validate VAT number format
   - Check registration number format
   - Warn on invalid entries

3. **Business Hours Presets**
   - "Copy to all weekdays" button
   - "Set weekend hours" button
   - Save as template

4. **Image Optimization**
   - Auto-resize uploaded images
   - Compress to reasonable file size
   - Generate thumbnails

5. **Multi-language Support**
   - Allow restaurant name in multiple languages
   - Description translations
   - Language selector per field

---

**Last Updated:** January 2026  
**Version:** 2.0 (Redesigned)