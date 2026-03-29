import { useState } from 'react';
import { Clock, Star, MapPin, Bike, ChevronDown, X, SlidersHorizontal } from 'lucide-react';
import { Button } from '../ui/button';

interface SimplifiedFiltersProps {
  onFilterChange: (filters: FilterState) => void;
}

export interface FilterState {
  openNow: boolean;
  minRating: number | null;
  maxDistance: number | null; // in km
  takeawayOnly: boolean;
  moreFilters: {
    priceLevel: number[]; // [1,2,3]
    cuisines: string[];
    dietary: string[];
  };
}

export function SimplifiedFilters({ onFilterChange }: SimplifiedFiltersProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [openNow, setOpenNow] = useState(false);
  const [within1km, setWithin1km] = useState(false);
  const [fourPlusStars, setFourPlusStars] = useState(false);
  const [takeaway, setTakeaway] = useState(false);
  
  // More filters (hidden by default)
  const [priceLevel, setPriceLevel] = useState<number[]>([]);
  const [cuisines, setCuisines] = useState<string[]>([]);
  const [dietary, setDietary] = useState<string[]>([]);

  const activeFiltersCount = 
    (openNow ? 1 : 0) + 
    (within1km ? 1 : 0) + 
    (fourPlusStars ? 1 : 0) + 
    (takeaway ? 1 : 0) +
    priceLevel.length +
    cuisines.length +
    dietary.length;

  const handleClearAll = () => {
    setOpenNow(false);
    setWithin1km(false);
    setFourPlusStars(false);
    setTakeaway(false);
    setPriceLevel([]);
    setCuisines([]);
    setDietary([]);
    notifyFilterChange(false, false, false, false, [], [], []);
  };

  const notifyFilterChange = (
    open: boolean,
    km: boolean,
    stars: boolean,
    take: boolean,
    price: number[],
    cuis: string[],
    diet: string[]
  ) => {
    onFilterChange({
      openNow: open,
      minRating: stars ? 4 : null,
      maxDistance: km ? 1 : null,
      takeawayOnly: take,
      moreFilters: {
        priceLevel: price,
        cuisines: cuis,
        dietary: diet
      }
    });
  };

  const removeFilter = (filterName: string) => {
    let newOpen = openNow;
    let newKm = within1km;
    let newStars = fourPlusStars;
    let newTake = takeaway;
    let newPrice = [...priceLevel];
    let newCuis = [...cuisines];
    let newDiet = [...dietary];

    switch(filterName) {
      case 'openNow':
        newOpen = false;
        setOpenNow(false);
        break;
      case 'within1km':
        newKm = false;
        setWithin1km(false);
        break;
      case 'fourPlusStars':
        newStars = false;
        setFourPlusStars(false);
        break;
      case 'takeaway':
        newTake = false;
        setTakeaway(false);
        break;
      default:
        // Handle price, cuisine, dietary removals
        if (priceLevel.includes(parseInt(filterName))) {
          newPrice = priceLevel.filter(p => p !== parseInt(filterName));
          setPriceLevel(newPrice);
        }
        if (cuisines.includes(filterName)) {
          newCuis = cuisines.filter(c => c !== filterName);
          setCuisines(newCuis);
        }
        if (dietary.includes(filterName)) {
          newDiet = dietary.filter(d => d !== filterName);
          setDietary(newDiet);
        }
    }

    notifyFilterChange(newOpen, newKm, newStars, newTake, newPrice, newCuis, newDiet);
  };

  const getActiveFilters = () => {
    const filters: Array<{ id: string; label: string }> = [];
    if (openNow) filters.push({ id: 'openNow', label: 'Open now' });
    if (within1km) filters.push({ id: 'within1km', label: 'Within 1 km' });
    if (fourPlusStars) filters.push({ id: 'fourPlusStars', label: '4+ stars' });
    if (takeaway) filters.push({ id: 'takeaway', label: 'Takeaway' });
    priceLevel.forEach(p => {
      const priceLabel = p === 1 ? '~€10' : p === 2 ? '~€20' : '>€30';
      filters.push({ id: p.toString(), label: priceLabel });
    });
    cuisines.forEach(c => filters.push({ id: c, label: c }));
    dietary.forEach(d => filters.push({ id: d, label: d }));
    return filters;
  };

  return (
    <div className="bg-white border-b border-gray-100 sticky top-[73px] z-30">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-3">
        {/* Primary 4 Filters */}
        <div className="flex flex-wrap items-center gap-2">
          <button
            onClick={() => {
              const newVal = !openNow;
              setOpenNow(newVal);
              notifyFilterChange(newVal, within1km, fourPlusStars, takeaway, priceLevel, cuisines, dietary);
            }}
            className={`px-4 py-2 rounded-full text-sm font-medium transition ${
              openNow
                ? 'bg-orange-100 text-orange-700 border-2 border-orange-300'
                : 'bg-gray-100 text-gray-700 border-2 border-transparent hover:border-gray-300'
            }`}
          >
            Open now
          </button>

          <button
            onClick={() => {
              const newVal = !within1km;
              setWithin1km(newVal);
              notifyFilterChange(openNow, newVal, fourPlusStars, takeaway, priceLevel, cuisines, dietary);
            }}
            className={`px-4 py-2 rounded-full text-sm font-medium transition ${
              within1km
                ? 'bg-orange-100 text-orange-700 border-2 border-orange-300'
                : 'bg-gray-100 text-gray-700 border-2 border-transparent hover:border-gray-300'
            }`}
          >
            Within 1 km
          </button>

          <button
            onClick={() => {
              const newVal = !fourPlusStars;
              setFourPlusStars(newVal);
              notifyFilterChange(openNow, within1km, newVal, takeaway, priceLevel, cuisines, dietary);
            }}
            className={`px-4 py-2 rounded-full text-sm font-medium transition ${
              fourPlusStars
                ? 'bg-orange-100 text-orange-700 border-2 border-orange-300'
                : 'bg-gray-100 text-gray-700 border-2 border-transparent hover:border-gray-300'
            }`}
          >
            4+ stars
          </button>

          <button
            onClick={() => {
              const newVal = !takeaway;
              setTakeaway(newVal);
              notifyFilterChange(openNow, within1km, fourPlusStars, newVal, priceLevel, cuisines, dietary);
            }}
            className={`px-4 py-2 rounded-full text-sm font-medium transition ${
              takeaway
                ? 'bg-orange-100 text-orange-700 border-2 border-orange-300'
                : 'bg-gray-100 text-gray-700 border-2 border-transparent hover:border-gray-300'
            }`}
          >
            Takeaway
          </button>

          {/* More Filters Button */}
          <button
            onClick={() => setIsOpen(!isOpen)}
            className="px-4 py-2 rounded-full text-sm font-medium bg-gray-100 text-gray-700 border-2 border-transparent hover:border-gray-300 transition flex items-center gap-1"
          >
            <SlidersHorizontal className="w-4 h-4" />
            More filters
            {activeFiltersCount > 4 && (
              <span className="ml-1 px-1.5 py-0.5 bg-orange-500 text-white text-xs rounded-full">
                {activeFiltersCount - 4}
              </span>
            )}
          </button>

          {/* Clear All - Subtle Text */}
          {activeFiltersCount > 0 && (
            <button
              onClick={handleClearAll}
              className="text-sm text-gray-500 hover:text-gray-700 underline ml-2"
            >
              Clear all ({activeFiltersCount})
            </button>
          )}
        </div>

        {/* Active Filter Chips */}
        {getActiveFilters().length > 0 && (
          <div className="flex flex-wrap gap-2 mt-3">
            {getActiveFilters().map(filter => (
              <div
                key={filter.id}
                className="inline-flex items-center gap-1.5 px-3 py-1 bg-orange-50 text-orange-700 rounded-full text-sm border border-orange-200"
              >
                {filter.label}
                <button
                  onClick={() => removeFilter(filter.id)}
                  className="hover:bg-orange-200 rounded-full p-0.5 transition"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              </div>
            ))}
          </div>
        )}

        {/* More Filters Panel */}
        {isOpen && (
          <div className="border-t pt-4 mt-2 grid grid-cols-1 md:grid-cols-3 gap-6">
            {/* Price Level */}
            <div>
              <h4 className="text-sm font-medium mb-3">Price Range</h4>
              <div className="flex gap-2">
                <button
                  onClick={() => {
                    const newPrices = priceLevel.includes(1)
                      ? priceLevel.filter(p => p !== 1)
                      : [...priceLevel, 1];
                    setPriceLevel(newPrices);
                    notifyFilterChange(openNow, within1km, fourPlusStars, takeaway, newPrices, cuisines, dietary);
                  }}
                  className={`px-4 py-2 rounded-lg border-2 transition ${
                    priceLevel.includes(1)
                      ? 'bg-orange-50 border-orange-500 text-orange-700'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  ~€10
                </button>
                <button
                  onClick={() => {
                    const newPrices = priceLevel.includes(2)
                      ? priceLevel.filter(p => p !== 2)
                      : [...priceLevel, 2];
                    setPriceLevel(newPrices);
                    notifyFilterChange(openNow, within1km, fourPlusStars, takeaway, newPrices, cuisines, dietary);
                  }}
                  className={`px-4 py-2 rounded-lg border-2 transition ${
                    priceLevel.includes(2)
                      ? 'bg-orange-50 border-orange-500 text-orange-700'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  ~€20
                </button>
                <button
                  onClick={() => {
                    const newPrices = priceLevel.includes(3)
                      ? priceLevel.filter(p => p !== 3)
                      : [...priceLevel, 3];
                    setPriceLevel(newPrices);
                    notifyFilterChange(openNow, within1km, fourPlusStars, takeaway, newPrices, cuisines, dietary);
                  }}
                  className={`px-4 py-2 rounded-lg border-2 transition ${
                    priceLevel.includes(3)
                      ? 'bg-orange-50 border-orange-500 text-orange-700'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  {'>'} €30
                </button>
              </div>
            </div>

            {/* Cuisines */}
            <div>
              <h4 className="text-sm font-medium mb-3">Cuisine</h4>
              <div className="flex flex-wrap gap-2">
                {cuisineOptions.map((cuisine) => (
                  <button
                    key={cuisine}
                    onClick={() => {
                      const newCuisines = cuisines.includes(cuisine)
                        ? cuisines.filter(c => c !== cuisine)
                        : [...cuisines, cuisine];
                      setCuisines(newCuisines);
                      notifyFilterChange(openNow, within1km, fourPlusStars, takeaway, priceLevel, newCuisines, dietary);
                    }}
                    className={`px-3 py-1.5 rounded-full text-sm border-2 transition ${
                      cuisines.includes(cuisine)
                        ? 'bg-purple-50 border-purple-500 text-purple-700'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    {cuisine}
                  </button>
                ))}
              </div>
            </div>

            {/* Dietary */}
            <div>
              <h4 className="text-sm font-medium mb-3">Dietary</h4>
              <div className="flex flex-wrap gap-2">
                {dietaryOptions.map((option) => (
                  <button
                    key={option}
                    onClick={() => {
                      const newDietary = dietary.includes(option)
                        ? dietary.filter(d => d !== option)
                        : [...dietary, option];
                      setDietary(newDietary);
                      notifyFilterChange(openNow, within1km, fourPlusStars, takeaway, priceLevel, cuisines, newDietary);
                    }}
                    className={`px-3 py-1.5 rounded-full text-sm border-2 transition ${
                      dietary.includes(option)
                        ? 'bg-green-50 border-green-500 text-green-700'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    {option}
                  </button>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

const cuisineOptions = ['Italian', 'Japanese', 'Chinese', 'Mexican', 'Indian', 'Thai', 'American', 'Mediterranean'];
const dietaryOptions = ['Vegetarian', 'Vegan', 'Gluten-free', 'Halal'];