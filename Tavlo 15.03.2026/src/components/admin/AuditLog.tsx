import { useState } from 'react';
import { Clock, User, FileText, Filter, Download, Search, Eye, Shield, AlertCircle } from 'lucide-react';
import { Card } from '../ui/card';
import { Input } from '../ui/input';
import { Button } from '../ui/button';

interface AuditEntry {
  id: string;
  timestamp: string;
  adminUser: string;
  adminRole: string;
  action: string;
  entityType: string;
  entityId: string;
  entityName: string;
  reason: string;
  ipAddress: string;
  details?: string;
  severity: 'info' | 'warning' | 'critical';
}

export function AuditLog() {
  const [selectedSeverity, setSelectedSeverity] = useState<string>('all');
  const [selectedAction, setSelectedAction] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Mock audit data
  const auditEntries: AuditEntry[] = [
    {
      id: '1',
      timestamp: '2024-12-25 14:32:15',
      adminUser: 'admin@tavlo.app',
      adminRole: 'Super Admin',
      action: 'VENDOR_SUSPENDED',
      entityType: 'vendor',
      entityId: 'V-8472',
      entityName: 'La Bella Vista',
      reason: 'Multiple unresolved customer complaints (8 open cases), declining rating trend (4.8→3.2 in 14 days)',
      ipAddress: '192.168.1.105',
      details: 'Status changed from active to suspended. Vendor notified via email.',
      severity: 'critical'
    },
    {
      id: '2',
      timestamp: '2024-12-25 13:18:42',
      adminUser: 'support@tavlo.app',
      adminRole: 'Support Admin',
      action: 'CUSTOMER_DATA_ACCESSED',
      entityType: 'customer',
      entityId: 'C-19283',
      entityName: 'Sarah Martinez',
      reason: 'Support ticket #4829: Payment dispute resolution',
      ipAddress: '192.168.1.107',
      details: 'Viewed email, phone, order history for support case resolution',
      severity: 'warning'
    },
    {
      id: '3',
      timestamp: '2024-12-25 12:45:29',
      adminUser: 'admin@tavlo.app',
      adminRole: 'Super Admin',
      action: 'VENDOR_APPROVED',
      entityType: 'vendor',
      entityId: 'V-9201',
      entityName: 'Sakura Sushi',
      reason: 'Documentation verified, VAT number validated, business checks passed',
      ipAddress: '192.168.1.105',
      details: 'Vendor account activated. Welcome email sent.',
      severity: 'info'
    },
    {
      id: '4',
      timestamp: '2024-12-25 11:22:08',
      adminUser: 'content@tavlo.app',
      adminRole: 'Content Admin',
      action: 'REVIEW_HIDDEN',
      entityType: 'review',
      entityId: 'R-7743',
      entityName: 'Review by John D.',
      reason: 'Contains profanity and abusive language. Violates community guidelines section 3.2',
      ipAddress: '192.168.1.108',
      details: 'Review visibility changed to hidden. Original content preserved in audit trail.',
      severity: 'warning'
    },
    {
      id: '5',
      timestamp: '2024-12-25 10:05:17',
      adminUser: 'finance@tavlo.app',
      adminRole: 'Finance Admin',
      action: 'INVOICE_GENERATED',
      entityType: 'invoice',
      entityId: 'INV-2024-1245',
      entityName: 'Monthly Platform Invoice',
      reason: 'Automated monthly billing cycle for December 2024',
      ipAddress: '192.168.1.106',
      details: 'Generated for vendor V-4782 (Bella Italia). Amount: €49.00. Status: Sent.',
      severity: 'info'
    },
    {
      id: '6',
      timestamp: '2024-12-25 09:43:51',
      adminUser: 'admin@tavlo.app',
      adminRole: 'Super Admin',
      action: 'SUBSCRIPTION_MANUALLY_EXTENDED',
      entityType: 'subscription',
      entityId: 'SUB-4901',
      entityName: 'Premium Plan - Cafe Noir',
      reason: 'Payment processor outage. Extension granted as goodwill gesture during incident.',
      ipAddress: '192.168.1.105',
      details: 'Extended by 7 days. Vendor notified. Will be reviewed when payment processor restored.',
      severity: 'critical'
    },
    {
      id: '7',
      timestamp: '2024-12-24 18:12:33',
      adminUser: 'support@tavlo.app',
      adminRole: 'Support Admin',
      action: 'CUSTOMER_ACCOUNT_ANONYMIZED',
      entityType: 'customer',
      entityId: 'C-15672',
      entityName: 'Anonymous User (formerly Mark T.)',
      reason: 'GDPR right to erasure request (#GDPR-2024-0874)',
      ipAddress: '192.168.1.107',
      details: 'Personal data anonymized: email, phone, name replaced with pseudonyms. Order history retained.',
      severity: 'critical'
    },
    {
      id: '8',
      timestamp: '2024-12-24 16:54:19',
      adminUser: 'admin@tavlo.app',
      adminRole: 'Super Admin',
      action: 'VENDOR_REACTIVATED',
      entityType: 'vendor',
      entityId: 'V-6438',
      entityName: 'Pizza Express',
      reason: 'Payment received, subscription renewed after 3-day grace period',
      ipAddress: '192.168.1.105',
      details: 'Status changed from suspended to active. QR codes re-enabled.',
      severity: 'info'
    },
  ];

  const actionTypes = [
    { value: 'all', label: 'All Actions' },
    { value: 'vendor', label: 'Vendor Actions' },
    { value: 'customer', label: 'Customer Actions' },
    { value: 'subscription', label: 'Subscription Actions' },
    { value: 'content', label: 'Content Moderation' },
    { value: 'invoice', label: 'Invoice Actions' },
  ];

  const severityOptions = [
    { value: 'all', label: 'All Severities' },
    { value: 'info', label: 'Info', color: 'blue' },
    { value: 'warning', label: 'Warning', color: 'orange' },
    { value: 'critical', label: 'Critical', color: 'red' },
  ];

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'critical':
        return 'bg-red-100 text-red-700 border-red-200';
      case 'warning':
        return 'bg-orange-100 text-orange-700 border-orange-200';
      case 'info':
        return 'bg-blue-100 text-blue-700 border-blue-200';
      default:
        return 'bg-gray-100 text-gray-700 border-gray-200';
    }
  };

  const getActionIcon = (action: string) => {
    if (action.includes('VENDOR')) return <Shield className="w-4 h-4" />;
    if (action.includes('CUSTOMER')) return <User className="w-4 h-4" />;
    if (action.includes('REVIEW') || action.includes('CONTENT')) return <Eye className="w-4 h-4" />;
    if (action.includes('INVOICE')) return <FileText className="w-4 h-4" />;
    return <AlertCircle className="w-4 h-4" />;
  };

  return (
    <div className="p-6 max-w-[1800px] mx-auto">
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-2">
          <div>
            <h1 className="text-2xl mb-1">Audit Log</h1>
            <p className="text-sm text-gray-500">
              Complete record of all administrative actions • GDPR & legal compliance
            </p>
          </div>
          <Button variant="outline" className="flex items-center gap-2">
            <Download className="w-4 h-4" />
            Export Audit Report
          </Button>
        </div>
      </div>

      {/* Compliance Notice */}
      <Card className="mb-6 p-4 bg-blue-50 border-blue-200">
        <div className="flex items-start gap-3">
          <Shield className="w-5 h-5 text-blue-600 mt-0.5" />
          <div className="flex-1">
            <h3 className="font-medium text-blue-900 mb-1">Legal & Compliance Notice</h3>
            <p className="text-sm text-blue-700">
              This audit log is maintained for GDPR compliance, internal accountability, and legal defense. 
              All administrative actions are permanently recorded and cannot be deleted. Retention period: 7 years.
            </p>
          </div>
        </div>
      </Card>

      {/* Filters */}
      <Card className="mb-6 p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {/* Search */}
          <div className="md:col-span-2">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <Input
                type="text"
                placeholder="Search by admin, vendor, customer, or action..."
                className="pl-10"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>
          </div>

          {/* Action Type Filter */}
          <div>
            <select
              className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
              value={selectedAction}
              onChange={(e) => setSelectedAction(e.target.value)}
            >
              {actionTypes.map((type) => (
                <option key={type.value} value={type.value}>
                  {type.label}
                </option>
              ))}
            </select>
          </div>

          {/* Severity Filter */}
          <div>
            <select
              className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
              value={selectedSeverity}
              onChange={(e) => setSelectedSeverity(e.target.value)}
            >
              {severityOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        </div>
      </Card>

      {/* Audit Entries */}
      <Card>
        <div className="divide-y divide-gray-100">
          {auditEntries.map((entry) => (
            <div key={entry.id} className="p-5 hover:bg-gray-50 transition-colors">
              <div className="flex items-start gap-4">
                {/* Severity Indicator */}
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${getSeverityColor(entry.severity)} border`}>
                  {getActionIcon(entry.action)}
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                  {/* Header Row */}
                  <div className="flex items-start justify-between gap-4 mb-2">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="font-medium text-gray-900">{entry.action.replace(/_/g, ' ')}</span>
                        <span className={`px-2 py-0.5 text-xs rounded-full border ${getSeverityColor(entry.severity)}`}>
                          {entry.severity.toUpperCase()}
                        </span>
                      </div>
                      <div className="text-sm text-gray-600">
                        {entry.entityType.charAt(0).toUpperCase() + entry.entityType.slice(1)}: <span className="font-medium">{entry.entityName}</span>
                        <span className="text-gray-400"> • ID: {entry.entityId}</span>
                      </div>
                    </div>
                    <div className="text-sm text-gray-500 whitespace-nowrap flex items-center gap-2">
                      <Clock className="w-4 h-4" />
                      {entry.timestamp}
                    </div>
                  </div>

                  {/* Admin Info */}
                  <div className="flex items-center gap-4 mb-3 text-sm">
                    <div className="flex items-center gap-2 text-gray-600">
                      <User className="w-4 h-4" />
                      <span>{entry.adminUser}</span>
                      <span className="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs">
                        {entry.adminRole}
                      </span>
                    </div>
                    <div className="text-gray-400">
                      IP: {entry.ipAddress}
                    </div>
                  </div>

                  {/* Reason */}
                  <div className="mb-2">
                    <div className="text-xs text-gray-500 mb-1">Reason:</div>
                    <div className="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-200">
                      {entry.reason}
                    </div>
                  </div>

                  {/* Details */}
                  {entry.details && (
                    <div>
                      <div className="text-xs text-gray-500 mb-1">Details:</div>
                      <div className="text-sm text-gray-600">
                        {entry.details}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      </Card>

      {/* Pagination */}
      <div className="mt-6 flex items-center justify-between">
        <div className="text-sm text-gray-600">
          Showing 1-8 of 1,247 audit entries
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm">
            Previous
          </Button>
          <Button variant="outline" size="sm" className="bg-purple-600 text-white hover:bg-purple-700">
            1
          </Button>
          <Button variant="outline" size="sm">
            2
          </Button>
          <Button variant="outline" size="sm">
            3
          </Button>
          <Button variant="outline" size="sm">
            Next
          </Button>
        </div>
      </div>
    </div>
  );
}
