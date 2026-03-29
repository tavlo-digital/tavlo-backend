import { useState, useRef, useEffect } from 'react';
import { api } from '@/lib/api';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
  QrCode, 
  Printer, 
  RefreshCw,
  Download,
  Grid3x3,
  Copy,
  Check,
  AlertTriangle,
  Info,
  ChevronDown,
  ChevronUp,
  X
} from 'lucide-react';
import { toast } from 'sonner';
import { QRCodeSVG } from 'qrcode.react';

interface Table {
  id: string;
  number: number;
  qrData: string;
  timestamp: number;
  lastScanned?: number; // Timestamp of last scan
  neverScanned?: boolean; // True if never scanned
  currentStatus?: 'idle' | 'active' | 'waiting-payment'; // Current table state
}

interface QRCodesManagementProps {
  vendorId: string;
}

export function QRCodesManagement({ vendorId }: QRCodesManagementProps) {
  const APP_BASE = process.env.NEXT_PUBLIC_APP_URL || 'https://app.tavlo.io';

  // Mock settings data (in production, this would come from Settings API/context)
  const qrSettings = {
    sharedBasket: true,
    reservationsEnabled: false,
    maxGuestsPerTable: 10
  };

  const [tables, setTables] = useState<Table[]>([]);

  // Load tables from API on mount
  useEffect(() => {
    if (!vendorId) return;
    api.getTables(vendorId as string)
      .then((data: any) => {
        const mapped: Table[] = (data as any[]).map((t: any) => ({
          id: t.id,
          number: t.number,
          qrData: JSON.stringify({ restaurantId: vendorId, tableId: t.id, token: t.qrToken, type: 'table' }),
          timestamp: t.qrCreatedAt ? new Date(t.qrCreatedAt).getTime() : Date.now(),
          lastScanned: t.lastScannedAt ? new Date(t.lastScannedAt).getTime() : undefined,
          neverScanned: !t.lastScannedAt,
          currentStatus: 'idle' as Table['currentStatus'],
        }));
        setTables(mapped);
      })
      .catch(() => {
        // If no tables yet, show empty state (user can seed or create tables in settings)
        setTables([]);
      });
  }, [vendorId]);

  const [copiedId, setCopiedId] = useState<string | null>(null);
  const [showAdvanced, setShowAdvanced] = useState<{ [key: string]: boolean }>({});
  const [showRegenerateModal, setShowRegenerateModal] = useState(false);
  const [regenerateConfirmed, setRegenerateConfirmed] = useState(false);
  const printRefs = useRef<{ [key: string]: HTMLDivElement | null }>({});

  // Get usage health status
  const getUsageHealth = (table: Table): {
    label: string;
    color: string;
    tooltip: string;
  } => {
    if (table.neverScanned || !table.lastScanned) {
      return {
        label: 'Never scanned',
        color: 'text-gray-500',
        tooltip: 'This QR code has never been used. Check if it\'s visible and accessible to customers.'
      };
    }

    const daysSinceScanned = Math.floor((Date.now() - table.lastScanned) / (1000 * 60 * 60 * 24));
    
    if (daysSinceScanned === 0) {
      return {
        label: 'Scanned today',
        color: 'text-emerald-600',
        tooltip: 'This QR code is actively being used by customers.'
      };
    }

    if (daysSinceScanned >= 7) {
      return {
        label: 'No scans in 7 days',
        color: 'text-amber-600',
        tooltip: 'This QR code hasn\'t been scanned recently. Check if it\'s damaged, missing, or obstructed.'
      };
    }

    return {
      label: `Last scan ${daysSinceScanned}d ago`,
      color: 'text-gray-600',
      tooltip: `Last scanned ${daysSinceScanned} day${daysSinceScanned > 1 ? 's' : ''} ago.`
    };
  };

  // Get table activity status
  const getTableStatus = (status?: Table['currentStatus']): {
    icon: string;
    color: string;
    tooltip: string;
  } => {
    switch (status) {
      case 'active':
        return {
          icon: '🟡',
          color: 'bg-amber-100 border-amber-300',
          tooltip: 'Order in progress'
        };
      case 'waiting-payment':
        return {
          icon: '🔴',
          color: 'bg-red-100 border-red-300',
          tooltip: 'Waiting for payment'
        };
      default:
        return {
          icon: '🟢',
          color: 'bg-emerald-100 border-emerald-300',
          tooltip: 'Table idle'
        };
    }
  };

  const refreshQRCode = (tableId: string) => {
    api.refreshTableQR(vendorId as string, tableId)
      .then((updated: any) => {
        setTables(tables.map(t =>
          t.id === tableId
            ? {
                ...t,
                qrData: JSON.stringify({ restaurantId: vendorId, tableId: updated.id, token: updated.qrToken, type: 'table' }),
                timestamp: updated.qrCreatedAt ? new Date(updated.qrCreatedAt).getTime() : Date.now(),
              }
            : t
        ));
        toast.success(`QR code rotated for Table ${tables.find(t => t.id === tableId)?.number}`);
      })
      .catch(() => toast.error('Failed to refresh QR code'));
  };

  const regenerateAllQRCodes = () => {
    if (!regenerateConfirmed) {
      toast.error('Please confirm you understand the impact');
      return;
    }

    api.regenerateAllQR(vendorId as string)
      .then((data: any) => {
        const mapped: Table[] = (data as any[]).map((t: any) => ({
          id: t.id,
          number: t.number,
          qrData: JSON.stringify({ restaurantId: vendorId, tableId: t.id, token: t.qrToken, type: 'table' }),
          timestamp: t.qrCreatedAt ? new Date(t.qrCreatedAt).getTime() : Date.now(),
          lastScanned: t.lastScannedAt ? new Date(t.lastScannedAt).getTime() : undefined,
          neverScanned: !t.lastScannedAt,
          currentStatus: 'idle' as Table['currentStatus'],
        }));
        setTables(mapped);
        setShowRegenerateModal(false);
        setRegenerateConfirmed(false);
        toast.success('All QR codes have been replaced. Please reprint them.');
      })
      .catch(() => toast.error('Failed to regenerate QR codes'));
  };

  const printQRCode = (tableId: string) => {
    const table = tables.find(t => t.id === tableId);
    if (!table) return;

    const printWindow = window.open('', '', 'width=800,height=600');
    if (!printWindow) {
      toast.error('Please allow pop-ups to print QR codes');
      return;
    }

    const qrElement = printRefs.current[tableId];
    if (!qrElement) return;

    const svg = qrElement.querySelector('svg');
    if (!svg) return;

    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>Table ${table.number} - QR Code</title>
          <style>
            body {
              margin: 0;
              padding: 40px;
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
              display: flex;
              flex-direction: column;
              align-items: center;
              justify-content: center;
              min-height: 100vh;
            }
            .container {
              text-align: center;
              border: 2px solid #e5e7eb;
              border-radius: 16px;
              padding: 40px;
              background: white;
            }
            h1 {
              margin: 0 0 8px 0;
              font-size: 64px;
              font-weight: bold;
              color: #111827;
              letter-spacing: -1px;
            }
            h2 {
              margin: 0 0 32px 0;
              font-size: 32px;
              font-weight: 600;
              color: #6b7280;
            }
            .qr-code {
              margin: 0 auto 32px auto;
              padding: 20px;
              background: white;
              border-radius: 12px;
            }
            .instructions {
              font-size: 20px;
              color: #374151;
              margin-bottom: 16px;
              font-weight: 500;
            }
            .restaurant-name {
              font-size: 24px;
              font-weight: 600;
              color: #111827;
              margin-bottom: 8px;
            }
            .footer {
              margin-top: 32px;
              padding-top: 24px;
              border-top: 2px solid #e5e7eb;
              font-size: 14px;
              color: #9ca3af;
            }
            @media print {
              body {
                padding: 20px;
              }
              .container {
                border: 2px solid #000;
                box-shadow: none;
              }
              h1 {
                font-size: 72px;
              }
            }
          </style>
        </head>
        <body>
          <div class="container">
            <div class="restaurant-name">Bella Cucina</div>
            <h1>TABLE ${table.number}</h1>
            <h2>Scan to Order</h2>
            <div class="qr-code">
              ${svg.outerHTML}
            </div>
            <div class="instructions">
              Scan this QR code with your phone camera<br/>
              to view our menu and place your order
            </div>
            <div class="footer">
              Generated on ${new Date(table.timestamp).toLocaleDateString()}
            </div>
          </div>
        </body>
      </html>
    `);

    printWindow.document.close();
    
    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 250);

    toast.success(`Printing QR code for Table ${table.number}`);
  };

  const downloadQRCode = (tableId: string) => {
    const table = tables.find(t => t.id === tableId);
    if (!table) return;

    const qrElement = printRefs.current[tableId];
    if (!qrElement) return;

    const svg = qrElement.querySelector('svg');
    if (!svg) return;

    // Convert SVG to canvas for download
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const svgData = new XMLSerializer().serializeToString(svg);
    const img = new Image();
    
    img.onload = () => {
      canvas.width = img.width;
      canvas.height = img.height;
      ctx.drawImage(img, 0, 0);
      
      canvas.toBlob((blob) => {
        if (!blob) return;
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.download = `table-${table.number}-qr-code.png`;
        link.href = url;
        link.click();
        URL.revokeObjectURL(url);
        toast.success(`QR code downloaded for Table ${table.number}`);
      });
    };
    
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
  };

  const copyQRLink = (tableId: string) => {
    const table = tables.find(t => t.id === tableId);
    if (!table) return;

    // Generate the customer-facing URL (not raw JSON)
    const qrUrl = `${APP_BASE}/order?restaurant=${vendorId}&table=${table.number}`;

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(qrUrl)
        .then(() => {
          setCopiedId(tableId);
          setTimeout(() => setCopiedId(null), 2000);
          toast.success('QR link copied to clipboard');
        })
        .catch(() => {
          fallbackCopyTextToClipboard(qrUrl, tableId);
        });
    } else {
      fallbackCopyTextToClipboard(qrUrl, tableId);
    }
  };

  const fallbackCopyTextToClipboard = (text: string, tableId: string) => {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.width = '2em';
    textArea.style.height = '2em';
    textArea.style.padding = '0';
    textArea.style.border = 'none';
    textArea.style.outline = 'none';
    textArea.style.boxShadow = 'none';
    textArea.style.background = 'transparent';
    textArea.style.opacity = '0';
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
      const successful = document.execCommand('copy');
      if (successful) {
        setCopiedId(tableId);
        setTimeout(() => setCopiedId(null), 2000);
        toast.success('QR link copied to clipboard');
      } else {
        toast.error('Failed to copy QR link');
      }
    } catch (err) {
      toast.error('Failed to copy QR link');
    }
    
    document.body.removeChild(textArea);
  };

  const printAllQRCodes = () => {
    const printWindow = window.open('', '', 'width=1200,height=800');
    if (!printWindow) {
      toast.error('Please allow pop-ups to print QR codes');
      return;
    }

    let allQRs = '';
    tables.forEach(table => {
      const qrElement = printRefs.current[table.id];
      if (qrElement) {
        const svg = qrElement.querySelector('svg');
        if (svg) {
          allQRs += `
            <div class="qr-card">
              <div class="restaurant-name">Bella Cucina</div>
              <h1>TABLE ${table.number}</h1>
              <div class="qr-code">${svg.outerHTML}</div>
              <div class="instructions">Scan to Order</div>
              <div class="footer">Generated ${new Date(table.timestamp).toLocaleDateString()}</div>
            </div>
          `;
        }
      }
    });

    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>All QR Codes - Bella Cucina</title>
          <style>
            body {
              margin: 0;
              padding: 20px;
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            }
            .qr-grid {
              display: grid;
              grid-template-columns: repeat(4, 1fr);
              gap: 20px;
            }
            .qr-card {
              text-align: center;
              border: 2px solid #000;
              border-radius: 12px;
              padding: 20px;
              background: white;
              page-break-inside: avoid;
            }
            .restaurant-name {
              font-size: 14px;
              font-weight: 600;
              color: #6b7280;
              margin-bottom: 8px;
            }
            h1 {
              margin: 0 0 16px 0;
              font-size: 28px;
              font-weight: bold;
              color: #111827;
              letter-spacing: -0.5px;
            }
            .qr-code {
              margin: 0 auto 16px auto;
            }
            .qr-code svg {
              max-width: 100%;
              height: auto !important;
            }
            .instructions {
              font-size: 14px;
              font-weight: 600;
              color: #374151;
              margin-bottom: 8px;
            }
            .footer {
              font-size: 10px;
              color: #9ca3af;
            }
            @media print {
              body {
                padding: 10px;
              }
              .qr-grid {
                gap: 15px;
              }
              .qr-card {
                padding: 15px;
                border: 2px solid #000;
              }
              h1 {
                font-size: 32px;
              }
            }
          </style>
        </head>
        <body>
          <div class="qr-grid">
            ${allQRs}
          </div>
        </body>
      </html>
    `);

    printWindow.document.close();
    
    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 500);

    toast.success('Printing all QR codes');
  };

  const toggleAdvanced = (tableId: string) => {
    setShowAdvanced(prev => ({
      ...prev,
      [tableId]: !prev[tableId]
    }));
  };

  return (
    <div className="p-6 space-y-6 max-w-[1400px] mx-auto">
      {/* Top Context Banner (NEW) */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start gap-3">
          <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
          <div className="flex-1">
            <p className="text-sm font-medium text-gray-900 mb-2">
              QR Behavior is defined in Settings → Tables & QR
            </p>
            <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-700">
              <div className="flex items-center gap-2">
                <span className="font-medium">Shared basket:</span>
                <Badge variant={qrSettings.sharedBasket ? "default" : "secondary"} className="text-xs">
                  {qrSettings.sharedBasket ? 'Enabled' : 'Disabled'}
                </Badge>
              </div>
              <div className="flex items-center gap-2">
                <span className="font-medium">Reservations:</span>
                <Badge variant={qrSettings.reservationsEnabled ? "default" : "secondary"} className="text-xs">
                  {qrSettings.reservationsEnabled ? 'Enabled' : 'Disabled'}
                </Badge>
              </div>
              <div className="flex items-center gap-2">
                <span className="font-medium">Max guests per table:</span>
                <Badge variant="secondary" className="text-xs">
                  {qrSettings.maxGuestsPerTable}
                </Badge>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-neutral-900">QR Code Management</h2>
          <p className="text-neutral-600 mt-1">
            Manage QR codes for all your restaurant tables
          </p>
        </div>
        <div className="flex gap-2">
          <Button 
            variant="outline"
            className="border-red-300 text-red-700 hover:bg-red-50 hover:text-red-800 hover:border-red-400 group relative"
            onClick={() => setShowRegenerateModal(true)}
          >
            <RefreshCw className="w-4 h-4 mr-2" />
            Regenerate All QR Codes
            <div className="absolute bottom-full mb-2 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-nowrap z-10">
              Replaces all QR codes for all tables
            </div>
          </Button>
          <Button 
            className="bg-blue-600 hover:bg-blue-700"
            onClick={printAllQRCodes}
          >
            <Printer className="w-4 h-4 mr-2" />
            Print All QR Codes
          </Button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Total Tables</p>
                <p className="text-2xl font-semibold mt-1">{tables.length}</p>
              </div>
              <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <Grid3x3 className="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Active QR Codes</p>
                <p className="text-2xl font-semibold mt-1">{tables.length}</p>
              </div>
              <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <QrCode className="w-6 h-6 text-green-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Restaurant</p>
                <p className="text-lg font-semibold mt-1">Bella Cucina</p>
                <p className="text-xs text-neutral-500">ID: {vendorId}</p>
              </div>
              <div className="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <QrCode className="w-6 h-6 text-orange-600" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Info Card */}
      <Card className="bg-blue-50 border-blue-200">
        <CardContent className="p-4">
          <div className="flex gap-3">
            <QrCode className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div className="text-sm text-blue-900">
              <p className="font-semibold mb-1">How QR Codes Work</p>
              <p className="text-blue-800">
                Each table has a unique QR code that customers can scan to access your menu and place orders. 
                You can refresh individual QR codes for security, print them for display, or download them for custom printing.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* QR Codes Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {tables.map((table) => {
          const usageHealth = getUsageHealth(table);
          const tableStatus = getTableStatus(table.currentStatus);
          
          return (
            <Card key={table.id} className="overflow-hidden hover:shadow-lg transition-shadow">
              <CardHeader className="pb-4 bg-gradient-to-br from-neutral-50 to-white">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <CardTitle className="text-lg mb-1">Table {table.number}</CardTitle>
                    
                    {/* QR Usage Health Indicator */}
                    <div className="flex items-center gap-1.5 mb-1 group relative">
                      <div className={`w-1.5 h-1.5 rounded-full ${
                        usageHealth.color === 'text-emerald-600' ? 'bg-emerald-500' :
                        usageHealth.color === 'text-amber-600' ? 'bg-amber-500' :
                        'bg-gray-400'
                      }`}></div>
                      <span className={`text-xs ${usageHealth.color}`}>
                        {usageHealth.label}
                      </span>
                      <Info className="w-3 h-3 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                      <div className="absolute top-full left-0 mt-1 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-3 py-2 w-48 z-10">
                        {usageHealth.tooltip}
                      </div>
                    </div>

                    {/* Lightweight QR History */}
                    <CardDescription className="text-xs">
                      Generated on: {new Date(table.timestamp).toLocaleDateString()}
                    </CardDescription>
                  </div>
                  
                  {/* Table Activity Status */}
                  <div className="group relative">
                    <div className={`w-8 h-8 rounded-full border-2 flex items-center justify-center text-sm ${tableStatus.color}`}>
                      {tableStatus.icon}
                    </div>
                    <div className="absolute top-full right-0 mt-1 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-nowrap z-10">
                      {tableStatus.tooltip}
                    </div>
                  </div>
                </div>
              </CardHeader>
              
              <CardContent className="p-6">
                {/* QR Code Display */}
                <div 
                  ref={el => { printRefs.current[table.id] = el; }}
                  className="bg-white p-4 rounded-lg mb-4 flex items-center justify-center border-2 border-neutral-100"
                >
                  <QRCodeSVG
                    value={table.qrData}
                    size={200}
                    level="H"
                    includeMargin={true}
                  />
                </div>

                {/* Advanced Toggle (Hide Raw QR Data by Default) */}
                <button
                  onClick={() => toggleAdvanced(table.id)}
                  className="w-full mb-4 p-2 text-xs text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg flex items-center justify-between transition-colors"
                >
                  <span>Advanced</span>
                  {showAdvanced[table.id] ? (
                    <ChevronUp className="w-3 h-3" />
                  ) : (
                    <ChevronDown className="w-3 h-3" />
                  )}
                </button>

                {/* QR Data Info - Hidden by Default */}
                {showAdvanced[table.id] && (
                  <div className="mb-4 p-3 bg-neutral-50 rounded-lg border border-neutral-200">
                    <p className="text-xs font-medium text-neutral-600 mb-1">QR Data (JSON):</p>
                    <p className="text-xs text-neutral-500 font-mono break-all">
                      {table.qrData}
                    </p>
                  </div>
                )}

                {/* Action Buttons */}
                <div className="space-y-2">
                  <div className="grid grid-cols-2 gap-2">
                    <div className="group relative">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => refreshQRCode(table.id)}
                        className="w-full"
                      >
                        <RefreshCw className="w-3 h-3 mr-1" />
                        Refresh
                      </Button>
                      <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-nowrap z-10">
                        Rotates this table's QR code for security
                      </div>
                    </div>
                    
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => printQRCode(table.id)}
                      className="w-full"
                    >
                      <Printer className="w-3 h-3 mr-1" />
                      Print
                    </Button>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => downloadQRCode(table.id)}
                      className="w-full"
                    >
                      <Download className="w-3 h-3 mr-1" />
                      Download
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => copyQRLink(table.id)}
                      className="w-full"
                    >
                      {copiedId === table.id ? (
                        <>
                          <Check className="w-3 h-3 mr-1" />
                          Copied!
                        </>
                      ) : (
                        <>
                          <Copy className="w-3 h-3 mr-1" />
                          Copy Link
                        </>
                      )}
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Regenerate All QR Codes - Confirmation Modal */}
      {showRegenerateModal && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg max-w-md w-full shadow-2xl">
            <div className="p-6 border-b border-gray-200">
              <div className="flex items-start gap-3">
                <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                  <AlertTriangle className="w-5 h-5 text-red-600" />
                </div>
                <div className="flex-1">
                  <h3 className="text-lg font-semibold text-gray-900">
                    Replace all QR codes?
                  </h3>
                </div>
                <button
                  onClick={() => {
                    setShowRegenerateModal(false);
                    setRegenerateConfirmed(false);
                  }}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            <div className="p-6 space-y-4">
              <p className="text-sm text-gray-700 leading-relaxed">
                This will <strong>invalidate all existing QR codes</strong>.
              </p>
              <p className="text-sm text-gray-700 leading-relaxed">
                You must <strong>reprint and replace them on every table</strong>.
              </p>

              <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <div className="flex items-start gap-2">
                  <AlertTriangle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                  <p className="text-xs text-amber-800">
                    Current QR codes will stop working immediately. Only proceed if you're ready to replace all physical codes.
                  </p>
                </div>
              </div>

              <label className="flex items-start gap-3 cursor-pointer p-3 rounded-lg hover:bg-gray-50 transition-colors">
                <input
                  type="checkbox"
                  checked={regenerateConfirmed}
                  onChange={(e) => setRegenerateConfirmed(e.target.checked)}
                  className="mt-0.5 w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500"
                />
                <span className="text-sm text-gray-700">
                  I understand all current QR codes will stop working
                </span>
              </label>
            </div>

            <div className="p-6 bg-gray-50 rounded-b-lg flex gap-3">
              <Button
                variant="outline"
                onClick={() => {
                  setShowRegenerateModal(false);
                  setRegenerateConfirmed(false);
                }}
                className="flex-1"
              >
                Cancel
              </Button>
              <Button
                onClick={regenerateAllQRCodes}
                disabled={!regenerateConfirmed}
                className={`flex-1 ${
                  regenerateConfirmed
                    ? 'bg-red-600 hover:bg-red-700 text-white'
                    : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                }`}
              >
                Replace All QR Codes
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
