import { useState, useEffect } from 'react';
import { ArrowLeft, Star, MapPin, Clock, Phone, Globe, Award, ShoppingBag, Users, QrCode } from 'lucide-react';
import { Button } from '../ui/button';
import { MenuSection } from './MenuSection';
import { ReviewsSection } from './ReviewsSection';
import { LocationSection } from './LocationSection';
import { AboutSection } from './AboutSection';
import { TakeawayModal } from './TakeawayModal';
import { TakeawayGuestModal } from './TakeawayGuestModal';
import { ReservationModal } from '../shared/ReservationModal';
import { ReviewModal } from '../ReviewModal';
import { toast } from 'sonner@2.0.3';
import { api } from '../../utils/api';
import { RestaurantHeader } from './RestaurantHeader';
import { OrderingOptions } from './OrderingOptions';

type Tab = 'order' | 'menu' | 'reviews' | 'location' | 'about';

interface RestaurantPageProps {
  restaurantId: string;
  user: any;
  onBack: () => void;
  onScanQR: () => void;
  onLoginRequired: () => void;
  onTakeawayConfirm?: (guestData: any, pickupData: any) => void;
  takeawayOrder?: {
    guestData: { name: string; phone?: string; email?: string } | null;
    pickupData: { pickupTime: string; scheduledFor: 'asap' | 'scheduled'; displayTime: string } | null;
  } | null;
}

// Mock restaurant data
const MOCK_RESTAURANT = {
  id: 'rest_1',
  name: 'Bella Italia',
  cuisine: 'Italian Fine Dining',
  rating: 4.8,
  reviewCount: 234,
  priceLevel: 2,
  address: 'Stephansplatz 12, 1010 Vienna, Austria',
  phone: '+43 1 234 5678',
  hours: 'Mon-Thu: 11:00 - 22:00\nFri-Sat: 11:00 - 23:00\nSun: 12:00 - 21:00',
  coverImage: 'https://images.unsplash.com/photo-1662197480393-2a82030b7b83?w=1200',
  description: 'Experience authentic Italian cuisine in the heart of Vienna. Our chefs prepare traditional dishes using the finest ingredients imported directly from Italy.',
  coordinates: { lat: 48.2082, lng: 16.3738 },
  openingHours: {
    monday: { open: '11:00', close: '22:00' },
    tuesday: { open: '11:00', close: '22:00' },
    wednesday: { open: '11:00', close: '22:00' },
    thursday: { open: '11:00', close: '22:00' },
    friday: { open: '11:00', close: '23:00' },
    saturday: { open: '11:00', close: '23:00' },
    sunday: { open: '12:00', close: '21:00' }
  },
  features: {
    loyaltyProgram: true,
    loyaltyRate: '5 pts / €1',
    takeawayAvailable: true,
    fastDelivery: false,
    promotionsActive: true,
    promotionEndsAt: '2h',
    verified: true,
    acceptsCards: true,
    freeDelivery: false
  },
  website: 'https://bellaitalia.at',
  vatNumber: 'ATU12345678'
};

const MOCK_MENU = [
  {
    id: 'item_1',
    name: 'Pasta Carbonara',
    description: 'Classic Roman pasta with eggs, pecorino, guanciale, and black pepper',
    price: 14.50,
    category: 'Mains',
    image: 'https://images.unsplash.com/photo-1662197480393-2a82030b7b83?w=400'
  },
  {
    id: 'item_2',
    name: 'Margherita Pizza',
    description: 'San Marzano tomatoes, mozzarella di bufala, fresh basil, olive oil',
    price: 12.00,
    category: 'Mains',
    image: 'https://images.unsplash.com/photo-1563245738-9169ff58eccf?w=400'
  },
  {
    id: 'item_3',
    name: 'Caesar Salad',
    description: 'Romaine lettuce, parmesan, croutons, Caesar dressing',
    price: 9.50,
    category: 'Starters',
    image: 'https://images.unsplash.com/photo-1651352650142-385087834d9d?w=400'
  },
  {
    id: 'item_4',
    name: 'Grilled Ribeye Steak',
    description: '300g premium ribeye with herb butter and roasted vegetables',
    price: 28.00,
    category: 'Mains',
    image: 'https://images.unsplash.com/photo-1657143378324-83c1609f32a8?w=400'
  },
  {
    id: 'item_5',
    name: 'Tiramisu',
    description: 'Classic Italian dessert with espresso and mascarpone',
    price: 7.50,
    category: 'Desserts',
    image: 'https://images.unsplash.com/photo-1607257882338-70f7dd2ae344?w=400'
  },
  {
    id: 'item_6',
    name: 'Aperol Spritz',
    description: 'Refreshing Italian cocktail with prosecco and Aperol',
    price: 8.50,
    category: 'Drinks',
    image: 'https://images.unsplash.com/photo-1650691960684-c15e3e2d5c85?w=400'
  }
];

const MOCK_REVIEWS = [
  {
    id: 'review_1',
    customerName: 'Maria Schmidt',
    rating: 5,
    text: 'Absolutely wonderful! The pasta was cooked to perfection and the service was excellent. Will definitely come back.',
    date: '2 days ago',
    verified: true,
    orderType: 'dine-in' as const,
    helpful: 12
  },
  {
    id: 'review_2',
    customerName: 'John Williams',
    rating: 4,
    text: 'Great atmosphere and delicious food. The pizza was authentic and the tiramisu was heavenly.',
    date: '1 week ago',
    verified: true,
    orderType: 'takeaway' as const,
    helpful: 8
  },
  {
    id: 'review_3',
    customerName: 'Anna Müller',
    rating: 5,
    text: 'Best Italian restaurant in Vienna! The staff is friendly and the food quality is consistently high.',
    date: '2 weeks ago',
    verified: true,
    orderType: 'dine-in' as const,
    helpful: 3
  }
];

export function RestaurantPage({ 
  restaurantId, 
  user,
  onBack, 
  onScanQR,
  onLoginRequired,
  onTakeawayConfirm,
  takeawayOrder: existingTakeawayOrder
}: RestaurantPageProps) {
  const [activeTab, setActiveTab] = useState<Tab>('order');
  const [showReservationModal, setShowReservationModal] = useState(false);
  const [showTakeawayGuestModal, setShowTakeawayGuestModal] = useState(false);
  const [showTakeawayModal, setShowTakeawayModal] = useState(false);
  const [takeawayGuestData, setTakeawayGuestData] = useState<any>(null);
  const [showReviewModal, setShowReviewModal] = useState(false);
  
  // Backend data states
  const [loading, setLoading] = useState(true);
  const [restaurantData, setRestaurantData] = useState<any>(null);
  const [settings, setSettings] = useState<any>(null);
  const [menu, setMenu] = useState<any>(null);

  const tabs = [
    { id: 'order' as Tab, label: 'Order' },
    { id: 'menu' as Tab, label: 'Menu' },
    { id: 'reviews' as Tab, label: 'Reviews' },
    { id: 'location' as Tab, label: 'Location' },
    { id: 'about' as Tab, label: 'About' }
  ];
  
  // Load restaurant data from backend
  useEffect(() => {
    const loadData = async () => {
      try {
        setLoading(true);
        console.log('🔄 Loading restaurant data for:', restaurantId);
        
        // Fetch all data in parallel
        const [restaurantRes, settingsRes, menuRes] = await Promise.all([
          api.getRestaurant(restaurantId),
          api.getVendorSettings(restaurantId),
          api.getMenu(restaurantId)
        ]);
        
        console.log('✅ Restaurant data loaded:', { restaurantRes, settingsRes, menuRes });
        
        setRestaurantData(restaurantRes);
        setSettings(settingsRes);
        setMenu(menuRes);
      } catch (error) {
        console.error('❌ Failed to load restaurant data:', error);
        toast.error('Failed to load restaurant data');
      } finally {
        setLoading(false);
      }
    };
    
    loadData();
  }, [restaurantId]);

  const handleToggleFavorite = () => {
    toast.success('Added to favorites!');
  };

  const handleTakeaway = () => {
    // If user is logged in, skip guest modal and go directly to time selection
    if (user) {
      // Pre-fill guest data with user info
      setTakeawayGuestData({
        name: user.name || user.email?.split('@')[0] || 'Guest',
        phone: user.phone || '',
        email: user.email || ''
      });
      setShowTakeawayModal(true);
    } else {
      // Show guest modal for non-logged-in users
      setShowTakeawayGuestModal(true);
    }
  };

  const handleReserveTable = () => {
    if (!user) {
      onLoginRequired();
      return;
    }
    setShowReservationModal(true);
  };

  const handleAddReview = () => {
    if (!user) {
      onLoginRequired();
      return;
    }
    // Open review modal - user can write review
    // Note: In production, you'd check if user has ordered from this restaurant
    setShowReviewModal(true);
  };
  
  const handleReviewSubmit = async (reviewData: any) => {
    try {
      console.log('Review submitted for restaurant:', restaurantId, reviewData);
      // TODO: Send review to backend via API
      // await api.submitReview(restaurantId, user.id, reviewData);
      
      toast.success('Thank you for your review!');
      setShowReviewModal(false);
      // Refresh reviews (would normally call API)
    } catch (error) {
      console.error('Failed to submit review:', error);
      toast.error('Failed to submit review. Please try again.');
    }
  };
  
  // Show loading state
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading restaurant...</p>
        </div>
      </div>
    );
  }
  
  // If data failed to load, show error
  if (!restaurantData || !settings) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-gray-600 mb-4">Failed to load restaurant data</p>
          <Button onClick={onBack}>Go Back</Button>
        </div>
      </div>
    );
  }
  
  // Build features object from settings
  const features = {
    loyaltyProgram: settings.enableLoyalty !== false,
    loyaltyRate: settings.enableLoyalty ? `${settings.pointsPerEuro || 1} pts / €1` : null,
    takeawayAvailable: true, // TODO: Add to settings
    verified: true, // TODO: Add to settings
    acceptsCards: settings.acceptCard || false,
    promotionsActive: false, // TODO: Add promotions system
    promotionEndsAt: null
  };
  
  // Build payment methods array from settings
  const paymentMethods = [];
  if (settings.acceptCard) paymentMethods.push('Card');
  if (settings.acceptApplePay) paymentMethods.push('Apple Pay');
  if (settings.acceptGooglePay) paymentMethods.push('Google Pay');
  if (settings.acceptCash) paymentMethods.push('Cash');
  
  // Calculate prep time display
  const prepTime = settings.estimatedPrepTime || 20;
  const prepTimeDisplay = `${prepTime - 5}-${prepTime} min`;

  return (
    <div className="min-h-screen bg-gray-50">
      <RestaurantHeader
        coverImage={settings.coverPhoto || MOCK_RESTAURANT.coverImage}
        name={settings.restaurantName || restaurantData.name}
        cuisine={restaurantData.cuisineTag || 'Restaurant'}
        rating={MOCK_RESTAURANT.rating}
        reviewCount={MOCK_RESTAURANT.reviewCount}
        priceLevel={MOCK_RESTAURANT.priceLevel}
        address={settings.address || restaurantData.address}
        onBack={onBack}
        onToggleFavorite={handleToggleFavorite}
        openingHours={settings.businessHours}
        features={features}
        onRatingClick={() => setActiveTab('reviews')}
      />

      {/* Tabs Navigation */}
      <div className="bg-white border-b sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6">
          <div className="flex gap-2 overflow-x-auto hide-scrollbar">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`px-4 py-4 text-sm whitespace-nowrap border-b-2 transition-colors ${
                  activeTab === tab.id
                    ? 'border-orange-500 text-orange-600'
                    : 'border-transparent text-gray-600 hover:text-gray-900'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Tab Content */}
      <div className="pb-12">
        {activeTab === 'order' && (
          <OrderingOptions
            onScanQR={onScanQR}
            onTakeaway={handleTakeaway}
            onReserveTable={handleReserveTable}
            restaurantStatus={{
              isOpen: true,
              isBusy: false,
              slowPrep: true,
              takeawayLimited: false,
              avgPrepTime: prepTimeDisplay
            }}
          />
        )}

        {activeTab === 'menu' && (
          <MenuSection 
            menu={menu?.items || MOCK_MENU} 
            currency={settings.currency || '€'}
            browseMode={true}
            onOrderClick={() => setActiveTab('order')}
          />
        )}

        {activeTab === 'reviews' && (
          <ReviewsSection
            reviews={MOCK_REVIEWS}
            averageRating={MOCK_RESTAURANT.rating}
            totalReviews={MOCK_RESTAURANT.reviewCount}
            isLoggedIn={!!user}
            onAddReview={handleAddReview}
          />
        )}

        {activeTab === 'location' && (
          <LocationSection
            address={settings.address || restaurantData.address}
            phone={settings.phone || restaurantData.phone}
            hours={MOCK_RESTAURANT.hours}
            coordinates={MOCK_RESTAURANT.coordinates}
          />
        )}

        {activeTab === 'about' && (
          <AboutSection
            name={settings.restaurantName || restaurantData.name}
            description={settings.description || MOCK_RESTAURANT.description}
            features={features}
            website={settings.website || MOCK_RESTAURANT.website}
            vatNumber={settings.vatNumber || MOCK_RESTAURANT.vatNumber}
            paymentMethods={paymentMethods}
            reviewCount={MOCK_RESTAURANT.reviewCount}
            yearsExperience={10}
          />
        )}
      </div>

      {/* Sticky CTA - Visible on ALL tabs */}
      {activeTab !== 'order' && (
        <div className="fixed bottom-0 left-0 right-0 bg-white border-t shadow-2xl z-40">
          <div className="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
            <div className="flex-1 min-w-0">
              <div className="font-medium text-gray-900">{settings.restaurantName || restaurantData.name}</div>
              <div className="text-sm text-gray-600 truncate">Ready to order? Start now</div>
            </div>
            <Button 
              size="lg" 
              onClick={() => setActiveTab('order')}
              className="shrink-0"
            >
              <QrCode className="w-5 h-5 mr-2" />
              Order Now
            </Button>
          </div>
        </div>
      )}

      {/* Reservation Modal */}
      <ReservationModal
        isOpen={showReservationModal}
        onClose={() => setShowReservationModal(false)}
        restaurantName={settings.restaurantName || restaurantData.name}
        restaurantId={restaurantId}
        user={user}
      />

      {/* Takeaway Guest/Login Modal */}
      <TakeawayGuestModal
        isOpen={showTakeawayGuestModal}
        onClose={() => setShowTakeawayGuestModal(false)}
        restaurantName={settings.restaurantName || restaurantData.name}
        onContinueAsGuest={(guestData) => {
          setTakeawayGuestData(guestData);
          setShowTakeawayGuestModal(false);
          setShowTakeawayModal(true);
        }}
        onLogin={() => {
          setShowTakeawayGuestModal(false);
          onLoginRequired();
        }}
        onRegister={() => {
          setShowTakeawayGuestModal(false);
          onLoginRequired(); // Will show registration
        }}
      />

      {/* Takeaway Time Selection Modal */}
      <TakeawayModal
        isOpen={showTakeawayModal}
        onClose={() => {
          setShowTakeawayModal(false);
          setTakeawayGuestData(null);
        }}
        restaurantName={settings?.restaurantName || restaurantData?.name || ''}
        restaurantId={restaurantId}
        pickupInstructions={settings?.pickupInstructions}
        onConfirm={(pickupData) => {
          console.log('Takeaway selected:', pickupData);
          console.log('Guest data:', takeawayGuestData);
          toast.success(`Pickup time selected: ${pickupData.displayTime}`);
          setShowTakeawayModal(false);
          
          // Call callback to store takeaway data in parent
          if (onTakeawayConfirm && takeawayGuestData) {
            onTakeawayConfirm(takeawayGuestData, pickupData);
          }
          
          // Parent (PlatformApp) will handle navigation to menu via onTakeawayStart callback
        }}
      />

      {/* Review Modal */}
      {showReviewModal && (
        <ReviewModal
          restaurantName={settings.restaurantName || restaurantData.name}
          orderItems={menu?.items?.slice(0, 3) || MOCK_MENU.slice(0, 3)}
          onSubmit={handleReviewSubmit}
          onClose={() => setShowReviewModal(false)}
        />
      )}

      <style>{`
        .hide-scrollbar {
          -ms-overflow-style: none;
          scrollbar-width: none;
        }
        .hide-scrollbar::-webkit-scrollbar {
          display: none;
        }
      `}</style>
    </div>
  );
}