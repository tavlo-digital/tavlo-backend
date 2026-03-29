# Business Info Redesign - Visual Comparison

## Page Layout Overview

### BEFORE (Mixed Layout)
```
┌─────────────────────────────────────────────────┐
│  BUSINESS INFO                                  │
├─────────────────────────────────────────────────┤
│                                                 │
│  Restaurant Details                             │
│  ┌──────────────┐  ┌──────────────┐            │
│  │ Rest. Name   │  │ Bus. Reg #   │            │
│  └──────────────┘  └──────────────┘            │
│  ┌──────────────┐  ┌──────────────┐            │
│  │ VAT Number   │  │ Company Type │            │
│  └──────────────┘  └──────────────┘            │
│                                                 │
│  Description                                    │
│  ┌───────────────────────────────┐             │
│  │                               │             │
│  └───────────────────────────────┘             │
│                                                 │
│  ┌──────────────┐  ┌──────────────┐            │
│  │ 📧 Email     │  │ 📞 Phone     │            │
│  └──────────────┘  └──────────────┘            │
│                                                 │
│  Website                                        │
│  ┌───────────────────────────────┐             │
│  └───────────────────────────────┘             │
│                                                 │
│  📍 Address                                     │
│  ┌───────────────────────────────┐             │
│  └───────────────────────────────┘             │
│                                                 │
│  Business Hours                                 │
│  [Days/times grid...]                           │
│                                                 │
│  Branding                                       │
│  ┌──────────┐  ┌──────────┐                    │
│  │ Logo     │  │ Cover    │                    │
│  └──────────┘  └──────────┘                    │
│                                                 │
│  [Cancel]                        [Save]        │
└─────────────────────────────────────────────────┘
```

**Problems:**
❌ Legal and public info mixed together  
❌ No visual hierarchy or grouping  
❌ Hard to understand field purposes  
❌ No indication of customer visibility  

---

### AFTER (Structured Cards)
```
┌─────────────────────────────────────────────────┐
│  BUSINESS INFO                                  │
├─────────────────────────────────────────────────┤
│                                                 │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  │
│  ┃ 🔒 LEGAL BUSINESS INFORMATION          ┃  │ GRAY CARD
│  ┃ For invoices, tax, legal identification┃  │
│  ┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫  │
│  ┃ ┌────────────┐  ┌────────────┐         ┃  │
│  ┃ │ Bus. Reg # │  │ VAT Number │         ┃  │
│  ┃ └────────────┘  └────────────┘         ┃  │
│  ┃ ┌────────────┐  ┌────────────────────┐ ┃  │
│  ┃ │ Company    │  │ Legal Address      │ ┃  │
│  ┃ │ Type       │  │                    │ ┃  │
│  ┃ └────────────┘  └────────────────────┘ ┃  │
│  ┃                                         ┃  │
│  ┃ ⚠️ Changes may affect invoices/tax     ┃  │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  │
│                                                 │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  │
│  ┃ 👁️ RESTAURANT PROFILE                  ┃  │ BLUE CARD
│  ┃ (Visible to Customers)                  ┃  │
│  ┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫  │
│  ┃ Restaurant Name                         ┃  │
│  ┃ ┌───────────────────────────────────┐   ┃  │
│  ┃ └───────────────────────────────────┘   ┃  │
│  ┃ ℹ️ Admin approval after first change   ┃  │
│  ┃                                         ┃  │
│  ┃ Description                             ┃  │
│  ┃ ┌───────────────────────────────────┐   ┃  │
│  ┃ │                                   │   ┃  │
│  ┃ └───────────────────────────────────┘   ┃  │
│  ┃                                         ┃  │
│  ┃ ┌──────────────┐  ┌──────────────┐     ┃  │
│  ┃ │ 📧 Email     │  │ 📞 Phone     │     ┃  │
│  ┃ └──────────────┘  └──────────────┘     ┃  │
│  ┃                                         ┃  │
│  ┃ 🌐 Website                              ┃  │
│  ┃ ┌───────────────────────────────────┐   ┃  │
│  ┃ └───────────────────────────────────┘   ┃  │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  │
│                                                 │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  │
│  ┃ 🎨 BRANDING & APPEARANCE                ┃  │ PURPLE CARD
│  ┃ Shown on page, QR menu, ordering        ┃  │
│  ┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫  │
│  ┃ ┌──────────────┐  ┌──────────────┐     ┃  │
│  ┃ │ Logo         │  │ Cover Photo  │     ┃  │
│  ┃ │ [Upload]     │  │ [Upload]     │     ┃  │
│  ┃ └──────────────┘  └──────────────┘     ┃  │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  │
│                                                 │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  │
│  ┃ 🕐 BUSINESS HOURS                       ┃  │ GREEN CARD
│  ┃ Controls orders & reservations          ┃  │
│  ┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫  │
│  ┃ [Days/times grid...]                    ┃  │
│  ┃                                         ┃  │
│  ┃ ℹ️ Ordering disabled outside hours     ┃  │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  │
│                                                 │
│  [Cancel]                        [Save]        │
└─────────────────────────────────────────────────┘
```

**Benefits:**
✅ Clear visual separation by purpose  
✅ Color-coded sections for quick scanning  
✅ Contextual helper text in each section  
✅ Explicit about customer visibility  

---

## Section Details

### SECTION 1: Legal Business Identity

#### **Visual Design**
```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                                              ┃
┃  ┌────┐                                     ┃ HEADER
┃  │ 🔒 │  Legal Business Information         ┃ bg-gray-50
┃  └────┘  For invoices, tax, legal ID        ┃
┃                                              ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃                                              ┃
┃  Business Registration Number                ┃
┃  ┌────────────────────────────────────────┐  ┃
┃  │ FN 123456a                             │  ┃
┃  └────────────────────────────────────────┘  ┃
┃                                              ┃
┃  VAT Number                                  ┃
┃  ┌────────────────────────────────────────┐  ┃
┃  │ ATU12345678                            │  ┃
┃  └────────────────────────────────────────┘  ┃
┃                                              ┃
┃  Company Type                                ┃
┃  ┌────────────────────────────────────────┐  ┃
┃  │ GmbH ▼                                 │  ┃
┃  └────────────────────────────────────────┘  ┃
┃                                              ┃
┃  📍 Legal Address                            ┃
┃  ┌────────────────────────────────────────┐  ┃
┃  │ Kärntner Straße 1, 1010 Wien           │  ┃
┃  └────────────────────────────────────────┘  ┃
┃                                              ┃
┃  ┌──────────────────────────────────────┐   ┃
┃  │ 🛡️ Note: Any changes to legal       │   ┃ AMBER WARNING
┃  │   information require Tavlo admin   │   ┃
┃  │   approval for verification.        │   ┃
┃  │                                      │   ┃
┃  │   Changes to legal information may  │   ┃
┃  │   affect invoices and tax compliance│   ┃
┃  └──────────────────────────────────────┘   ┃
┃                                              ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
```

**Key Features:**
- Gray color scheme (formal)
- Lock icon (indicates official)
- Warning at bottom (amber)
- All legal fields grouped together

---

### SECTION 2: Restaurant Profile

#### **Visual Design**
```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                                              ┃
┃  ┌────┐                                     ┃ HEADER
┃  │ 👁️ │  Restaurant Profile                 ┃ bg-blue-50
┃  └────┘  (Visible to Customers)             ┃
┃                                              ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃                                              ┃
┃  Restaurant Name                             ┃
┃  ┌────────────────────────────────────────┐  ┃
┃  │ La Bella Vista                         │  ┃
┃  └────────────────────────────────────────┘  ┃
┃  ℹ️ Admin approval after first change       ┃
┃                                              ┃
┃  Description                                 ┃
┃  ┌────────────────────────────────────────┐  ┃
┃  │ Authentic Italian cuisine in the       │  ┃
┃  │ heart of Vienna                        │  ┃
┃  └────────────────────────────────────────┘  ┃
┃                                              ┃
┃  📧 Email          📞 Phone                  ┃
┃  ┌──────────────┐  ┌──────────────┐         ┃
┃  │ info@...     │  │ +43 1 234..  │         ┃
┃  └──────────────┘  └──────────────┘         ┃
┃                                              ┃
┃  🌐 Website                                  ┃
┃  ┌────────────────────────────────────────┐  ┃
┃  │ www.labellavista.at                    │  ┃
┃  └────────────────────────────────────────┘  ┃
┃                                              ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
```

**Key Features:**
- Blue color scheme (public-facing)
- Eye icon (indicates visibility)
- "(Visible to Customers)" in title
- Note about admin approval
- Icons for contact fields

---

### SECTION 3: Branding & Appearance

#### **Visual Design**
```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                                              ┃
┃  ┌────┐                                     ┃ HEADER
┃  │ 🎨 │  Branding & Appearance              ┃ bg-purple-50
┃  └────┘  Shown on page, QR menu, ordering   ┃
┃                                              ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃                                              ┃
┃  Logo                    Cover Photo         ┃
┃  ┌──────────────┐       ┌──────────────┐    ┃
┃  │              │       │              │    ┃
┃  │     📤       │       │     📤       │    ┃ UPLOAD ZONES
┃  │  Upload logo │       │ Upload cover │    ┃
┃  │              │       │              │    ┃
┃  │ PNG, JPG     │       │ PNG, JPG     │    ┃
┃  │ up to 5MB    │       │ up to 10MB   │    ┃
┃  └──────────────┘       └──────────────┘    ┃
┃                                              ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
```

**Key Features:**
- Purple color scheme (creative)
- Palette icon (indicates visual)
- Helper text explains where images appear
- Side-by-side upload zones
- Clear file size limits

---

### SECTION 4: Business Hours

#### **Visual Design**
```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                                              ┃
┃  ┌────┐                                     ┃ HEADER
┃  │ 🕐 │  Business Hours                     ┃ bg-green-50
┃  └────┘  Controls orders & reservations     ┃
┃                                              ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃                                              ┃
┃  Monday    ☑ Open  [11:00] to [22:00]       ┃
┃  Tuesday   ☑ Open  [11:00] to [22:00]       ┃
┃  Wednesday ☑ Open  [11:00] to [22:00]       ┃
┃  Thursday  ☑ Open  [11:00] to [22:00]       ┃
┃  Friday    ☑ Open  [11:00] to [22:00]       ┃
┃  Saturday  ☑ Open  [11:00] to [22:00]       ┃
┃  Sunday    ☐ Open  Closed                   ┃
┃                                              ┃
┃  ┌──────────────────────────────────────┐   ┃
┃  │ ℹ️ Outside these hours, ordering may │   ┃ BLUE INFO
┃  │   be disabled per Ordering settings  │   ┃
┃  └──────────────────────────────────────┘   ┃
┃                                              ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
```

**Key Features:**
- Green color scheme (operational)
- Clock icon (indicates time)
- Helper text explains operational impact
- Info box links to Ordering settings
- Clear day-by-day layout

---

## Color Palette

### **Section Headers**

| Section | Background | Border | Icon BG | Icon Color | Text |
|---------|-----------|--------|---------|------------|------|
| Legal | `bg-gray-50` | `border-gray-200` | `bg-gray-200` | `text-gray-700` | Gray |
| Profile | `bg-blue-50` | `border-blue-200` | `bg-blue-200` | `text-blue-700` | Blue |
| Branding | `bg-purple-50` | `border-purple-200` | `bg-purple-200` | `text-purple-700` | Purple |
| Hours | `bg-green-50` | `border-green-200` | `bg-green-200` | `text-green-700` | Green |

### **Info Boxes**

| Type | Background | Border | Text |
|------|-----------|--------|------|
| Warning | `bg-amber-50` | `border-amber-200` | `text-amber-900` |
| Info | `bg-blue-50` | `border-blue-200` | `text-blue-900` |

---

## Icon Badge Design

Each section header has an icon badge:

```
┌────────────────────────────────┐
│                                │
│  ┌────┐                        │
│  │    │  [SECTION TITLE]       │
│  │ 🔒 │  [Helper text]          │
│  │    │                        │
│  └────┘                        │
│                                │
└────────────────────────────────┘
```

**Badge specifications:**
- Size: `w-10 h-10` (40px × 40px)
- Rounded: `rounded-lg`
- Background: Color-specific (e.g., `bg-gray-200`)
- Icon size: `w-5 h-5` (20px × 20px)
- Flex center: `flex items-center justify-center`

---

## Field Layout Patterns

### **Single Column Fields**
```
Label
┌────────────────────────────────┐
│ Input value                    │
└────────────────────────────────┘
Helper text (if applicable)
```

### **Two Column Grid**
```
Label 1                Label 2
┌──────────────┐      ┌──────────────┐
│ Input value  │      │ Input value  │
└──────────────┘      └──────────────┘
```

### **Full-Span Field**
```
Label
┌────────────────────────────────────────┐
│ Textarea or long input                 │
│                                        │
└────────────────────────────────────────┘
```

---

## Spacing Hierarchy

```
┌─────────────────────────────────┐
│                                 │
│  Section 1 (Card)               │ ←──┐
│                                 │    │
└─────────────────────────────────┘    │
                                       │ 32px gap
┌─────────────────────────────────┐    │ (space-y-8)
│                                 │    │
│  Section 2 (Card)               │ ←──┘
│  ┌─────────────────────────┐   │
│  │ CardHeader              │   │ ←── 0px (border-b)
│  └─────────────────────────┘   │
│  ┌─────────────────────────┐   │
│  │ CardContent             │   │ ←── pt-6 (24px top padding)
│  │                         │   │
│  │ Field 1                 │   │
│  │ ↕ 16px gap (gap-4)      │   │
│  │ Field 2                 │   │
│  │                         │   │
│  └─────────────────────────┘   │
│                                 │
└─────────────────────────────────┘
```

---

## Responsive Breakpoints

### **Desktop (≥ 768px)**
```
┌────────────────────────────────────────┐
│ Legal Section                          │
│ ┌───────────┐  ┌───────────┐          │
│ │ Bus. Reg  │  │ VAT       │  2 cols  │
│ └───────────┘  └───────────┘          │
│ ┌───────────┐  ┌───────────────────┐  │
│ │ Company   │  │ Legal Address     │  │
│ └───────────┘  └───────────────────┘  │
└────────────────────────────────────────┘

┌────────────────────────────────────────┐
│ Profile Section                        │
│ Restaurant Name (full width)           │
│ Description (full width)               │
│ ┌───────────┐  ┌───────────┐          │
│ │ Email     │  │ Phone     │  2 cols  │
│ └───────────┘  └───────────┘          │
│ Website (full width)                   │
└────────────────────────────────────────┘

┌────────────────────────────────────────┐
│ Branding Section                       │
│ ┌───────────┐  ┌───────────┐          │
│ │ Logo      │  │ Cover     │  2 cols  │
│ └───────────┘  └───────────┘          │
└────────────────────────────────────────┘
```

### **Mobile (< 768px)**
```
┌──────────────────┐
│ Legal Section    │
│ ┌──────────────┐ │
│ │ Bus. Reg     │ │ 1 col
│ └──────────────┘ │
│ ┌──────────────┐ │
│ │ VAT          │ │
│ └──────────────┘ │
│ ┌──────────────┐ │
│ │ Company      │ │
│ └──────────────┘ │
│ ┌──────────────┐ │
│ │ Legal Addr   │ │
│ └──────────────┘ │
└──────────────────┘

┌──────────────────┐
│ Profile Section  │
│ ┌──────────────┐ │
│ │ Name         │ │ 1 col
│ └──────────────┘ │
│ ┌──────────────┐ │
│ │ Description  │ │
│ └──────────────┘ │
│ ┌──────────────┐ │
│ │ Email        │ │
│ └──────────────┘ │
│ ┌──────────────┐ │
│ │ Phone        │ │
│ └──────────────┘ │
└──────────────────┘

┌──────────────────┐
│ Branding Section │
│ ┌──────────────┐ │
│ │ Logo         │ │ 1 col
│ └──────────────┘ │
│ ┌──────────────┐ │
│ │ Cover        │ │
│ └──────────────┘ │
└──────────────────┘
```

---

## User Flow Diagram

```
User Opens Business Info
         ↓
┌────────────────────┐
│ 🔒 Legal Section   │ ← Sees warning about compliance
│ Formal gray card   │ ← Fills legal fields carefully
└────────────────────┘
         ↓
┌────────────────────┐
│ 👁️ Profile Section │ ← Knows this is customer-facing
│ Blue card          │ ← Updates contact info freely
└────────────────────┘
         ↓
┌────────────────────┐
│ 🎨 Branding        │ ← Understands where images appear
│ Purple card        │ ← Uploads logo and cover
└────────────────────┘
         ↓
┌────────────────────┐
│ 🕐 Business Hours  │ ← Sees operational impact note
│ Green card         │ ← Sets hours confidently
└────────────────────┘
         ↓
    Clicks Save
         ↓
  Settings Updated
```

---

## Key Visual Differences

| Aspect | Before | After |
|--------|--------|-------|
| **Structure** | Flat list | 4 distinct cards |
| **Grouping** | Mixed fields | Logical separation |
| **Colors** | Monochrome | Color-coded by purpose |
| **Icons** | Field-level only | Section headers + fields |
| **Helper Text** | Minimal | Comprehensive |
| **Visual Hierarchy** | Weak | Strong (cards, colors, icons) |
| **Scanning** | Difficult | Easy (color + icons) |
| **Understanding** | Unclear | Explicit (purpose stated) |

---

## Accessibility Features

### **Color + Text + Icons**
Never relies on color alone:
- 🔒 Lock + Gray + "Legal" = Official
- 👁️ Eye + Blue + "Visible" = Public
- 🎨 Palette + Purple + "Branding" = Visual
- 🕐 Clock + Green + "Hours" = Operational

### **Semantic Structure**
```html
<Card>                     <!-- Semantic grouping -->
  <CardHeader>            <!-- Section header -->
    <CardTitle>          <!-- h3 level heading -->
    <CardDescription>    <!-- Supporting text -->
  </CardHeader>
  <CardContent>           <!-- Main content area -->
    <label>              <!-- Accessible labels -->
    <input>              <!-- Form controls -->
  </CardContent>
</Card>
```

### **Focus States**
All interactive elements have focus states:
- Input fields: `focus:ring-2 focus:ring-gray-900`
- Upload zones: Keyboard accessible
- Checkboxes: Native focus indicators

---

**Last Updated:** January 2026
