/**
 * Action Story Template
 * 
 * Each action follows this structure:
 * 
 * ID Format: TAV-[ACTOR]-[DOMAIN]-[NUMBER]
 * - ACTOR: ADM (Admin), VEN (Vendor), CUS (Customer), SYS (System)
 * - DOMAIN: ACC (Account), LEG (Legal), BIL (Billing), MEN (Menu), INV (Inventory), 
 *           ORD (Ordering), PAY (Payment), REV (Reviews), ADM (Admin), SYS (System)
 * 
 * 6 Required Sections:
 * 1. Trigger - What initiates this action
 * 2. Pre-conditions - What must be true before action can occur
 * 3. System Action - What the system does
 * 4. UI Updates - What the user sees
 * 5. Failure States - What can go wrong
 * 6. Success Outcome - Final state when successful
 */

export interface ActionStory {
  id: string;
  name: string;
  trigger: string;
  preconditions: string;
  systemAction: string;
  uiUpdates: string;
  failureStates: string;
  successOutcome: string;
}

export const ActionStoryTemplate = () => null;
