import { Check } from 'lucide-react';

interface FilterChip {
  id: string;
  label: string;
  icon?: React.ReactNode;
}

interface FilterChipsProps {
  filters: FilterChip[];
  selectedFilters: string[];
  onToggleFilter: (filterId: string) => void;
  className?: string;
}

export function FilterChips({ 
  filters, 
  selectedFilters, 
  onToggleFilter,
  className = ''
}: FilterChipsProps) {
  return (
    <div className={`flex flex-wrap gap-2 ${className}`}>
      {filters.map((filter) => {
        const isSelected = selectedFilters.includes(filter.id);
        return (
          <button
            key={filter.id}
            onClick={() => onToggleFilter(filter.id)}
            className={`
              px-4 py-2 rounded-full text-sm transition-all flex items-center gap-2
              ${isSelected 
                ? 'bg-orange-500 text-white shadow-md' 
                : 'bg-white border border-gray-200 text-gray-700 hover:border-orange-500 hover:text-orange-500'
              }
            `}
          >
            {filter.icon}
            <span>{filter.label}</span>
            {isSelected && <Check className="w-4 h-4" />}
          </button>
        );
      })}
    </div>
  );
}
