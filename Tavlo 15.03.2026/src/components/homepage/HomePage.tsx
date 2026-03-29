import { useState } from 'react';
import { Header } from './Header';
import { PromoBanner } from './PromoBanner';
import { RestaurantGrid } from './RestaurantGrid';
import { RestaurantMapView } from './RestaurantMapView';
import { SimplifiedFilters, FilterState } from './SimplifiedFilters';
import { Footer } from './Footer';
import { usePlatformLanguage } from '../../contexts/PlatformLanguageContext';
import { getPlatformTranslation } from '../../utils/platformTranslations';
import { Map, Grid3x3 } from 'lucide-react';
import { Button } from '../ui/button';

interface HomePageProps {
  user: any;
  onLoginClick: () => void;
  onRegisterClick: () => void;
  onProfileClick: () => void;
  onRestaurantClick: (restaurantId: string) => void;
  onBackToPlatform?: () => void; // Navigate back to platform marketing page
}

// Mock data
const MOCK_RESTAURANTS = [
  {
    id: 'rest_1',
    name: 'Bella Italia',
    cuisine: 'Italian',
    rating: 4.8,
    reviewCount: 234,
    distance: '0.5 km',
    priceLevel: 2,
    avgMainPrice: '€12–18',
    image: 'https://images.unsplash.com/photo-1662197480393-2a82030b7b83?w=400',
    isFavorite: false,
    bestFor: ['Best for lunch', 'Date-friendly'],
    features: {
      loyaltyProgram: true,
      takeawayAvailable: true,
      promotionsActive: true,
      verified: true
    },
    isOpen: true,
    updatedRecently: true,
    whyChoose: 'People love the pasta',
    trustLabel: 'Highly rated',
    lat: 48.2082,
    lng: 16.3738
  },
  {
    id: 'rest_2',
    name: 'Sakura Sushi',
    cuisine: 'Japanese',
    rating: 4.9,
    reviewCount: 456,
    distance: '1.2 km',
    priceLevel: 3,
    avgMainPrice: '€18–25',
    image: 'https://images.unsplash.com/photo-1700324822763-956100f79b0d?w=400',
    isFavorite: true,
    bestFor: ['Fast service', 'Best for takeaway'],
    features: {
      loyaltyProgram: true,
      takeawayAvailable: true,
      verified: true
    },
    isOpen: true,
    whyChoose: 'Fresh fish daily',
    trustLabel: 'Popular choice',
    lat: 48.2135,
    lng: 16.3850
  },
  {
    id: 'rest_3',
    name: 'El Taco Loco',
    cuisine: 'Mexican',
    rating: 4.6,
    reviewCount: 189,
    distance: '0.8 km',
    priceLevel: 1,
    avgMainPrice: '€8–12',
    image: 'https://images.unsplash.com/photo-1688845465690-e5ea24774fd5?w=400',
    isFavorite: false,
    bestFor: ['Budget-friendly', 'Fast service'],
    features: {
      takeawayAvailable: true,
      promotionsActive: true
    },
    isOpen: true,
    whyChoose: 'Great value for money',
    lat: 48.2020,
    lng: 16.3680
  },
  {
    id: 'rest_4',
    name: 'The Burger Joint',
    cuisine: 'American',
    rating: 4.7,
    reviewCount: 312,
    distance: '1.5 km',
    priceLevel: 2,
    avgMainPrice: '€10–15',
    image: 'https://images.unsplash.com/photo-1656439659132-24c68e36b553?w=400',
    isFavorite: false,
    bestFor: ['Fast service', 'Best for takeaway'],
    features: {
      takeawayAvailable: true,
      verified: true
    },
    isOpen: true,
    whyChoose: 'Fastest takeaway nearby',
    lat: 48.2160,
    lng: 16.3600
  },
  {
    id: 'rest_5',
    name: 'Pizza Paradise',
    cuisine: 'Italian',
    rating: 4.5,
    reviewCount: 278,
    distance: '2.0 km',
    priceLevel: 1,
    avgMainPrice: '€9–14',
    image: 'https://images.unsplash.com/photo-1563245738-9169ff58eccf?w=400',
    isFavorite: false,
    bestFor: ['Budget-friendly', 'Best for lunch'],
    features: {
      takeawayAvailable: true
    },
    isOpen: false,
    opensAt: '11:00',
    whyChoose: 'Authentic wood-fired pizza',
    lat: 48.1950,
    lng: 16.3800
  },
  {
    id: 'rest_6',
    name: 'Vienna Café',
    cuisine: 'Café',
    rating: 4.4,
    reviewCount: 156,
    distance: '0.3 km',
    priceLevel: 1,
    avgMainPrice: '€6–10',
    image: 'https://images.unsplash.com/photo-1657593088889-5105c637f2a8?w=400',
    isFavorite: false,
    bestFor: ['Best for lunch', 'Budget-friendly'],
    features: {
      takeawayAvailable: true
    },
    isOpen: true,
    whyChoose: 'Cozy atmosphere',
    lat: 48.2100,
    lng: 16.3720
  }
];

const MOCK_PROMOS = [
  {
    id: 'promo_1',
    image: 'https://images.unsplash.com/photo-1662197480393-2a82030b7b83?w=1200',
    title: '50% Off Italian Cuisine',
    description: 'Enjoy authentic Italian dishes with our exclusive weekend offer',
    ctaText: 'See offer',
    ctaAction: () => console.log('Promo 1 clicked')
  },
  {
    id: 'promo_2',
    image: 'https://images.unsplash.com/photo-1700324822763-956100f79b0d?w=1200',
    title: 'New Sushi Menu Available',
    description: 'Fresh sashimi and rolls prepared by our master chefs',
    ctaText: 'Order deal',
    ctaAction: () => console.log('Promo 2 clicked')
  },
  {
    id: 'promo_3',
    image: 'https://images.unsplash.com/photo-1688845465690-e5ea24774fd5?w=1200',
    title: 'Taco Tuesday Special',
    description: 'Buy 2 get 1 free on all tacos every Tuesday',
    ctaText: 'See offer',
    ctaAction: () => console.log('Promo 3 clicked')
  }
];

export function HomePage({
  user,
  onLoginClick,
  onRegisterClick,
  onProfileClick,
  onRestaurantClick,
  onBackToPlatform
}: HomePageProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedFilters, setSelectedFilters] = useState<FilterState>({
    openNow: false,
    minRating: null,
    maxDistance: null,
    takeawayOnly: false,
    moreFilters: {
      priceLevel: [],
      cuisines: [],
      dietary: []
    }
  });
  const [restaurants, setRestaurants] = useState(MOCK_RESTAURANTS);
  const [viewMode, setViewMode] = useState<'grid' | 'map'>('grid');

  const handleSearch = (query: string) => {
    setSearchQuery(query);
    applyAdvancedFilters(query, selectedFilters);
  };

  const handleFilterChange = (filters: FilterState) => {
    setSelectedFilters(filters);
    applyAdvancedFilters(searchQuery, filters);
  };

  const applyAdvancedFilters = (query: string, filters: FilterState) => {
    let filtered = [...MOCK_RESTAURANTS];

    // Apply search query
    if (query) {
      const lowerQuery = query.toLowerCase();
      filtered = filtered.filter(
        r => 
          r.name.toLowerCase().includes(lowerQuery) || 
          r.cuisine.toLowerCase().includes(lowerQuery)
      );
    }

    // Apply open now filter
    if (filters.openNow) {
      filtered = filtered.filter(r => r.isOpen);
    }

    // Apply minimum rating
    if (filters.minRating !== null) {
      filtered = filtered.filter(r => r.rating >= filters.minRating);
    }

    // Apply max distance (mock - in real app would calculate actual distance)
    if (filters.maxDistance !== null) {
      const distanceKm = parseFloat(filtered[0]?.distance || '0');
      filtered = filtered.filter(r => {
        const km = parseFloat(r.distance);
        return km <= filters.maxDistance;
      });
    }

    // Apply takeaway filter
    if (filters.takeawayOnly) {
      filtered = filtered.filter(r => r.features?.takeawayAvailable);
    }

    // Apply price level filter
    if (filters.moreFilters?.priceLevel?.length > 0) {
      filtered = filtered.filter(r => 
        filters.moreFilters.priceLevel.includes(r.priceLevel)
      );
    }

    // Apply cuisine filter
    if (filters.moreFilters?.cuisines?.length > 0) {
      filtered = filtered.filter(r => 
        filters.moreFilters.cuisines.includes(r.cuisine)
      );
    }

    setRestaurants(filtered);
  };

  const handleToggleFavorite = (restaurantId: string) => {
    setRestaurants(prev =>
      prev.map(r =>
        r.id === restaurantId ? { ...r, isFavorite: !r.isFavorite } : r
      )
    );
  };

  const { language } = usePlatformLanguage();

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      <Header
        user={user}
        onLoginClick={onLoginClick}
        onRegisterClick={onRegisterClick}
        onProfileClick={onProfileClick}
        onSearch={handleSearch}
        onLogoClick={onBackToPlatform}
      />

      {/* Back to Platform Button */}
      {onBackToPlatform && (
        <div className="bg-white border-b border-gray-100">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 py-3">
            <button
              onClick={onBackToPlatform}
              className="text-sm text-gray-600 hover:text-emerald-700 transition-colors inline-flex items-center gap-2 group"
            >
              <svg className="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              <span>{getPlatformTranslation('back_to_home', language)}</span>
            </button>
          </div>
        </div>
      )}

      {/* Simplified Filters - MAX 4 VISIBLE */}
      <SimplifiedFilters onFilterChange={handleFilterChange} />

      <main className="flex-1">
        <PromoBanner promos={MOCK_PROMOS} />
        
        {/* View Toggle Button */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 mb-4">
          <div className="flex items-center justify-between">
            <p className="text-sm text-gray-600">
              {restaurants.length} {restaurants.length === 1 ? 'restaurant' : 'restaurants'} found
            </p>
            <div className="flex gap-2">
              <Button
                onClick={() => setViewMode('grid')}
                variant={viewMode === 'grid' ? 'default' : 'outline'}
                size="sm"
                className="gap-2"
              >
                <Grid3x3 className="w-4 h-4" />
                Grid
              </Button>
              <Button
                onClick={() => setViewMode('map')}
                variant={viewMode === 'map' ? 'default' : 'outline'}
                size="sm"
                className="gap-2"
              >
                <Map className="w-4 h-4" />
                Map
              </Button>
            </div>
          </div>
        </div>

        {viewMode === 'grid' ? (
          <RestaurantGrid
            restaurants={restaurants}
            onRestaurantClick={onRestaurantClick}
            onToggleFavorite={handleToggleFavorite}
          />
        ) : (
          <div className="max-w-7xl mx-auto px-4 sm:px-6 mb-6">
            <RestaurantMapView
              restaurants={restaurants}
              onRestaurantClick={onRestaurantClick}
            />
          </div>
        )}
      </main>

      <Footer />
    </div>
  );
}