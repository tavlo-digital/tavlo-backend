import { useState } from 'react';
import { AdminLayout } from './AdminLayout';
import { Dashboard } from './Dashboard';
import { AdminDashboard } from './AdminDashboard';
import { AdminDashboard_v1_1 } from './AdminDashboard_v1.1';
import { AdminPageState } from './AdminNavigationService';
import { VendorsList } from './VendorsList';
import { VendorsList_v1_1 } from './VendorsList_v1.1';
import { VendorsList_v1_2 } from './VendorsList_v1.2';
import { VendorDetailPage } from './VendorDetailPage';
import { ImprovedVendorManagement } from './ImprovedVendorManagement';
import { ImprovedCustomerManagement } from './ImprovedCustomerManagement';
import { VendorApproval } from './VendorApproval';
import { InvoiceManagement } from './InvoiceManagement';
import { FinanceBillingOverview } from './FinanceBillingOverview';
import { CustomerManagement } from './CustomerManagement';
import { AdminCustomersPage } from './AdminCustomersPage';
import { SubscriptionManagement } from './SubscriptionManagement';
import { EnhancedSubscriptionManagement } from './EnhancedSubscriptionManagement';
import { ModerationManagement } from './ModerationManagement';
import { SystemSettings } from './SystemSettings';
import { AIAdminInsights } from './AIAdminInsights';
import { AuditLog } from './AuditLog';
import { toast } from 'sonner@2.0.3';

export type AdminPage =
  | 'overview'
  | 'vendors'
  | 'vendor-detail'
  | 'customers'
  | 'billing'
  | 'subscriptions'
  | 'reviews'
  | 'ai-insights'
  | 'system'
  | 'audit-log';

interface DashboardContext {
  source: string;
  description: string;
}

export function AdminApp() {
  const [currentPage, setCurrentPage] = useState<AdminPage>('overview');
  const [pageFilters, setPageFilters] = useState<Record<string, any>>({});
  const [vendorDetailId, setVendorDetailId] = useState<string | null>(null);
  const [vendorDetailTab, setVendorDetailTab] = useState<string>('overview');
  const [dashboardContext, setDashboardContext] = useState<DashboardContext | undefined>();

  const handleNavigate = (page: string) => {
    setCurrentPage(page as AdminPage);
    setPageFilters({});
    setVendorDetailId(null);
  };

  // Handle navigation from dashboard with filters and context
  const handleDashboardNavigate = (state: AdminPageState) => {
    console.log('Dashboard navigate:', state);
    
    // If navigating to vendor detail
    if (state.entityType === 'vendor' && state.entityId) {
      setVendorDetailId(state.entityId);
      setVendorDetailTab(state.tab || 'overview');
      setCurrentPage('vendor-detail');
      setPageFilters({});
      setDashboardContext(undefined);
    } else {
      setCurrentPage(state.page);
      setPageFilters(state.filters || {});
      setVendorDetailId(null);
      
      // Set dashboard context for vendors page
      if (state.page === 'vendors' && state.context) {
        setDashboardContext({
          source: state.context.source || 'Dashboard',
          description: state.context.description || 'Filtered view from dashboard'
        });
      }
    }
    
    // Show toast with navigation context
    if (state.filters && Object.keys(state.filters).length > 0) {
      const filterDesc = Object.entries(state.filters)
        .map(([key, value]) => `${key}: ${value}`)
        .join(', ');
      toast.info(`Navigating to ${state.page}`, {
        description: `Filters: ${filterDesc}`
      });
    }
  };

  // Handle vendor detail navigation from vendor list
  const handleVendorDetailNavigate = (vendorId: string, tab?: string) => {
    setVendorDetailId(vendorId);
    setVendorDetailTab(tab || 'overview');
    setCurrentPage('vendor-detail');
  };

  // Handle export logging
  const handleExportLogged = (exportDetails: any) => {
    console.log('Export logged:', exportDetails);
    // In production: Write to audit log database
    toast.success('Export logged to audit trail');
  };

  // Handle audit actions
  const handleAuditAction = (action: string, details: any) => {
    console.log('Audit action:', action, details);
    // In production: Write to global audit log
  };

  const handleVendorApprove = () => {
    toast.success('Vendor approved successfully!');
    setCurrentPage('vendors');
  };

  const handleVendorReject = () => {
    toast.success('Vendor application rejected');
    setCurrentPage('vendors');
  };

  const renderPageContent = () => {
    switch (currentPage) {
      case 'overview':
        return <AdminDashboard_v1_1 onNavigate={handleDashboardNavigate} />;
      
      case 'vendors':
        return (
          <VendorsList_v1_2 
            appliedFilters={pageFilters} 
            dashboardContext={dashboardContext}
            onNavigateToVendorDetail={handleVendorDetailNavigate}
            onExportLogged={handleExportLogged}
            onAuditAction={handleAuditAction}
          />
        );
      
      case 'vendor-approval':
        return (
          <VendorApproval
            onBack={() => setCurrentPage('vendors')}
            onApprove={handleVendorApprove}
            onReject={handleVendorReject}
          />
        );
      
      case 'vendor-detail':
        return (
          <VendorDetailPage
            vendorId={vendorDetailId!}
            initialTab={vendorDetailTab as any}
            onBack={() => setCurrentPage('vendors')}
          />
        );
      
      case 'customers':
        return <AdminCustomersPage page={currentPage} />;
      
      case 'billing':
        return <FinanceBillingOverview />;
      
      case 'subscriptions':
        return <EnhancedSubscriptionManagement page={currentPage} />;
      
      case 'reviews':
        return <ModerationManagement page={currentPage} />;
      
      case 'system':
        return <SystemSettings />;
      
      case 'ai-insights':
        return <AIAdminInsights />;
      
      case 'audit-log':
        return <AuditLog />;
      
      default:
        return <Dashboard />;
    }
  };

  return (
    <AdminLayout currentPage={currentPage} onNavigate={handleNavigate}>
      {renderPageContent()}
    </AdminLayout>
  );
}