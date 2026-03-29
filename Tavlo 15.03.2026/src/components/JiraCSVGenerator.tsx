import React, { useState } from 'react';
import { Button } from './ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { generateJiraCSV, getActionStorySummary } from '../utils/generateJiraCSV';
import { Download, CheckCircle, FileText } from 'lucide-react';

/**
 * Jira CSV Generator Component
 * Generates and downloads Jira-compatible CSV from Action Story documentation
 */

export function JiraCSVGenerator() {
  const [csvGenerated, setCsvGenerated] = useState(false);
  const summary = getActionStorySummary();

  const handleGenerateAndDownload = () => {
    const csv = generateJiraCSV();
    
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
  };

  const handleCopyToClipboard = () => {
    const csv = generateJiraCSV();
    navigator.clipboard.writeText(csv);
    alert('CSV copied to clipboard!');
  };

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <FileText className="size-5" />
            Tavlo Action Stories - Jira CSV Generator
          </CardTitle>
          <CardDescription>
            Generate Jira-compatible CSV for bulk import of all Action Story tasks
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Summary */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-muted p-4 rounded-lg">
              <div className="text-2xl font-bold text-primary">{summary.platform}</div>
              <div className="text-sm text-muted-foreground">Platform Actions</div>
            </div>
            <div className="bg-muted p-4 rounded-lg">
              <div className="text-2xl font-bold text-primary">{summary.customerPart1}</div>
              <div className="text-sm text-muted-foreground">Customer Part 1</div>
            </div>
            <div className="bg-muted p-4 rounded-lg">
              <div className="text-2xl font-bold text-primary">{summary.customerPart2}</div>
              <div className="text-sm text-muted-foreground">Customer Part 2</div>
            </div>
            <div className="bg-muted p-4 rounded-lg">
              <div className="text-2xl font-bold text-green-600">{summary.total}</div>
              <div className="text-sm text-muted-foreground">Total Stories</div>
            </div>
          </div>

          {/* Action Breakdown */}
          <div className="border rounded-lg p-4 space-y-2">
            <h3 className="font-semibold text-sm mb-2">Action Story Breakdown</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Platform - Vendor Onboarding:</span>
                <span className="font-medium">5 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Platform - Legal & Compliance:</span>
                <span className="font-medium">3 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Platform - Billing & Payments:</span>
                <span className="font-medium">5 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Platform - Content Moderation:</span>
                <span className="font-medium">3 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Platform - Customer Support:</span>
                <span className="font-medium">2 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Platform - System Monitoring:</span>
                <span className="font-medium">4 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Platform - Analytics:</span>
                <span className="font-medium">3 actions</span>
              </div>
              <div className="flex justify-between border-t pt-2">
                <span className="text-muted-foreground font-semibold">Customer - QR Landing:</span>
                <span className="font-medium">7 actions</span>
              </div>
              <div className="flex justify-between border-t pt-2">
                <span className="text-muted-foreground">Customer - Authentication:</span>
                <span className="font-medium">6 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Customer - Menu Browsing:</span>
                <span className="font-medium">8 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Customer - Dish Customization:</span>
                <span className="font-medium">8 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Customer - Basket Management:</span>
                <span className="font-medium">6 actions</span>
              </div>
              <div className="flex justify-between border-t pt-2">
                <span className="text-muted-foreground font-semibold">Customer - Payment Flow:</span>
                <span className="font-medium">10 actions</span>
              </div>
              <div className="flex justify-between border-t pt-2">
                <span className="text-muted-foreground">Customer - Order Tracking:</span>
                <span className="font-medium">8 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Customer - Reviews:</span>
                <span className="font-medium">7 actions</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Customer - Account Management:</span>
                <span className="font-medium">5 actions</span>
              </div>
            </div>
          </div>

          {/* CSV Format Info */}
          <div className="border rounded-lg p-4 bg-muted/50">
            <h3 className="font-semibold text-sm mb-2">CSV Format</h3>
            <ul className="text-sm space-y-1 text-muted-foreground">
              <li>• <strong>Issue Type:</strong> Task</li>
              <li>• <strong>Summary:</strong> TAV-XXX-YYY-000 Action Name</li>
              <li>• <strong>Description:</strong> Trigger, System Action, Failure States</li>
              <li>• <strong>Project Key:</strong> TAVLO</li>
              <li>• <strong>Epic Link:</strong> Auto-mapped from domain (ACC→Vendor Onboarding, PAY→Customer Ordering, etc.)</li>
              <li>• <strong>Labels:</strong> action:tav-xxx-yyy-000, initiator, domain</li>
              <li>• <strong>Priority:</strong> High (payments/orders), Medium (setup), Low (reviews/content)</li>
            </ul>
          </div>

          {/* Actions */}
          <div className="flex gap-3">
            <Button 
              onClick={handleGenerateAndDownload}
              className="flex-1"
              size="lg"
            >
              <Download className="size-4 mr-2" />
              Download Jira CSV
            </Button>
            <Button 
              onClick={handleCopyToClipboard}
              variant="outline"
              size="lg"
            >
              Copy to Clipboard
            </Button>
          </div>

          {csvGenerated && (
            <div className="flex items-center gap-2 text-green-600 bg-green-50 p-3 rounded-lg">
              <CheckCircle className="size-5" />
              <span className="text-sm font-medium">
                CSV generated successfully! Ready for Jira bulk import.
              </span>
            </div>
          )}

          {/* Import Instructions */}
          <div className="border-t pt-4 text-sm text-muted-foreground space-y-2">
            <h4 className="font-semibold text-foreground">Import Instructions:</h4>
            <ol className="list-decimal list-inside space-y-1 ml-2">
              <li>Download the CSV file above</li>
              <li>In Jira, go to your TAVLO project</li>
              <li>Navigate to Issues → Import issues from CSV</li>
              <li>Upload the downloaded file</li>
              <li>Map columns (should auto-detect)</li>
              <li>Review and import all {summary.total} tasks</li>
            </ol>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
