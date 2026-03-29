import { Mail, Phone, Globe, Building2, MapPin, FileText, Edit, AlertCircle } from 'lucide-react';

interface ContactInfo {
  businessName: string;
  email: string;
  phone: string;
  website?: string;
  vat?: string;
  legalEntityName?: string;
  registeredAddress?: string;
  country: string;
  city: string;
}

interface VendorContactInfoProps {
  contactInfo: ContactInfo;
  onEdit?: () => void;
  canEdit?: boolean;
}

export function VendorContactInfo({ contactInfo, onEdit, canEdit = false }: VendorContactInfoProps) {
  const fields = [
    {
      label: 'Business Name',
      value: contactInfo.businessName,
      icon: Building2,
      required: true
    },
    {
      label: 'Contact Email',
      value: contactInfo.email,
      icon: Mail,
      required: true,
      copyable: true
    },
    {
      label: 'Phone Number',
      value: contactInfo.phone,
      icon: Phone,
      required: true,
      copyable: true
    },
    {
      label: 'Website',
      value: contactInfo.website,
      icon: Globe,
      required: false,
      link: true
    },
    {
      label: 'VAT Number',
      value: contactInfo.vat,
      icon: FileText,
      required: false,
      copyable: true
    },
    {
      label: 'Legal Entity Name',
      value: contactInfo.legalEntityName,
      icon: Building2,
      required: false
    },
    {
      label: 'Registered Address',
      value: contactInfo.registeredAddress,
      icon: MapPin,
      required: true
    },
    {
      label: 'City',
      value: contactInfo.city,
      icon: MapPin,
      required: true
    },
    {
      label: 'Country',
      value: contactInfo.country,
      icon: MapPin,
      required: true
    }
  ];

  const handleCopy = (value: string) => {
    navigator.clipboard.writeText(value);
  };

  return (
    <div className="bg-white border border-gray-200 rounded-lg">
      <div className="p-4 border-b border-gray-200 flex items-center justify-between">
        <h3 className="font-semibold text-gray-900">Contact & Legal Details</h3>
        {canEdit && onEdit && (
          <button
            onClick={onEdit}
            className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-purple-700 hover:bg-purple-50 rounded-lg transition-colors"
          >
            <Edit className="w-4 h-4" />
            Edit
          </button>
        )}
      </div>
      
      <div className="p-4 grid grid-cols-2 gap-4">
        {fields.map((field, index) => {
          const Icon = field.icon;
          const isMissing = field.required && !field.value;
          
          return (
            <div key={index} className={field.label === 'Registered Address' ? 'col-span-2' : ''}>
              <div className="flex items-center gap-1.5 mb-1.5">
                <Icon className="w-3.5 h-3.5 text-gray-500" />
                <span className="text-xs font-medium text-gray-600 uppercase tracking-wide">
                  {field.label}
                  {field.required && <span className="text-red-600 ml-0.5">*</span>}
                </span>
              </div>
              
              {isMissing ? (
                <div className="flex items-center gap-2 text-sm text-orange-600">
                  <AlertCircle className="w-4 h-4" />
                  <span>Missing required field</span>
                </div>
              ) : field.value ? (
                <div className="flex items-center gap-2">
                  {field.link ? (
                    <a
                      href={field.value}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-sm text-purple-600 hover:underline"
                    >
                      {field.value}
                    </a>
                  ) : (
                    <span className="text-sm text-gray-900">{field.value}</span>
                  )}
                  
                  {field.copyable && (
                    <button
                      onClick={() => handleCopy(field.value!)}
                      className="text-gray-400 hover:text-gray-600 transition-colors"
                      title="Copy to clipboard"
                    >
                      <FileText className="w-3.5 h-3.5" />
                    </button>
                  )}
                </div>
              ) : (
                <span className="text-sm text-gray-400">—</span>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
