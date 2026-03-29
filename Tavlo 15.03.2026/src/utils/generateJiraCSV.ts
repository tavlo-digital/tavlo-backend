import { ActionStory } from '../components/documentation/ActionStoryTemplate';
import { platformActions } from '../components/documentation/PlatformActions';
import { customerActionsPart1 } from '../components/documentation/CustomerActions_Part1';
import { customerActionsPart2 } from '../components/documentation/CustomerActions_Part2';

/**
 * Generates Jira-compatible CSV from Action Story documentation
 * Ready for bulk import without manual editing
 */

interface JiraTask {
  issueType: string;
  summary: string;
  description: string;
  projectKey: string;
  epicLink: string;
  labels: string;
  priority: string;
}

function getEpicFromDomain(domain: string): string {
  const epicMap: Record<string, string> = {
    'ACC': 'Vendor Onboarding',
    'LEG': 'Vendor Onboarding',
    'BIL': 'Vendor Subscription',
    'MEN': 'Menu Management',
    'INV': 'Inventory',
    'ORD': 'Customer Ordering',
    'PAY': 'Customer Ordering',
    'REV': 'Reviews',
    'ADM': 'Admin Control',
    'SYS': 'Platform Core'
  };
  return epicMap[domain] || 'Platform Core';
}

function getPriority(actionStory: ActionStory): string {
  const { id, name, systemAction, trigger } = actionStory;
  const text = `${name} ${systemAction} ${trigger}`.toLowerCase();
  
  // High priority: money, orders, payments, access
  if (text.match(/payment|pay|order|checkout|refund|subscription|billing|access|auth|login/)) {
    return 'High';
  }
  
  // Low priority: reviews, content, non-critical
  if (text.match(/review|rating|favorite|save|analytics|report/)) {
    return 'Low';
  }
  
  // Default: Medium for setup, management, automation
  return 'Medium';
}

function getInitiator(actionId: string): string {
  if (actionId.includes('ADM-')) return 'admin';
  if (actionId.includes('VEN-')) return 'vendor';
  if (actionId.includes('CUS-')) return 'customer';
  if (actionId.includes('SYS-')) return 'system';
  return 'unknown';
}

function getDomain(actionId: string): string {
  const parts = actionId.split('-');
  if (parts.length >= 3) {
    return parts[2].toLowerCase();
  }
  return 'unknown';
}

function createJiraTask(actionStory: ActionStory): JiraTask {
  const domain = actionStory.id.split('-')[2];
  const initiator = getInitiator(actionStory.id);
  const domainLower = getDomain(actionStory.id);
  
  return {
    issueType: 'Task',
    summary: `${actionStory.id} ${actionStory.name}`,
    description: `Implements Action Story ${actionStory.id}.
Source: Figma → Action Story frame ${actionStory.id}.

Trigger:
${actionStory.trigger}

System Action:
${actionStory.systemAction}

Failure States:
${actionStory.failureStates}`,
    projectKey: 'TAVLO',
    epicLink: getEpicFromDomain(domain),
    labels: `action:${actionStory.id.toLowerCase()},${initiator},${domainLower}`,
    priority: getPriority(actionStory)
  };
}

function escapeCSVField(field: string): string {
  // If field contains comma, newline, or quotes, wrap in quotes and escape internal quotes
  if (field.includes(',') || field.includes('\n') || field.includes('"')) {
    return `"${field.replace(/"/g, '""')}"`;
  }
  return field;
}

function validateActionStories(actions: ActionStory[]): string[] {
  const errors: string[] = [];
  const seenIds = new Set<string>();
  
  actions.forEach((action, index) => {
    // Check valid Action ID format
    if (!action.id.match(/^TAV-[A-Z]{3}-[A-Z]{3}-\d{3}$/)) {
      errors.push(`Action ${index + 1}: Invalid ID format: ${action.id}`);
    }
    
    // Check for duplicates
    if (seenIds.has(action.id)) {
      errors.push(`Duplicate Action ID: ${action.id}`);
    }
    seenIds.add(action.id);
    
    // Check required fields
    if (!action.name || action.name.trim() === '') {
      errors.push(`Action ${action.id}: Empty name`);
    }
    
    if (!action.trigger || action.trigger.trim() === '') {
      errors.push(`Action ${action.id}: Empty trigger`);
    }
    
    if (!action.systemAction || action.systemAction.trim() === '') {
      errors.push(`Action ${action.id}: Empty system action`);
    }
  });
  
  return errors;
}

export function generateJiraCSV(): string {
  // Combine all action stories
  const allActions = [
    ...platformActions,
    ...customerActionsPart1,
    ...customerActionsPart2
  ];
  
  // Validate before generating
  const errors = validateActionStories(allActions);
  if (errors.length > 0) {
    return `VALIDATION ERRORS:\n${errors.join('\n')}`;
  }
  
  // Generate CSV header
  const header = 'Issue Type,Summary,Description,Project Key,Epic Link,Labels,Priority';
  
  // Generate CSV rows
  const rows = allActions.map(action => {
    const task = createJiraTask(action);
    return [
      task.issueType,
      escapeCSVField(task.summary),
      escapeCSVField(task.description),
      task.projectKey,
      escapeCSVField(task.epicLink),
      task.labels,
      task.priority
    ].join(',');
  });
  
  return [header, ...rows].join('\n');
}

// Summary statistics
export function getActionStorySummary() {
  return {
    platform: platformActions.length,
    customerPart1: customerActionsPart1.length,
    customerPart2: customerActionsPart2.length,
    total: platformActions.length + customerActionsPart1.length + customerActionsPart2.length
  };
}
