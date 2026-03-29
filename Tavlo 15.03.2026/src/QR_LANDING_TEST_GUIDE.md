# 🧪 QR Landing Page - Testing Guide

## Quick Start

### 1. Access Test Mode

Click the **mode switcher** button in the bottom-left corner (shows current mode like "🏠 Platform")

Select **"🧪 QR Test"** from the dropdown menu

### 2. Test All 6 States

Use the **blue button bar** at the top to switch between states:

#### **State 1: Empty** 
- **What it is**: Initial QR scan, no existing session
- **What to test**: 
  - Click "Start Order" → Opens auth modal
  - Try "Continue as Guest" → Creates session and shows PIN
  - Check language selector works
  - Test "Call the Waiter" button

#### **State 2: PIN Display** ✨ NEW DESIGN
- **What it is**: First person who created the session
- **What to test**:
  - See the **4 large gold PIN boxes** (e.g., 4982)
  - Click "Copy PIN" → Should show "Copied!" feedback
  - Click "Start Ordering" → Simulates going to menu
  - PIN should be clearly visible and memorable
  - Check the helper text explains sharing

#### **State 3: Joinable** ✨ NEW DESIGN  
- **What it is**: Second person joining an existing session
- **What to test**:
  - **Enter PIN**: Type `4982` (or the PIN shown in state info)
  - Auto-advance between input boxes
  - Auto-submit when 4th digit entered
  - Try wrong PIN → Should see shake animation and error
  - Click "Join Table" button
  - Check "Start New Order Instead" link (only shows for Draft sessions)

#### **State 4: Abandoned Draft**
- **What it is**: Unfinished order with no items sent to kitchen
- **What to test**:
  - See warning message about unfinished order
  - Click "Start New Order" → Opens confirmation modal
  - Confirm deletion → Creates new session with new PIN
  - Or enter PIN to join existing session

#### **State 5: Cash Pending**
- **What it is**: Payment is pending, table is blocked
- **What to test**:
  - Only action available: "Call Waiter"
  - All other actions disabled
  - See timer icon and clear message

#### **State 6: Security Block**
- **What it is**: Too many QR scans detected (abuse prevention)
- **What to test**:
  - See shield icon and security message
  - Only action: "Ask Staff for Help"
  - Check explanation of why block occurred

---

## 📋 Test Checklist

### Visual Design
- [ ] Hero image loads correctly
- [ ] Restaurant logo displays (if available)
- [ ] Dark gradient overlay provides good contrast
- [ ] Language dropdown works smoothly
- [ ] Table badge shows with pulse animation
- [ ] All cards have warm, premium styling
- [ ] Gold accent color (#d4a574) is visible
- [ ] Buttons are large and touch-friendly

### PIN Display (State 2)
- [ ] PIN digits are large and clear (gold boxes)
- [ ] Staggered animation on PIN reveal
- [ ] Copy button works with visual feedback
- [ ] Helper text is prominent
- [ ] "Start Ordering" CTA is clear

### PIN Input (State 3)
- [ ] 4 input boxes auto-focus correctly
- [ ] Auto-advance to next box
- [ ] Auto-submit on 4th digit
- [ ] Shake animation on error
- [ ] Error message displays clearly
- [ ] Benefits list shows why joining is good

### Interactions
- [ ] All modals slide up smoothly
- [ ] Auth modal offers Guest/Sign In/Register
- [ ] Delete draft modal requires confirmation
- [ ] Language selector changes language
- [ ] "Call Waiter" button always accessible
- [ ] Buttons have hover states

### Mobile Experience
- [ ] Works on phone-sized screens (375px+)
- [ ] Touch targets are large enough (44px+)
- [ ] Text is readable without zooming
- [ ] Cards don't overflow screen
- [ ] Sticky bottom bar stays in place

---

## 🎯 Key User Flows to Test

### Flow 1: First Person Starting Order
1. Start in **Empty** state
2. Click "Start Order"
3. Choose "Continue as Guest"
4. See **PIN Display** state with large PIN
5. Copy PIN (test copy button)
6. Click "Start Ordering"

### Flow 2: Second Person Joining
1. Switch to **Joinable** state  
2. Look at state info panel for correct PIN
3. Type PIN digit by digit (watch auto-advance)
4. Auto-submit should trigger on 4th digit
5. Alert shows "Successfully joined!"

### Flow 3: Recovering from Abandoned Draft
1. Switch to **Abandoned Draft** state
2. Click "Start New Order"
3. See confirmation modal
4. Confirm deletion
5. New session created with new PIN

---

## 🔧 Console Logging

All event handlers log to console:

```javascript
✅ Start New Session: { authChoice: 'guest', language: 'en' }
✅ Join Session with PIN: 4982
✅ Delete Draft & Start New: { authChoice: 'signin' }
🔔 Call Waiter
🍽️ Continue to Menu
```

Open browser DevTools (F12) to see these logs.

---

## 💡 Pro Tips

1. **Test on Real Mobile Device**: 
   - Open the preview on your phone
   - Test touch interactions
   - Check if keyboard obscures inputs

2. **Test Different Screen Sizes**:
   - Use browser DevTools responsive mode
   - Try iPhone SE (375px) to iPhone 14 Pro Max (430px)

3. **Test PIN Entry Speed**:
   - Type PIN quickly to test auto-submit
   - Try backspace navigation between boxes

4. **Check Accessibility**:
   - Tab through inputs with keyboard
   - Test with screen reader (optional)

5. **Test Error States**:
   - Enter wrong PIN in Joinable state
   - Watch for shake animation
   - Verify error message appears

---

## 🐛 Known Limitations (Test Mode Only)

- Backend is not connected (simulated responses)
- PIN validation is client-side only (accepts hardcoded PIN)
- Session creation doesn't persist
- Language changes don't affect all text (demo mode)

---

## 📸 What You Should See

### State 2 - PIN Display
```
┌─────────────────────────────┐
│  🍽️                         │
│  You're all set to order    │
│                             │
│  Your Table PIN             │
│  ┌───┐ ┌───┐ ┌───┐ ┌───┐  │
│  │ 4 │ │ 9 │ │ 8 │ │ 2 │  │  ← Large gold boxes
│  └───┘ └───┘ └───┘ └───┘  │
│                             │
│  [Copy PIN]                 │
│                             │
│  💡 Anyone at your table... │
│                             │
│  [Start Ordering]           │
└─────────────────────────────┘
```

### State 3 - PIN Input
```
┌─────────────────────────────┐
│  🔒                         │
│  Someone already started    │
│  ordering                   │
│                             │
│  Enter the 4-digit PIN      │
│  ┌───┐ ┌───┐ ┌───┐ ┌───┐  │
│  │ _ │ │ _ │ │ _ │ │ _ │  │  ← Input boxes
│  └───┘ └───┘ └───┘ └───┘  │
│                             │
│  [Join Table]               │
│                             │
│  ✓ Add items to cart        │
│  ✓ See live updates         │
│  ✓ Pay separately           │
└─────────────────────────────┘
```

---

## ✅ Success Criteria

The redesign is successful if:

1. **PIN is unmissable** in State 2 (first person)
2. **PIN entry is effortless** in State 3 (second person)
3. **No configuration clutter** (no "how many people", no toggles)
4. **Feels restaurant-like**, not like a SaaS form
5. **Mobile-first** and touch-optimized
6. **Clear visual hierarchy** at every state
7. **Warm, premium aesthetic** with gold accents

---

## 🆘 Need Help?

- **State not showing correctly?** Check the state info panel at the top
- **PIN not working?** Use the PIN shown in the blue info box (e.g., 4982)
- **Buttons not responding?** Check console for errors (F12)
- **Want to reset?** Refresh the page and start over

Happy Testing! 🎉
