**Figma Maker Prompt**

Improve the existing **Admin → Insights & Analysis (AI Platform Insights)** interface for Tavlo.
The goal is to **extend the current design with additional analytics capabilities** while **preserving the existing layout, structure, and visual style**.

Do NOT redesign the page.
Do NOT remove or move existing components.
Do NOT change existing UI elements.

Only **add enhancements and new analytics widgets that integrate naturally into the current design system.**

The visual language, spacing, cards, and typography must remain consistent with the existing Tavlo admin interface.

---

### 1. Vendor Health Score (New Feature)

Add a **Vendor Health Score system** that summarizes vendor platform health in a single score.

This should be implemented as a **new analytics layer that complements existing insights such as Subscription Risk, Revenue Opportunities, and Platform Health.**

#### Add a new dashboard card in the top metrics row:

**Vendor Health Overview**

Contents:

• Healthy Vendors
• Stable Vendors
• Warning Vendors
• High-Risk Vendors

Display counts for each category.

Color coding:

Green – Healthy (80–100)
Blue – Stable (60–79)
Orange – Warning (40–59)
Red – High Risk (0–39)

Include a small trend indicator (up/down compared to last week).

This card must match the style of existing cards such as:

Active Subscriptions
Churn Risk
Payment Success Rate
Platform Health Score

---

### 2. Vendor Health Score Details (New Section)

Within the **Subscription Risk tab**, enhance vendor cards by adding a **Vendor Health Score indicator**.

Each vendor card should display:

Vendor Health Score (0–100)

Example layout:

Vendor name
Health Score badge
Risk Score
Indicators

Use a circular or pill indicator with color coding:

Green
Blue
Orange
Red

Under the score, show contributing factors:

• Platform Engagement
• Payment Reliability
• Subscription Stability
• Support Activity
• Growth Trend

These should appear as small progress bars or indicators.

Example:

Platform Engagement – High
Payment Reliability – Perfect
Subscription Stability – 18 months
Support Activity – Low
Growth Trend – Increasing

This feature must **extend the existing vendor risk cards**, not replace them.

---

### 3. Vendor Health Overview Panel

Add a **new expandable analytics panel** below the Platform Health section.

Title:

Vendor Ecosystem Health

Contents:

• Average Vendor Health Score
• Distribution of vendors across health categories
• Mini chart showing vendor health trend over time

Use a simple bar chart or distribution visualization.

This section should visually match the existing **Subscription Metrics and Payment Health sections.**

---

### 4. Automated Retention Intelligence (New Feature)

Enhance the **Subscription Risk tab** with **AI-recommended retention actions.**

When a vendor has:

Low engagement
Failed payments
Trial ending soon
Subscription risk signals

Show a new section inside the vendor card:

**AI Retention Recommendation**

Example content:

Recommended Action
Contact vendor regarding inactivity and offer onboarding assistance.

Include a **confidence indicator**.

Example:

Confidence: 82%

Add suggested action buttons:

Contact Vendor
Schedule Follow-Up
Send Retention Offer

Buttons must match the style used elsewhere in the admin interface.

---

### 5. Automated Revenue Intelligence

Enhance the **Revenue Opportunities tab** with stronger AI insights.

For each opportunity card add:

Growth Signals

Examples:

Feature usage nearing plan limits
High QR scan volume
Multiple menu updates
Frequent platform logins

Add a **Projected Revenue Impact indicator**.

Example:

Potential Additional MRR
+€100/month

Add a visual confidence indicator:

Confidence: 87%

Maintain the existing card layout.

---

### 6. Vendor Lifecycle Insights (Optional Enhancement)

Add a small analytics section under Subscription Metrics.

Title:

Vendor Lifecycle Insights

Display:

• New Vendors (last 30 days)
• Growing Vendors
• At-Risk Vendors
• Churned Vendors

Use small stat cards consistent with existing metrics styling.

---

### 7. UI and Design Constraints

Strict rules:

• Do NOT remove existing components
• Do NOT change navigation
• Do NOT move current cards
• Do NOT alter colors or typography
• Maintain Tavlo design system consistency
• New features must look native to the existing interface

Spacing, card shapes, icons, and labels should match the current design.

---

### 8. Design Goal

The goal is to make the Insights & Analysis page:

More actionable for admins
Better at identifying vendor risks
Better at identifying revenue opportunities
More intelligent without increasing complexity

The page should still feel clean, structured, and easy to scan.
