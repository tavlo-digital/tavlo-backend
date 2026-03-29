import { AlertCircle, ChevronRight } from 'lucide-react';

interface PendingChange {
  vendorId: string;
  vendorName: string;
  changeType: 'legal' | 'business' | 'contact';
  submittedAt: string;
  urgency: 'high' | 'normal';
}

interface VendorPendingChangesCardProps {
  onNavigate: (vendorId: string) => void;
}

export function VendorPendingChangesCard({ onNavigate }: VendorPendingChangesCardProps) {
  // Mock data - would come from API
  const pendingChanges: PendingChange[] = [
    {
      vendorId: 'VID-8492',
      vendorName: 'Bella Italia',
      changeType: 'legal',
      submittedAt: '2 hours ago',
      urgency: 'high'
    },
    {
      vendorId: 'VID-2847',
      vendorName: 'Pizza Express',
      changeType: 'business',
      submittedAt: '5 hours ago',
      urgency: 'normal'
    },
    {
      vendorId: 'VID-9471',
      vendorName: 'Sakura Sushi',
      changeType: 'contact',
      submittedAt: '1 day ago',
      urgency: 'normal'
    }
  ];

  const urgentCount = pendingChanges.filter(c => c.urgency === 'high').length;

  return (
    <div className="bg-white rounded-lg border border-orange-200 shadow-sm">
      {/* Header */}
      <div className="px-6 py-4 border-b border-orange-100 bg-orange-50">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
              <AlertCircle className="w-5 h-5 text-orange-600" />
            </div>
            <div>
              <h3 className="font-semibold text-gray-900">Pending Vendor Changes</h3>
              <p className="text-sm text-gray-600">
                {pendingChanges.length} changes awaiting review
                {urgentCount > 0 && (
                  <span className="ml-2 text-orange-600 font-medium">
                    • {urgentCount} urgent
                  </span>
                )}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Change List */}
      <div className="divide-y divide-gray-100">
        {pendingChanges.map((change) => (
          <button
            key={`${change.vendorId}-${change.changeType}`}
            onClick={() => onNavigate(change.vendorId)}
            className="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors group"
          >
            <div className="flex items-center gap-4">
              {change.urgency === 'high' && (
                <div className="w-2 h-2 bg-orange-500 rounded-full" />
              )}
              <div className="text-left">
                <div className="font-medium text-gray-900 group-hover:text-purple-700 transition-colors">
                  {change.vendorName}
                </div>
                <div className="text-sm text-gray-600">
                  {change.changeType === 'legal' && 'Legal information changes'}
                  {change.changeType === 'business' && 'Business details changes'}
                  {change.changeType === 'contact' && 'Contact information changes'}
                  <span className="ml-2 text-gray-400">• {change.submittedAt}</span>
                </div>
              </div>
            </div>
            <ChevronRight className="w-5 h-5 text-gray-400 group-hover:text-purple-600 transition-colors" />
          </button>
        ))}
      </div>

      {/* Footer */}
      <div className="px-6 py-3 bg-gray-50 border-t border-gray-100">
        <button className="text-sm text-purple-600 hover:text-purple-700 font-medium">
          View all pending changes →
        </button>
      </div>
    </div>
  );
}
