import React, { useState } from 'react';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { generateJiraCSV, getActionStorySummary } from '../utils/generateJiraCSV';
import { Download, CheckCircle, FileText, ArrowLeft } from 'lucide-react';

/**
 * Jira CSV Generator Page
 * Generates and downloads Jira-compatible CSV from Action Story documentation
 */

export function JiraCSVGenerator() {
  const [csvGenerated, setCsvGenerated] = useState(false);
  const summary = getActionStorySummary();

  const handleGenerateAndDownload = () => {
    const csv = generateJiraCSV();
    
    // Check for validation errors
    if (csv.startsWith('VALIDATION ERRORS:')) {
      alert(csv);
      return;
    }
    
    // Create blob and download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'tavlo-action-stories-jira-import.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setCsvGenerated(true);
    setTimeout(() => setCsvGenerated(false), 5000); // Reset after 5 seconds
  };

  const handleCopyToClipboard = () => {
    const csv = generateJiraCSV();
    
    // Check for validation errors
    if (csv.startsWith('VALIDATION ERRORS:')) {
      alert(csv);
      return;
    }
    
    navigator.clipboard.writeText(csv);
    alert('CSV copied to clipboard!');
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-orange-50 to-white">
      <div className="max-w-4xl mx-auto p-6 space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4 mb-6">
          <Button
            variant="outline"
            size="sm"
            onClick={() => window.history.back()}
            className="gap-2"
          >
            <ArrowLeft className="size-4" />
            Back
          </Button>
          <div>
            <h1 className="text-3xl font-bold text-gray-900">Jira CSV Generator</h1>
            <p className="text-gray-600">Export all Tavlo Action Stories for Jira import</p>
          </div>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileText className="size-5" />
              Action Story Documentation
            </CardTitle>
            <CardDescription>
              {summary.total} action stories ready for bulk import into Jira
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Summary Stats */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                <div className="text-3xl font-bold text-blue-700">{summary.platform}</div>
                <div className="text-sm text-blue-600 font-medium">Platform Actions</div>
                <div className="text-xs text-blue-500 mt-1">Admin & System</div>
              </div>
              <div className="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                <div className="text-3xl font-bold text-green-700">{summary.customerPart1}</div>
                <div className="text-sm text-green-600 font-medium">Customer Part 1</div>
                <div className="text-xs text-green-500 mt-1">QR → Basket</div>
              </div>
              <div className="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                <div className="text-3xl font-bold text-purple-700">{summary.customerPart2}</div>
                <div className="text-sm text-purple-600 font-medium">Customer Part 2</div>
                <div className="text-xs text-purple-500 mt-1">Payment → Reviews</div>
              </div>
              <div className="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg border border-orange-200">
                <div className="text-3xl font-bold text-orange-700">{summary.total}</div>
                <div className="text-sm text-orange-600 font-medium">Total Stories</div>
                <div className="text-xs text-orange-500 mt-1">All Actions</div>
              </div>
            </div>

            {/* Detailed Breakdown */}
            <div className="border rounded-lg p-5 bg-gray-50">
              <h3 className="font-semibold text-sm mb-3 text-gray-700">Action Story Breakdown by Domain</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                {/* Platform Actions */}
                <div className="space-y-2 bg-white p-3 rounded border">
                  <div className="font-semibold text-blue-700 text-xs uppercase tracking-wide mb-2">Platform (27 actions)</div>
                  <div className="flex justify-between text-gray-600">
                    <span>Vendor Onboarding</span>
                    <span className="font-medium">5</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Legal & Compliance</span>
                    <span className="font-medium">3</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Billing & Payments</span>
                    <span className="font-medium">5</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Content Moderation</span>
                    <span className="font-medium">3</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Customer Support</span>
                    <span className="font-medium">2</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>System Monitoring</span>
                    <span className="font-medium">4</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Analytics & Reporting</span>
                    <span className="font-medium">3</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Admin Control</span>
                    <span className="font-medium">2</span>
                  </div>
                </div>

                {/* Customer Actions */}
                <div className="space-y-2 bg-white p-3 rounded border">
                  <div className="font-semibold text-green-700 text-xs uppercase tracking-wide mb-2">Customer (65 actions)</div>
                  <div className="flex justify-between text-gray-600">
                    <span>QR Landing & Setup</span>
                    <span className="font-medium">7</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Authentication</span>
                    <span className="font-medium">6</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Menu Browsing</span>
                    <span className="font-medium">8</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Dish Customization</span>
                    <span className="font-medium">8</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Basket Management</span>
                    <span className="font-medium">6</span>
                  </div>
                  <div className="flex justify-between text-gray-600 border-t pt-2 mt-2">
                    <span>Payment Flow</span>
                    <span className="font-medium">10</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Order Tracking</span>
                    <span className="font-medium">8</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Reviews</span>
                    <span className="font-medium">7</span>
                  </div>
                  <div className="flex justify-between text-gray-600">
                    <span>Account Management</span>
                    <span className="font-medium">5</span>
                  </div>
                </div>
              </div>
            </div>

            {/* CSV Format Info */}
            <div className="border rounded-lg p-4 bg-blue-50 border-blue-200">
              <h3 className="font-semibold text-sm mb-3 text-blue-900">CSV Output Format</h3>
              <div className="space-y-2 text-sm text-blue-800">
                <div className="flex gap-2">
                  <span className="font-medium min-w-32">Issue Type:</span>
                  <span>Task (all stories)</span>
                </div>
                <div className="flex gap-2">
                  <span className="font-medium min-w-32">Summary:</span>
                  <span>TAV-XXX-YYY-000 Action Name</span>
                </div>
                <div className="flex gap-2">
                  <span className="font-medium min-w-32">Description:</span>
                  <span>Trigger + System Action + Failure States</span>
                </div>
                <div className="flex gap-2">
                  <span className="font-medium min-w-32">Project Key:</span>
                  <span>TAVLO</span>
                </div>
                <div className="flex gap-2">
                  <span className="font-medium min-w-32">Epic Link:</span>
                  <span>Auto-mapped from domain code</span>
                </div>
                <div className="flex gap-2">
                  <span className="font-medium min-w-32">Labels:</span>
                  <span>action:id, initiator, domain</span>
                </div>
                <div className="flex gap-2">
                  <span className="font-medium min-w-32">Priority:</span>
                  <span>High (payments), Medium (setup), Low (reviews)</span>
                </div>
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex gap-3">
              <Button 
                onClick={handleGenerateAndDownload}
                className="flex-1 bg-orange-600 hover:bg-orange-700"
                size="lg"
              >
                <Download className="size-4 mr-2" />
                Download CSV File
              </Button>
              <Button 
                onClick={handleCopyToClipboard}
                variant="outline"
                size="lg"
                className="border-orange-200 hover:bg-orange-50"
              >
                Copy to Clipboard
              </Button>
            </div>

            {/* Success Message */}
            {csvGenerated && (
              <div className="flex items-center gap-2 text-green-700 bg-green-50 p-4 rounded-lg border border-green-200 animate-in fade-in slide-in-from-bottom-2">
                <CheckCircle className="size-5 flex-shrink-0" />
                <div>
                  <div className="font-semibold">CSV Generated Successfully!</div>
                  <div className="text-sm text-green-600">
                    {summary.total} action stories ready for Jira bulk import
                  </div>
                </div>
              </div>
            )}

            {/* Import Instructions */}
            <div className="border-t pt-5 space-y-3">
              <h4 className="font-semibold text-gray-900">How to Import into Jira</h4>
              <ol className="list-decimal list-inside space-y-2 text-sm text-gray-700 ml-1">
                <li className="pl-2">Download the CSV file using the button above</li>
                <li className="pl-2">Open your Jira project (<strong>TAVLO</strong>)</li>
                <li className="pl-2">Navigate to <strong>Issues → Import issues from CSV</strong></li>
                <li className="pl-2">Upload the downloaded file</li>
                <li className="pl-2">Jira will auto-detect column mappings (verify they match)</li>
                <li className="pl-2">Review the preview and click <strong>Import</strong></li>
                <li className="pl-2">All {summary.total} tasks will be created in your backlog</li>
              </ol>
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mt-3">
                <div className="text-sm text-yellow-800">
                  <strong>Note:</strong> Make sure the TAVLO project exists in Jira before importing. Epic names should match the domain mappings shown above.
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export default JiraCSVGenerator;
