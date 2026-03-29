import { useState } from 'react';
import { Download, FileSpreadsheet, CheckSquare, Square } from 'lucide-react';
import { toast } from 'sonner@2.0.3';

interface Vendor {
  id: string;
  name: string;
  category?: string;
  country?: string;
  city?: string;
  address?: string;
  status: string;
  liveStatus?: string;
  subscription: string;
  subscriptionStatus: string;
  payment: string;
  revenue: string;
  rating?: number;
  lastActive?: string;
  email?: string;
  phone?: string;
  website?: string;
  vat?: string;
  createdDate?: string;
}

interface VendorExportButtonProps {
  vendors: Vendor[];
  selectedVendors?: string[];
  isFiltered?: boolean;
  onExportLogged: (exportDetails: { count: number; type: string; filters?: any }) => void;
}

export function VendorExportButton({ 
  vendors, 
  selectedVendors = [], 
  isFiltered = false,
  onExportLogged
}: VendorExportButtonProps) {
  const [showMenu, setShowMenu] = useState(false);

  const exportToExcel = (vendorsToExport: Vendor[], exportType: string) => {
    // In production, this would generate an actual Excel file
    // For now, we'll simulate the export
    
    const exportData = vendorsToExport.map(vendor => ({
      'Vendor ID': vendor.id,
      'Vendor Name': vendor.name,
      'Category/Type': vendor.category || '—',
      'Country': vendor.country || '—',
      'City': vendor.city || '—',
      'Address': vendor.address || '—',
      'Status': vendor.status,
      'Live Status': vendor.liveStatus || '—',
      'Subscription Plan': vendor.subscription,
      'Subscription State': vendor.subscriptionStatus,
      'Payment Status': vendor.payment,
      'Total GMV': vendor.revenue,
      'Rating': vendor.rating || '—',
      'Last Active': vendor.lastActive || '—',
      'Contact Email': vendor.email || '—',
      'Phone': vendor.phone || '—',
      'Website': vendor.website || '—',
      'VAT Number': vendor.vat || '—',
      'Created Date': vendor.createdDate || '—'
    }));

    // Log export action
    onExportLogged({
      count: vendorsToExport.length,
      type: exportType,
      filters: isFiltered ? 'active' : undefined
    });

    // Show success message
    toast.success(`Exported ${vendorsToExport.length} vendors`, {
      description: `Export type: ${exportType}`
    });

    // In production:
    // - Generate Excel file using library like xlsx
    // - Trigger download
    console.log('Export data:', exportData);
    
    setShowMenu(false);
  };

  const handleExportSelected = () => {
    if (selectedVendors.length === 0) {
      toast.error('No vendors selected', {
        description: 'Please select vendors to export'
      });
      return;
    }

    const vendorsToExport = vendors.filter(v => selectedVendors.includes(v.id));
    
    if (vendorsToExport.length > 100) {
      // Confirmation for large exports
      if (confirm(`You are about to export ${vendorsToExport.length} vendors. Continue?`)) {
        exportToExcel(vendorsToExport, 'selected');
      }
    } else {
      exportToExcel(vendorsToExport, 'selected');
    }
  };

  const handleExportAll = () => {
    const vendorsToExport = vendors;
    
    if (vendorsToExport.length > 100) {
      // Confirmation for large exports
      if (confirm(`You are about to export ${vendorsToExport.length} vendors. Continue?`)) {
        exportToExcel(vendorsToExport, isFiltered ? 'filtered' : 'all');
      }
    } else {
      exportToExcel(vendorsToExport, isFiltered ? 'filtered' : 'all');
    }
  };

  return (
    <div className="relative">
      <button
        onClick={() => setShowMenu(!showMenu)}
        className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors"
      >
        <Download className="w-4 h-4" />
        Export
      </button>

      {showMenu && (
        <>
          <div 
            className="fixed inset-0 z-10" 
            onClick={() => setShowMenu(false)}
          />
          <div className="absolute right-0 top-full mt-2 bg-white rounded-lg shadow-lg border border-gray-200 py-2 min-w-[280px] z-20">
            {/* Header */}
            <div className="px-4 py-2 border-b border-gray-200">
              <div className="flex items-center gap-2 text-sm font-semibold text-gray-900">
                <FileSpreadsheet className="w-4 h-4 text-green-600" />
                Export to Excel
              </div>
              <p className="text-xs text-gray-600 mt-1">
                Export vendor data with all fields
              </p>
            </div>

            {/* Export Options */}
            <div className="py-2">
              {selectedVendors.length > 0 && (
                <button
                  onClick={handleExportSelected}
                  className="w-full px-4 py-3 text-left hover:bg-gray-50 flex items-start gap-3 transition-colors"
                >
                  <CheckSquare className="w-5 h-5 text-purple-600 mt-0.5" />
                  <div className="flex-1">
                    <div className="text-sm font-medium text-gray-900">
                      Export Selected ({selectedVendors.length})
                    </div>
                    <div className="text-xs text-gray-600 mt-0.5">
                      Only export selected vendors
                    </div>
                  </div>
                </button>
              )}

              <button
                onClick={handleExportAll}
                className="w-full px-4 py-3 text-left hover:bg-gray-50 flex items-start gap-3 transition-colors"
              >
                <Square className="w-5 h-5 text-blue-600 mt-0.5" />
                <div className="flex-1">
                  <div className="text-sm font-medium text-gray-900">
                    Export All ({vendors.length})
                  </div>
                  <div className="text-xs text-gray-600 mt-0.5">
                    {isFiltered 
                      ? 'Respects current filters' 
                      : 'Export all vendors in system'}
                  </div>
                </div>
              </button>
            </div>

            {/* Export Fields Info */}
            <div className="px-4 py-3 border-t border-gray-200 bg-gray-50">
              <div className="text-xs font-semibold text-gray-700 mb-2">
                Exported Fields:
              </div>
              <div className="text-xs text-gray-600 space-y-1">
                <div>• Vendor ID, Name, Category</div>
                <div>• Location (Country, City, Address)</div>
                <div>• Status, Live Status, Subscription</div>
                <div>• Payment, GMV, Rating</div>
                <div>• Contact (Email, Phone, Website, VAT)</div>
                <div>• Timestamps (Created, Last Active)</div>
              </div>
            </div>

            {/* Large Export Warning */}
            {vendors.length > 100 && (
              <div className="px-4 py-2 bg-yellow-50 border-t border-yellow-200">
                <p className="text-xs text-yellow-800">
                  ⚠️ Large export ({vendors.length} vendors) - confirmation required
                </p>
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}
