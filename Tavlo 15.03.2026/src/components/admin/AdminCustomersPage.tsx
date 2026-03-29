import { useState } from 'react';
import { CustomersList } from './CustomersList';
import { CustomerSupportOverview } from './CustomerSupportOverview';

export function AdminCustomersPage() {
  const [selectedCustomerId, setSelectedCustomerId] = useState<string | null>(null);
  const [restrictedDataVisible, setRestrictedDataVisible] = useState(false);

  if (selectedCustomerId) {
    return (
      <CustomerSupportOverview
        customerId={selectedCustomerId}
        onBack={() => setSelectedCustomerId(null)}
        restrictedDataVisible={restrictedDataVisible}
      />
    );
  }

  return (
    <CustomersList
      onNavigateToCustomer={(customerId) => setSelectedCustomerId(customerId)}
    />
  );
}
