import { useState } from 'react';
import { VendorMonitoring } from '../components/admin/VendorMonitoring';

export default function AdminVendorManagement() {
  const [selectedVendor, setSelectedVendor] = useState<string | null>(null);

  const handleViewVendor = (vendorId: string) => {
    console.log('View vendor:', vendorId);
    // In production, this would navigate to vendor details page
    setSelectedVendor(vendorId);
    alert(`Viewing vendor ${vendorId}\n\nIn production, this would show:\n- Full vendor profile\n- Setup progress\n- Subscription details\n- Order history\n- Support tickets`);
  };

  const handleSuspendVendor = (vendorId: string) => {
    const reason = prompt('Enter suspension reason:');
    if (reason) {
      // Call API to suspend vendor
      fetch(`/api/vendor/${vendorId}/suspend`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reason })
      })
        .then(() => {
          alert('Vendor suspended successfully');
        })
        .catch((error) => {
          alert('Failed to suspend vendor: ' + error.message);
        });
    }
  };

  return (
    <VendorMonitoring
      onViewVendor={handleViewVendor}
      onSuspendVendor={handleSuspendVendor}
    />
  );
}
