import { useState } from 'react';
import { 
  Plus, 
  Edit, 
  Trash2, 
  Lock, 
  CheckCircle, 
  XCircle, 
  X, 
  Info, 
  Eye, 
  EyeOff, 
  Key,
  AlertTriangle,
  Shield,
  Users,
  Copy,
  Check
} from 'lucide-react';
import { Input } from '../ui/input';
import { toast } from 'sonner@2.0.3';

// Permission structure for all modules
interface ModulePermissions {
  view: boolean;
  edit: boolean;
  special?: string[]; // Special permissions like "suspend", "delete", "export"
}

interface Permission {
  dashboard: ModulePermissions;
  vendors: ModulePermissions;
  customers: ModulePermissions;
  finance: ModulePermissions;
  subscriptions: ModulePermissions;
  reviews: ModulePermissions;
  system: ModulePermissions;
  audit: ModulePermissions;
  adminUsers: ModulePermissions;
}

interface Role {
  id: string;
  name: string;
  description: string;
  userCount: number;
  isSystem: boolean; // Super Admin is system role
  permissions: Permission;
}

interface AdminUser {
  id: string;
  username: string;
  email: string;
  roleId: string;
  status: 'active' | 'disabled';
  createdAt: string;
  lastLogin: string | null;
}

interface EnhancedRolesPermissionsProps {
  currentUserRole: 'super_admin' | 'finance_admin' | 'support_admin'; // For demo
}

export function EnhancedRolesPermissions({ currentUserRole = 'super_admin' }: EnhancedRolesPermissionsProps) {
  const isSuperAdmin = currentUserRole === 'super_admin';

  const [selectedRole, setSelectedRole] = useState<string | null>('super_admin');
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingRole, setEditingRole] = useState<Role | null>(null);
  const [showDeleteModal, setShowDeleteModal] = useState<Role | null>(null);
  const [showAdminUserModal, setShowAdminUserModal] = useState(false);
  const [showPasswordResetModal, setShowPasswordResetModal] = useState<AdminUser | null>(null);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  const [roles, setRoles] = useState<Role[]>([
    {
      id: 'super_admin',
      name: 'Super Admin',
      description: 'Full platform access - system role',
      userCount: 2,
      isSystem: true,
      permissions: {
        dashboard: { view: true, edit: true },
        vendors: { view: true, edit: true, special: ['suspend', 'approve'] },
        customers: { view: true, edit: true, special: ['gdpr-export', 'gdpr-delete'] },
        finance: { view: true, edit: true, special: ['export', 'vat-report'] },
        subscriptions: { view: true, edit: true, special: ['create-plan'] },
        reviews: { view: true, edit: true, special: ['moderate', 'delete'] },
        system: { view: true, edit: true },
        audit: { view: true, edit: false, special: ['export'] },
        adminUsers: { view: true, edit: true, special: ['create', 'reset-password', 'assign-role'] }
      }
    },
    {
      id: 'finance_admin',
      name: 'Finance Admin',
      description: 'Billing, invoices, subscriptions, VAT reporting',
      userCount: 3,
      isSystem: false,
      permissions: {
        dashboard: { view: true, edit: false },
        vendors: { view: true, edit: false },
        customers: { view: false, edit: false },
        finance: { view: true, edit: true, special: ['export', 'vat-report'] },
        subscriptions: { view: true, edit: false },
        reviews: { view: false, edit: false },
        system: { view: true, edit: false },
        audit: { view: true, edit: false, special: ['export'] },
        adminUsers: { view: false, edit: false }
      }
    },
    {
      id: 'support_admin',
      name: 'Support Admin',
      description: 'Customer support, complaints, vendor assistance',
      userCount: 5,
      isSystem: false,
      permissions: {
        dashboard: { view: true, edit: false },
        vendors: { view: true, edit: false },
        customers: { view: true, edit: false },
        finance: { view: true, edit: false },
        subscriptions: { view: true, edit: false },
        reviews: { view: true, edit: false },
        system: { view: false, edit: false },
        audit: { view: true, edit: false },
        adminUsers: { view: false, edit: false }
      }
    }
  ]);

  const [adminUsers, setAdminUsers] = useState<AdminUser[]>([
    {
      id: 'user_001',
      username: 'admin',
      email: 'admin@tavlo.com',
      roleId: 'super_admin',
      status: 'active',
      createdAt: '2024-01-15',
      lastLogin: '2025-01-06 14:30'
    },
    {
      id: 'user_002',
      username: 'finance.maria',
      email: 'maria@tavlo.com',
      roleId: 'finance_admin',
      status: 'active',
      createdAt: '2024-03-20',
      lastLogin: '2025-01-05 16:20'
    },
    {
      id: 'user_003',
      username: 'support.john',
      email: 'john@tavlo.com',
      roleId: 'support_admin',
      status: 'active',
      createdAt: '2024-05-10',
      lastLogin: '2025-01-06 09:15'
    }
  ]);

  const handleCreateRole = () => {
    if (!isSuperAdmin) {
      toast.error('Access denied', {
        description: 'Only Super Admin can create roles'
      });
      return;
    }
    setShowCreateModal(true);
  };

  const handleEditRole = (role: Role) => {
    if (!isSuperAdmin) {
      toast.error('Access denied', {
        description: 'Only Super Admin can edit roles'
      });
      return;
    }

    if (role.isSystem) {
      toast.error('Cannot edit system role', {
        description: 'Super Admin role is protected'
      });
      return;
    }

    setEditingRole(role);
  };

  const handleDeleteRole = (role: Role) => {
    if (!isSuperAdmin) {
      toast.error('Access denied', {
        description: 'Only Super Admin can delete roles'
      });
      return;
    }

    if (role.isSystem) {
      toast.error('Cannot delete system role', {
        description: 'Super Admin role is protected'
      });
      return;
    }

    if (role.userCount > 0) {
      toast.error('Cannot delete role', {
        description: `${role.userCount} admin users are assigned to this role. Reassign or delete users first.`
      });
      return;
    }

    setShowDeleteModal(role);
  };

  const confirmDeleteRole = () => {
    if (!showDeleteModal) return;

    // Audit log
    console.log('AUDIT LOG: Role deleted', {
      roleId: showDeleteModal.id,
      roleName: showDeleteModal.name,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString()
    });

    setRoles(roles.filter(r => r.id !== showDeleteModal.id));
    if (selectedRole === showDeleteModal.id) {
      setSelectedRole('super_admin');
    }
    setShowDeleteModal(null);

    toast.success('Role deleted', {
      description: 'Action logged to audit trail'
    });
  };

  const handleCreateAdminUser = () => {
    if (!isSuperAdmin) {
      toast.error('Access denied', {
        description: 'Only Super Admin can create admin users'
      });
      return;
    }
    setShowAdminUserModal(true);
  };

  const handleResetPassword = (user: AdminUser) => {
    if (!isSuperAdmin) {
      toast.error('Access denied', {
        description: 'Only Super Admin can reset passwords'
      });
      return;
    }
    setShowPasswordResetModal(user);
  };

  const selectedRoleData = roles.find(r => r.id === selectedRole);

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-lg font-medium mb-2">Admin Roles & Permissions</h2>
        <p className="text-sm text-gray-500 mb-3">
          Define role-based access control for admin users
        </p>
        <div className="bg-purple-50 border border-purple-200 rounded-lg p-3">
          <div className="flex items-start gap-2">
            <Shield className="w-4 h-4 text-purple-600 flex-shrink-0 mt-0.5" />
            <p className="text-xs text-purple-800">
              <strong>Principle of Least Privilege:</strong> Each role sees only what is necessary for their function.
              Permissions are explicit, not implied. All actions are logged in Audit Log.
            </p>
          </div>
        </div>
      </div>

      {/* Unsaved Changes Banner */}
      {hasUnsavedChanges && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 text-amber-600" />
            <span className="text-sm font-medium text-amber-900">
              You have unsaved changes
            </span>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => setHasUnsavedChanges(false)}
              className="px-3 py-1.5 text-sm text-amber-700 hover:bg-amber-100 rounded-lg"
            >
              Discard
            </button>
            <button
              onClick={() => {
                setHasUnsavedChanges(false);
                toast.success('Changes saved');
              }}
              className="px-3 py-1.5 text-sm bg-amber-600 text-white rounded-lg hover:bg-amber-700"
            >
              Save Changes
            </button>
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Panel - Roles List */}
        <div className="lg:col-span-1">
          <div className="flex items-center justify-between mb-3">
            <h3 className="font-medium text-sm">Admin Roles</h3>
            <span className="text-xs text-gray-500">{roles.length} roles</span>
          </div>
          
          <div className="space-y-2">
            {roles.map((role) => (
              <div
                key={role.id}
                className={`relative p-3 rounded-lg border transition-all ${
                  selectedRole === role.id
                    ? 'border-purple-300 bg-purple-50 ring-1 ring-purple-200'
                    : 'border-gray-200 hover:bg-gray-50'
                }`}
              >
                <button
                  onClick={() => setSelectedRole(role.id)}
                  className="w-full text-left"
                >
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-sm">{role.name}</span>
                        {role.isSystem && (
                          <Lock className="w-3 h-3 text-gray-400" title="System role" />
                        )}
                      </div>
                      <div className="text-xs text-gray-500 mt-0.5">{role.description}</div>
                    </div>
                    <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full ml-2">
                      {role.userCount}
                    </span>
                  </div>
                </button>

                {/* Edit & Delete Icons (Super Admin only) */}
                {isSuperAdmin && !role.isSystem && (
                  <div className="flex items-center gap-1 mt-2 pt-2 border-t border-gray-200">
                    <button
                      onClick={() => handleEditRole(role)}
                      className="flex-1 flex items-center justify-center gap-1 px-2 py-1 text-xs text-purple-600 hover:bg-purple-100 rounded transition-colors"
                      title="Edit role"
                    >
                      <Edit className="w-3 h-3" />
                      Edit
                    </button>
                    <button
                      onClick={() => handleDeleteRole(role)}
                      className="flex-1 flex items-center justify-center gap-1 px-2 py-1 text-xs text-red-600 hover:bg-red-100 rounded transition-colors"
                      title="Delete role"
                    >
                      <Trash2 className="w-3 h-3" />
                      Delete
                    </button>
                  </div>
                )}

                {/* System role locked indicator */}
                {role.isSystem && (
                  <div className="mt-2 pt-2 border-t border-gray-200">
                    <div className="flex items-center gap-1 text-xs text-gray-500">
                      <Lock className="w-3 h-3" />
                      System role (cannot edit/delete)
                    </div>
                  </div>
                )}
              </div>
            ))}

            {/* Create Custom Role Button */}
            <button
              onClick={handleCreateRole}
              disabled={!isSuperAdmin}
              className={`w-full p-3 border-2 border-dashed rounded-lg text-sm flex items-center justify-center gap-2 ${
                isSuperAdmin
                  ? 'border-gray-300 hover:border-purple-400 hover:bg-purple-50 text-gray-600 hover:text-purple-600'
                  : 'border-gray-200 text-gray-400 cursor-not-allowed'
              }`}
              title={!isSuperAdmin ? 'Only Super Admin can create roles' : ''}
            >
              <Plus className="w-4 h-4" />
              Create Custom Role
            </button>

            {!isSuperAdmin && (
              <div className="text-xs text-gray-500 text-center px-2">
                <Lock className="w-3 h-3 inline mr-1" />
                Only Super Admin can create roles
              </div>
            )}
          </div>
        </div>

        {/* Right Panel - Permissions View (Read-Only) */}
        <div className="lg:col-span-2">
          <div className="flex items-center justify-between mb-3">
            <h3 className="font-medium text-sm">
              Permissions for {selectedRoleData?.name}
            </h3>
            {selectedRoleData && !selectedRoleData.isSystem && isSuperAdmin && (
              <button
                onClick={() => handleEditRole(selectedRoleData)}
                className="flex items-center gap-1 px-3 py-1.5 text-sm text-purple-600 border border-purple-300 rounded-lg hover:bg-purple-50"
              >
                <Edit className="w-4 h-4" />
                Edit Permissions
              </button>
            )}
          </div>

          {/* Permission Matrix (Read-Only) */}
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                    Module
                  </th>
                  <th className="px-4 py-3 text-center text-xs font-medium text-gray-600">
                    View
                  </th>
                  <th className="px-4 py-3 text-center text-xs font-medium text-gray-600">
                    Edit
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                    Special Permissions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {selectedRoleData && Object.entries(selectedRoleData.permissions).map(([module, perms]) => (
                  <tr key={module} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-sm font-medium text-gray-900">
                      {formatModuleName(module)}
                    </td>
                    <td className="px-4 py-3 text-center">
                      {perms.view ? (
                        <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                      ) : (
                        <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                      )}
                    </td>
                    <td className="px-4 py-3 text-center">
                      {perms.edit ? (
                        <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                      ) : (
                        <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                      )}
                    </td>
                    <td className="px-4 py-3">
                      {perms.special && perms.special.length > 0 ? (
                        <div className="flex flex-wrap gap-1">
                          {perms.special.map(sp => (
                            <span key={sp} className="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">
                              {sp}
                            </span>
                          ))}
                        </div>
                      ) : (
                        <span className="text-xs text-gray-400">-</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Admin Users Section */}
          <div className="mt-6">
            <div className="flex items-center justify-between mb-3">
              <h3 className="font-medium text-sm">Admin Users in this Role</h3>
              {isSuperAdmin && (
                <button
                  onClick={handleCreateAdminUser}
                  className="flex items-center gap-1 px-3 py-1.5 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700"
                >
                  <Plus className="w-4 h-4" />
                  Create Admin User
                </button>
              )}
            </div>

            <div className="border border-gray-200 rounded-lg overflow-hidden">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                      Username
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                      Email
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                      Role
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                      Last Login
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                      Status
                    </th>
                    {isSuperAdmin && (
                      <th className="px-4 py-3 text-right text-xs font-medium text-gray-600">
                        Actions
                      </th>
                    )}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {adminUsers
                    .filter(user => !selectedRole || user.roleId === selectedRole)
                    .map((user) => (
                      <tr key={user.id} className="hover:bg-gray-50">
                        <td className="px-4 py-3 text-sm font-medium text-gray-900">
                          {user.username}
                        </td>
                        <td className="px-4 py-3 text-sm text-gray-600">
                          {user.email}
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-xs px-2 py-1 bg-purple-100 text-purple-700 rounded">
                            {roles.find(r => r.id === user.roleId)?.name}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-sm text-gray-600">
                          {user.lastLogin || 'Never'}
                        </td>
                        <td className="px-4 py-3">
                          <span className={`text-xs px-2 py-1 rounded ${
                            user.status === 'active'
                              ? 'bg-green-100 text-green-700'
                              : 'bg-gray-100 text-gray-700'
                          }`}>
                            {user.status}
                          </span>
                        </td>
                        {isSuperAdmin && (
                          <td className="px-4 py-3 text-right">
                            <button
                              onClick={() => handleResetPassword(user)}
                              className="text-xs text-purple-600 hover:text-purple-700 font-medium"
                            >
                              Reset Password
                            </button>
                          </td>
                        )}
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {/* Create/Edit Role Modal */}
      {(showCreateModal || editingRole) && (
        <CreateEditRoleModal
          role={editingRole}
          onClose={() => {
            setShowCreateModal(false);
            setEditingRole(null);
          }}
          onSave={(role) => {
            if (editingRole) {
              // Update existing role
              setRoles(roles.map(r => r.id === role.id ? role : r));
              toast.success('Role updated', {
                description: 'Changes logged to audit trail'
              });
            } else {
              // Create new role
              setRoles([...roles, role]);
              toast.success('Role created', {
                description: 'Action logged to audit trail'
              });
            }
            setShowCreateModal(false);
            setEditingRole(null);
          }}
        />
      )}

      {/* Delete Role Confirmation Modal */}
      {showDeleteModal && (
        <DeleteRoleModal
          role={showDeleteModal}
          onClose={() => setShowDeleteModal(null)}
          onConfirm={confirmDeleteRole}
        />
      )}

      {/* Password Reset Modal */}
      {showPasswordResetModal && (
        <PasswordResetModal
          user={showPasswordResetModal}
          onClose={() => setShowPasswordResetModal(null)}
        />
      )}

      {/* Create Admin User Modal */}
      {showAdminUserModal && (
        <CreateAdminUserModal
          roles={roles}
          onClose={() => setShowAdminUserModal(false)}
          onSave={(newUser) => {
            setAdminUsers([...adminUsers, newUser]);
            setShowAdminUserModal(false);
            toast.success('Admin user created', {
              description: 'Temporary password generated'
            });
          }}
        />
      )}
    </div>
  );
}

// Helper function
function formatModuleName(module: string): string {
  const names: Record<string, string> = {
    dashboard: 'Dashboard',
    vendors: 'Vendors',
    customers: 'Customers',
    finance: 'Finance & Billing',
    subscriptions: 'Subscriptions',
    reviews: 'Reviews & Moderation',
    system: 'System Settings',
    audit: 'Audit Log',
    adminUsers: 'Admin User Management'
  };
  return names[module] || module;
}

// Create/Edit Role Modal Component
interface CreateEditRoleModalProps {
  role: Role | null;
  onClose: () => void;
  onSave: (role: Role) => void;
}

function CreateEditRoleModal({ role, onClose, onSave }: CreateEditRoleModalProps) {
  const [formData, setFormData] = useState<{
    name: string;
    description: string;
    permissions: Permission;
  }>({
    name: role?.name || '',
    description: role?.description || '',
    permissions: role?.permissions || {
      dashboard: { view: false, edit: false },
      vendors: { view: false, edit: false, special: [] },
      customers: { view: false, edit: false, special: [] },
      finance: { view: false, edit: false, special: [] },
      subscriptions: { view: false, edit: false, special: [] },
      reviews: { view: false, edit: false, special: [] },
      system: { view: false, edit: false },
      audit: { view: false, edit: false, special: [] },
      adminUsers: { view: false, edit: false, special: [] }
    }
  });

  const handlePermissionToggle = (module: keyof Permission, type: 'view' | 'edit') => {
    setFormData({
      ...formData,
      permissions: {
        ...formData.permissions,
        [module]: {
          ...formData.permissions[module],
          [type]: !formData.permissions[module][type]
        }
      }
    });
  };

  const handleSpecialPermissionToggle = (module: keyof Permission, specialPerm: string) => {
    const currentSpecial = formData.permissions[module].special || [];
    const newSpecial = currentSpecial.includes(specialPerm)
      ? currentSpecial.filter(p => p !== specialPerm)
      : [...currentSpecial, specialPerm];

    setFormData({
      ...formData,
      permissions: {
        ...formData.permissions,
        [module]: {
          ...formData.permissions[module],
          special: newSpecial
        }
      }
    });
  };

  const handleSave = () => {
    if (!formData.name.trim()) {
      toast.error('Role name is required');
      return;
    }

    const newRole: Role = {
      id: role?.id || `role_${Date.now()}`,
      name: formData.name,
      description: formData.description,
      userCount: role?.userCount || 0,
      isSystem: false,
      permissions: formData.permissions
    };

    // Audit log
    console.log('AUDIT LOG: Role saved', {
      roleId: newRole.id,
      roleName: newRole.name,
      action: role ? 'EDIT' : 'CREATE',
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString(),
      permissionsDiff: role ? {
        before: role.permissions,
        after: newRole.permissions
      } : undefined
    });

    onSave(newRole);
  };

  const specialPermissionsByModule: Record<string, string[]> = {
    vendors: ['suspend', 'approve'],
    customers: ['gdpr-export', 'gdpr-delete'],
    finance: ['export', 'vat-report'],
    subscriptions: ['create-plan'],
    reviews: ['moderate', 'delete'],
    audit: ['export'],
    adminUsers: ['create', 'reset-password', 'assign-role']
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
          <h2 className="text-xl font-semibold">
            {role ? 'Edit Role' : 'Create Custom Role'}
          </h2>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 space-y-6">
          {/* Role Information */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
              Role Information
            </h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-2">
                  Role Name <span className="text-red-500">*</span>
                </label>
                <Input
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="e.g., Operations Manager"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">
                  Description (Internal)
                </label>
                <textarea
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="Describe the purpose and scope of this role"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 text-sm resize-none"
                  rows={2}
                />
              </div>
            </div>
          </div>

          {/* Permission Matrix */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
              Permission Assignment
            </h3>
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
              <div className="flex items-start gap-2">
                <Info className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                <p className="text-xs text-blue-800">
                  Permissions are opt-in. No permission is selected by default. 
                  Select only what is necessary for this role (Principle of Least Privilege).
                </p>
              </div>
            </div>

            <div className="border border-gray-200 rounded-lg overflow-hidden">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                      Module
                    </th>
                    <th className="px-4 py-3 text-center text-xs font-medium text-gray-600">
                      View
                    </th>
                    <th className="px-4 py-3 text-center text-xs font-medium text-gray-600">
                      Edit
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">
                      Special Permissions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {Object.entries(formData.permissions).map(([module, perms]) => (
                    <tr key={module} className="hover:bg-gray-50">
                      <td className="px-4 py-3 text-sm font-medium text-gray-900">
                        {formatModuleName(module)}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <input
                          type="checkbox"
                          checked={perms.view}
                          onChange={() => handlePermissionToggle(module as keyof Permission, 'view')}
                          className="rounded text-purple-600"
                        />
                      </td>
                      <td className="px-4 py-3 text-center">
                        <input
                          type="checkbox"
                          checked={perms.edit}
                          onChange={() => handlePermissionToggle(module as keyof Permission, 'edit')}
                          className="rounded text-purple-600"
                        />
                      </td>
                      <td className="px-4 py-3">
                        {specialPermissionsByModule[module] ? (
                          <div className="flex flex-wrap gap-2">
                            {specialPermissionsByModule[module].map(sp => (
                              <label key={sp} className="flex items-center gap-1 text-xs">
                                <input
                                  type="checkbox"
                                  checked={perms.special?.includes(sp)}
                                  onChange={() => handleSpecialPermissionToggle(module as keyof Permission, sp)}
                                  className="rounded text-purple-600"
                                />
                                <span>{sp}</span>
                              </label>
                            ))}
                          </div>
                        ) : (
                          <span className="text-xs text-gray-400">-</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex items-center justify-between">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            className="px-4 py-2 text-sm font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-700"
          >
            {role ? 'Update Role' : 'Create Role'}
          </button>
        </div>
      </div>
    </div>
  );
}

// Delete Role Confirmation Modal
interface DeleteRoleModalProps {
  role: Role;
  onClose: () => void;
  onConfirm: () => void;
}

function DeleteRoleModal({ role, onClose, onConfirm }: DeleteRoleModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-md w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
            <AlertTriangle className="w-6 h-6 text-red-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">Delete Role</h3>
            <p className="text-sm text-gray-600">This action cannot be undone</p>
          </div>
        </div>

        <p className="text-sm text-gray-700 mb-4">
          Are you sure you want to delete the <strong>{role.name}</strong> role?
        </p>

        <div className="flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            className="flex-1 px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700"
          >
            Delete Role
          </button>
        </div>
      </div>
    </div>
  );
}

// Password Reset Modal
interface PasswordResetModalProps {
  user: AdminUser;
  onClose: () => void;
}

function PasswordResetModal({ user, onClose }: PasswordResetModalProps) {
  const [tempPassword] = useState(() => {
    // Generate temporary password
    return 'Tavlo' + Math.random().toString(36).slice(2, 10) + '@2025';
  });
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    navigator.clipboard.writeText(tempPassword);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);

    toast.success('Password copied to clipboard');
  };

  const handleConfirm = () => {
    // Audit log
    console.log('AUDIT LOG: Password reset', {
      userId: user.id,
      username: user.username,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString(),
      tempPasswordProvided: true
    });

    toast.success('Password reset complete', {
      description: 'User must change password on next login'
    });

    onClose();
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-md w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <Key className="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">Reset Password</h3>
            <p className="text-sm text-gray-600">{user.username}</p>
          </div>
        </div>

        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
          <div className="flex items-start gap-2">
            <AlertTriangle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
            <p className="text-xs text-amber-800">
              <strong>Security Notice:</strong> This password will only be shown once. 
              Provide it securely to the admin user. They must change it on first login.
            </p>
          </div>
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium mb-2">Temporary Password</label>
          <div className="flex gap-2">
            <div className="flex-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg font-mono text-sm">
              {tempPassword}
            </div>
            <button
              onClick={handleCopy}
              className="px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center gap-2"
            >
              {copied ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
            </button>
          </div>
        </div>

        <div className="flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            onClick={handleConfirm}
            className="flex-1 px-4 py-2 text-sm font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-700"
          >
            Confirm Reset
          </button>
        </div>
      </div>
    </div>
  );
}

// Create Admin User Modal
interface CreateAdminUserModalProps {
  roles: Role[];
  onClose: () => void;
  onSave: (newUser: AdminUser) => void;
}

function CreateAdminUserModal({ roles, onClose, onSave }: CreateAdminUserModalProps) {
  const [formData, setFormData] = useState<{
    username: string;
    email: string;
    roleId: string;
    status: 'active' | 'disabled';
  }>({
    username: '',
    email: '',
    roleId: roles[0].id,
    status: 'active'
  });

  const handleSave = () => {
    if (!formData.username.trim()) {
      toast.error('Username is required');
      return;
    }

    if (!formData.email.trim()) {
      toast.error('Email is required');
      return;
    }

    const newUser: AdminUser = {
      id: `user_${Date.now()}`,
      username: formData.username,
      email: formData.email,
      roleId: formData.roleId,
      status: formData.status,
      createdAt: new Date().toISOString(),
      lastLogin: null
    };

    // Audit log
    console.log('AUDIT LOG: Admin user created', {
      userId: newUser.id,
      username: newUser.username,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString(),
      tempPasswordProvided: true
    });

    onSave(newUser);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-md w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <Users className="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">Create Admin User</h3>
          </div>
        </div>

        <div className="p-6 space-y-6">
          {/* User Information */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
              User Information
            </h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-2">
                  Username <span className="text-red-500">*</span>
                </label>
                <Input
                  value={formData.username}
                  onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                  placeholder="e.g., admin.john"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">
                  Email <span className="text-red-500">*</span>
                </label>
                <Input
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  placeholder="e.g., john@tavlo.com"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">
                  Role <span className="text-red-500">*</span>
                </label>
                <select
                  value={formData.roleId}
                  onChange={(e) => setFormData({ ...formData, roleId: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 text-sm"
                >
                  {roles.map(role => (
                    <option key={role.id} value={role.id}>
                      {role.name}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">
                  Status <span className="text-red-500">*</span>
                </label>
                <select
                  value={formData.status}
                  onChange={(e) => setFormData({ ...formData, status: e.target.value as 'active' | 'disabled' })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 text-sm"
                >
                  <option value="active">Active</option>
                  <option value="disabled">Disabled</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div className="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex items-center justify-between">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            className="px-4 py-2 text-sm font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-700"
          >
            Create User
          </button>
        </div>
      </div>
    </div>
  );
}