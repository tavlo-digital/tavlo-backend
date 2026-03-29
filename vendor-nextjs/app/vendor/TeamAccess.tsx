import React, { useState } from 'react';
import { 
  Users, 
  Mail, 
  MoreVertical, 
  X, 
  Check,
  AlertCircle,
  ChevronRight,
  Clock,
  Shield,
  Zap,
  ChefHat,
  UserCheck,
  Briefcase,
  Info,
  Trash2
} from 'lucide-react';
import { toast } from 'sonner';

interface Permission {
  id: string;
  label: string;
  description: string;
}

interface PermissionGroup {
  id: string;
  title: string;
  icon: React.ReactNode;
  permissions: Permission[];
  subsections?: {
    title: string;
    permissions: Permission[];
  }[];
}

interface User {
  id: string;
  name: string;
  email: string;
  role: 'owner' | 'custom';
  status: 'active' | 'invited' | 'disabled';
  lastActive?: string;
  permissions: string[];
}

const PERMISSION_GROUPS: PermissionGroup[] = [
  {
    id: 'orders',
    title: 'Orders & Operations',
    icon: <Zap className="w-5 h-5" />,
    permissions: [
      { id: 'orders.view', label: 'View live orders', description: 'See incoming orders in real-time' },
      { id: 'orders.manage', label: 'Manage orders', description: 'Accept, reject, and mark orders as ready' },
      { id: 'orders.kitchen', label: 'Kitchen mode', description: 'Orders-only view without prices or settings' }
    ]
  },
  {
    id: 'reservations',
    title: 'Reservations',
    icon: <Clock className="w-5 h-5" />,
    permissions: [
      { id: 'reservations.view', label: 'View reservations', description: 'See all table reservations' },
      { id: 'reservations.manage', label: 'Manage reservations', description: 'Confirm, cancel, and modify bookings' },
      { id: 'reservations.notifications', label: 'Receive reservation notifications', description: 'Get notified of new or changed reservations' }
    ]
  },
  {
    id: 'customers',
    title: 'Customer Interaction',
    icon: <UserCheck className="w-5 h-5" />,
    subsections: [
      {
        title: 'Access',
        permissions: [
          { id: 'customers.view_requests', label: 'View customer requests', description: 'See customer-initiated requests' },
          { id: 'customers.respond', label: 'Respond to customer requests', description: 'Acknowledge and act on customer requests' },
          { id: 'customers.notes', label: 'View customer notes', description: 'Access special requests and dietary requirements' }
        ]
      },
      {
        title: 'Notifications',
        permissions: [
          { id: 'customers.waiter_calls', label: 'Receive "Call Waiter" notifications', description: 'Get alerted when customers request assistance' },
          { id: 'customers.payment_notifications', label: 'Receive payment completed notifications', description: 'Be notified when a payment is processed' }
        ]
      }
    ],
    permissions: []
  },
  {
    id: 'menu',
    title: 'Menu & Content',
    icon: <ChefHat className="w-5 h-5" />,
    permissions: [
      { id: 'menu.view', label: 'View menu', description: 'See all menu items and categories' },
      { id: 'menu.edit', label: 'Edit menu items', description: 'Modify dishes, descriptions, and images' },
      { id: 'menu.pricing', label: 'Change prices', description: 'Update item prices' },
      { id: 'menu.availability', label: 'Manage availability', description: 'Mark items as sold out or hidden' }
    ]
  },
  {
    id: 'analytics',
    title: 'Analytics & Reports',
    icon: <Briefcase className="w-5 h-5" />,
    permissions: [
      { id: 'analytics.view', label: 'View sales analytics', description: 'Access sales dashboards and charts' },
      { id: 'analytics.reports', label: 'View performance reports', description: 'See detailed business reports' },
      { id: 'analytics.export', label: 'Export data', description: 'Download reports and data files' }
    ]
  },
  {
    id: 'settings',
    title: 'Settings',
    icon: <Shield className="w-5 h-5" />,
    permissions: [
      { id: 'settings.view', label: 'View settings', description: 'Access restaurant settings' },
      { id: 'settings.operational', label: 'Edit operational settings', description: 'Modify opening hours, tables, and service options' },
      { id: 'settings.team', label: 'Manage users', description: 'Invite, edit, and disable team members' }
    ]
  }
];

const ROLE_PRESETS = {
  kitchen: {
    name: 'Kitchen',
    icon: <ChefHat className="w-4 h-4" />,
    permissions: ['orders.view', 'orders.manage', 'orders.kitchen', 'menu.view']
  },
  waiter: {
    name: 'Waiter',
    icon: <UserCheck className="w-4 h-4" />,
    permissions: [
      'orders.view',
      'orders.manage',
      'reservations.view',
      'reservations.manage',
      'customers.view_requests',
      'customers.respond',
      'customers.waiter_calls',
      'customers.payment_notifications',
      'customers.notes',
      'menu.view',
      'menu.availability'
    ]
  },
  manager: {
    name: 'Manager',
    icon: <Briefcase className="w-4 h-4" />,
    permissions: [
      'orders.view',
      'orders.manage',
      'reservations.view',
      'reservations.manage',
      'reservations.notifications',
      'customers.view_requests',
      'customers.respond',
      'customers.waiter_calls',
      'customers.payment_notifications',
      'customers.notes',
      'menu.view',
      'menu.edit',
      'menu.pricing',
      'menu.availability',
      'analytics.view',
      'analytics.reports',
      'analytics.export',
      'settings.view',
      'settings.operational'
    ]
  }
};

interface TeamAccessProps {
  vendorId: string;
}

export function TeamAccess({ vendorId }: TeamAccessProps) {
  // Mock data - replace with API calls
  const [users, setUsers] = useState<User[]>([
    {
      id: '1',
      name: 'Maria Schmidt',
      email: 'maria@labellacucina.com',
      role: 'owner',
      status: 'active',
      lastActive: '2 minutes ago',
      permissions: [] // Owner has all permissions
    },
    {
      id: '2',
      name: 'Thomas Weber',
      email: 'thomas@labellacucina.com',
      role: 'custom',
      status: 'active',
      lastActive: '5 minutes ago',
      permissions: ROLE_PRESETS.waiter.permissions
    },
    {
      id: '3',
      name: 'Anna Müller',
      email: 'anna@labellacucina.com',
      role: 'custom',
      status: 'invited',
      permissions: ROLE_PRESETS.kitchen.permissions
    }
  ]);

  const [isInviteOpen, setIsInviteOpen] = useState(false);
  const [inviteEmail, setInviteEmail] = useState('');
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);
  const [activePreset, setActivePreset] = useState<string | null>(null);
  const [openDropdown, setOpenDropdown] = useState<string | null>(null);
  const [deleteConfirmUser, setDeleteConfirmUser] = useState<User | null>(null);

  // Plan limits
  const userLimit = 5;
  const currentUserCount = users.length;

  const handleInviteUser = () => {
    if (!inviteEmail) {
      toast.error('Please enter an email address');
      return;
    }

    if (currentUserCount >= userLimit) {
      toast.error('User limit reached. Upgrade your plan to add more users.');
      return;
    }

    // Mock invite
    const newUser: User = {
      id: Date.now().toString(),
      name: inviteEmail.split('@')[0],
      email: inviteEmail,
      role: 'custom',
      status: 'invited',
      permissions: []
    };

    setUsers([...users, newUser]);
    setInviteEmail('');
    setIsInviteOpen(false);
    toast.success(`Invitation sent to ${inviteEmail}`);
  };

  const handleEditUser = (user: User) => {
    setEditingUser(user);
    setSelectedPermissions(user.permissions);
    setActivePreset(null);
  };

  const handleSavePermissions = () => {
    if (!editingUser) return;

    setUsers(users.map(u => 
      u.id === editingUser.id 
        ? { ...u, permissions: selectedPermissions }
        : u
    ));

    setEditingUser(null);
    toast.success('Access updated successfully');
  };

  const handleTogglePermission = (permissionId: string) => {
    if (selectedPermissions.includes(permissionId)) {
      setSelectedPermissions(selectedPermissions.filter(p => p !== permissionId));
    } else {
      setSelectedPermissions([...selectedPermissions, permissionId]);
    }
    setActivePreset(null); // Clear preset when manually changing
  };

  const handleApplyPreset = (presetKey: keyof typeof ROLE_PRESETS) => {
    const preset = ROLE_PRESETS[presetKey];
    setSelectedPermissions(preset.permissions);
    setActivePreset(presetKey);
  };

  const handleDisableUser = (userId: string) => {
    setUsers(users.map(u => 
      u.id === userId 
        ? { ...u, status: u.status === 'disabled' ? 'active' : 'disabled' as const }
        : u
    ));
    const user = users.find(u => u.id === userId);
    toast.success(user?.status === 'disabled' ? 'User re-enabled' : 'User disabled');
    setOpenDropdown(null);
  };

  const handleResendInvite = (userId: string) => {
    const user = users.find(u => u.id === userId);
    toast.success(`Invitation resent to ${user?.email}`);
    setOpenDropdown(null);
  };

  const handleDeleteUser = () => {
    if (!deleteConfirmUser) return;
    
    setUsers(users.filter(u => u.id !== deleteConfirmUser.id));
    toast.success('User permanently deleted');
    setDeleteConfirmUser(null);
  };

  const openDeleteConfirm = (user: User) => {
    if (user.role === 'owner') {
      toast.error('Cannot delete owner');
      return;
    }
    setDeleteConfirmUser(user);
    setOpenDropdown(null);
  };

  return (
    <div className="max-w-6xl mx-auto">
      {/* Page Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Team & Access</h1>
        <p className="text-gray-600">Manage who can access your restaurant and what they can do</p>
      </div>

      {/* Users Table */}
      <div className="bg-white rounded-lg border border-gray-200 mb-6">
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Users className="w-5 h-5 text-gray-400" />
            <h2 className="font-semibold text-gray-900">Team Members</h2>
            <span className="text-sm text-gray-500">({currentUserCount})</span>
          </div>
          <button
            onClick={() => setIsInviteOpen(true)}
            disabled={currentUserCount >= userLimit}
            className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <Mail className="w-4 h-4" />
            Invite User
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Active</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {users.map((user) => (
                <tr key={user.id} className={`hover:bg-gray-50 ${user.status === 'disabled' ? 'opacity-60' : ''}`}>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-medium">
                        {user.name.charAt(0)}
                      </div>
                      <div className="font-medium text-gray-900">{user.name}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span 
                      className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        user.role === 'owner' 
                          ? 'bg-purple-100 text-purple-700'
                          : 'bg-gray-100 text-gray-700'
                      }`}
                      title={user.role === 'owner' ? 'Owners always have full access and cannot be restricted or removed.' : ''}
                    >
                      {user.role === 'owner' && <Shield className="w-3 h-3" />}
                      {user.role === 'owner' ? 'Owner (full access)' : 'Custom'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{user.email}</td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${
                      user.status === 'active' ? 'bg-green-100 text-green-700' :
                      user.status === 'invited' ? 'bg-blue-100 text-blue-700' :
                      'bg-gray-100 text-gray-700'
                    }`}>
                      {user.status === 'active' ? 'Active' : user.status === 'invited' ? 'Invited' : 'Disabled'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {user.lastActive || '—'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm">
                    <div className="relative">
                      <button
                        onClick={() => setOpenDropdown(openDropdown === user.id ? null : user.id)}
                        className="p-1 hover:bg-gray-100 rounded transition-colors"
                      >
                        <MoreVertical className="w-5 h-5 text-gray-400" />
                      </button>
                      
                      {openDropdown === user.id && (
                        <>
                          <div 
                            className="fixed inset-0 z-10"
                            onClick={() => setOpenDropdown(null)}
                          />
                          <div className="absolute right-0 top-full mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-20">
                            <button
                              onClick={() => {
                                handleEditUser(user);
                                setOpenDropdown(null);
                              }}
                              disabled={user.role === 'owner' || user.status === 'disabled'}
                              className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed block"
                            >
                              Edit access
                            </button>
                            <button
                              onClick={() => handleDisableUser(user.id)}
                              disabled={user.role === 'owner'}
                              title={user.status === 'disabled' ? 'User regains access immediately' : 'User loses access immediately. Activity history is preserved.'}
                              className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed block"
                            >
                              {user.status === 'disabled' ? 'Re-enable user' : 'Disable user'}
                            </button>
                            {user.status === 'invited' && (
                              <button
                                onClick={() => handleResendInvite(user.id)}
                                className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 block"
                              >
                                Resend invite
                              </button>
                            )}
                            <div className="border-t border-gray-200 my-1"></div>
                            <button
                              onClick={() => openDeleteConfirm(user)}
                              disabled={user.role === 'owner'}
                              className="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed block flex items-center gap-2"
                            >
                              <Trash2 className="w-4 h-4" />
                              Delete user
                            </button>
                          </div>
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Plan Limitation Indicator */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
        <AlertCircle className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
        <div className="flex-1">
          <div className="text-sm font-medium text-blue-900">
            Users: {currentUserCount} / {userLimit} used
          </div>
          <div className="text-sm text-blue-700 mt-1">
            {currentUserCount >= userLimit ? (
              <>Upgrade your plan to add more users</>
            ) : (
              <>{userLimit - currentUserCount} user slots remaining</>
            )}
          </div>
        </div>
        {currentUserCount >= userLimit && (
          <button className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
            Upgrade Plan
          </button>
        )}
      </div>

      {/* Invite User Modal */}
      {isInviteOpen && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg max-w-md w-full p-6">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-semibold text-gray-900">Invite New User</h2>
              <button
                onClick={() => setIsInviteOpen(false)}
                className="p-1 hover:bg-gray-100 rounded transition-colors"
              >
                <X className="w-5 h-5 text-gray-400" />
              </button>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Email address
                </label>
                <input
                  type="email"
                  value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  placeholder="colleague@example.com"
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                />
              </div>

              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-sm text-blue-800">
                  Invited users will receive an email to set their password. You can configure access and notifications after the user accepts the invite.
                </p>
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  onClick={() => setIsInviteOpen(false)}
                  className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  onClick={handleInviteUser}
                  className="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
                >
                  Send Invite
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {deleteConfirmUser && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg max-w-md w-full p-6">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-semibold text-gray-900">Permanently delete user?</h2>
              <button
                onClick={() => setDeleteConfirmUser(null)}
                className="p-1 hover:bg-gray-100 rounded transition-colors"
              >
                <X className="w-5 h-5 text-gray-400" />
              </button>
            </div>

            <div className="space-y-4">
              <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <div className="flex gap-3">
                  <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                  <div className="text-sm text-red-800">
                    <div className="font-medium mb-2">This action cannot be undone.</div>
                    <div className="space-y-1">
                      <div>• {deleteConfirmUser.name} will be removed permanently</div>
                      <div>• All past activity remains linked to your restaurant</div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  onClick={() => setDeleteConfirmUser(null)}
                  className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  onClick={handleDeleteUser}
                  className="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2"
                >
                  <Trash2 className="w-4 h-4" />
                  Delete permanently
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Permission Editor Drawer */}
      {editingUser && (
        <div className="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-end z-50">
          <div 
            className="absolute inset-0"
            onClick={() => setEditingUser(null)}
          />
          <div className="relative bg-white w-full sm:w-[600px] h-full sm:h-auto sm:max-h-[90vh] flex flex-col sm:rounded-l-lg shadow-xl">
            {/* Header */}
            <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
              <div>
                <h2 className="text-xl font-semibold text-gray-900">Edit User Access</h2>
                <p className="text-sm text-gray-600 mt-1">{editingUser.name}</p>
              </div>
              <button
                onClick={() => setEditingUser(null)}
                className="p-1 hover:bg-gray-100 rounded transition-colors"
              >
                <X className="w-5 h-5 text-gray-400" />
              </button>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto px-6 py-6">
              {/* Owner Role Info */}
              {editingUser.role === 'owner' ? (
                <div className="bg-purple-50 border border-purple-200 rounded-lg p-4 flex gap-3">
                  <Shield className="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" />
                  <div className="text-sm text-purple-800">
                    <div className="font-medium mb-1">Owner access cannot be modified</div>
                    <div>Owners always have full access to all features and settings.</div>
                  </div>
                </div>
              ) : (
                <>
                  {/* Permission Scope Explanation */}
                  <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 flex gap-3 mb-6">
                    <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                    <div className="text-sm text-blue-800">
                      Permissions control which sections and actions are visible to this user. Disabled permissions remove the feature entirely from their view.
                    </div>
                  </div>

                  {/* Quick Presets */}
                  <div className="mb-6">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Quick presets</label>
                    <p className="text-xs text-gray-500 mb-3">Presets are starting points. You can customize access below.</p>
                    <div className="grid grid-cols-3 gap-3">
                      {Object.entries(ROLE_PRESETS).map(([key, preset]) => (
                        <button
                          key={key}
                          onClick={() => handleApplyPreset(key as keyof typeof ROLE_PRESETS)}
                          className={`px-4 py-3 border-2 rounded-lg flex flex-col items-center gap-2 transition-colors ${
                            activePreset === key
                              ? 'border-emerald-500 bg-emerald-50'
                              : 'border-gray-200 hover:border-gray-300'
                          }`}
                        >
                          <div className={activePreset === key ? 'text-emerald-600' : 'text-gray-600'}>
                            {preset.icon}
                          </div>
                          <span className={`text-sm font-medium ${
                            activePreset === key ? 'text-emerald-700' : 'text-gray-700'
                          }`}>
                            {preset.name}
                          </span>
                        </button>
                      ))}
                    </div>
                  </div>

                  {/* Permission Groups */}
                  <div className="space-y-6">
                    {PERMISSION_GROUPS.map((group) => (
                      <div key={group.id}>
                        <div className="flex items-center gap-2 mb-3">
                          <div className="text-emerald-600">{group.icon}</div>
                          <h3 className="font-semibold text-gray-900">{group.title}</h3>
                        </div>
                        
                        {/* Check if this group has subsections (Customer Interaction) */}
                        {group.subsections && group.subsections.length > 0 ? (
                          <div className="space-y-4 ml-7">
                            {group.subsections.map((subsection, idx) => (
                              <div key={idx}>
                                <h4 className="text-sm font-medium text-gray-700 mb-2">{subsection.title}</h4>
                                <div className="space-y-3">
                                  {subsection.permissions.map((permission) => (
                                    <label
                                      key={permission.id}
                                      className="flex items-start gap-3 cursor-pointer group"
                                    >
                                      <div className="flex items-center h-5">
                                        <input
                                          type="checkbox"
                                          checked={selectedPermissions.includes(permission.id)}
                                          onChange={() => handleTogglePermission(permission.id)}
                                          className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                        />
                                      </div>
                                      <div className="flex-1 min-w-0">
                                        <div className="text-sm font-medium text-gray-900 group-hover:text-emerald-600 transition-colors">
                                          {permission.label}
                                        </div>
                                        <div className="text-xs text-gray-500 mt-0.5">
                                          {permission.description}
                                        </div>
                                      </div>
                                    </label>
                                  ))}
                                </div>
                                
                                {/* Add notification note after Notifications subsection */}
                                {subsection.title === 'Notifications' && (
                                  <p className="text-xs text-gray-500 mt-3 italic">
                                    Notifications control alerts only. They do not grant access to features.
                                  </p>
                                )}
                                
                                {/* Add divider between subsections */}
                                {idx < group.subsections!.length - 1 && (
                                  <div className="border-t border-gray-200 my-3"></div>
                                )}
                              </div>
                            ))}
                          </div>
                        ) : (
                          /* Regular permission list */
                          <div className="space-y-3 ml-7">
                            {group.permissions.map((permission) => (
                              <label
                                key={permission.id}
                                className="flex items-start gap-3 cursor-pointer group"
                              >
                                <div className="flex items-center h-5">
                                  <input
                                    type="checkbox"
                                    checked={selectedPermissions.includes(permission.id)}
                                    onChange={() => handleTogglePermission(permission.id)}
                                    className="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500"
                                  />
                                </div>
                                <div className="flex-1 min-w-0">
                                  <div className="text-sm font-medium text-gray-900 group-hover:text-emerald-600 transition-colors">
                                    {permission.label}
                                  </div>
                                  <div className="text-xs text-gray-500 mt-0.5">
                                    {permission.description}
                                  </div>
                                </div>
                              </label>
                            ))}
                          </div>
                        )}
                      </div>
                    ))}

                    {/* Settings Warning */}
                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 flex gap-3">
                      <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                      <div className="text-sm text-amber-800">
                        <div className="font-medium mb-1">Owner-only settings</div>
                        <div>Billing, subscription, and legal settings are restricted to the Owner role.</div>
                      </div>
                    </div>
                  </div>
                </>
              )}
            </div>

            {/* Footer */}
            <div className="px-6 py-4 border-t border-gray-200 flex gap-3 flex-shrink-0">
              <button
                onClick={() => setEditingUser(null)}
                className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
              {editingUser.role !== 'owner' && (
                <button
                  onClick={handleSavePermissions}
                  className="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2"
                >
                  <Check className="w-4 h-4" />
                  Save changes
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
