import { useState } from 'react';
import { ArrowLeft, LogOut } from 'lucide-react';
import { Button } from '../ui/button';
import { ProfileOverview } from './ProfileOverview';
import { ProfileEditForm } from './ProfileEditForm';
import { LoyaltyPointsCard } from './LoyaltyPointsCard';
import { OrderHistoryList } from './OrderHistoryList';
import { SavedRestaurants } from './SavedRestaurants';
import { UserReviewsList } from './UserReviewsList';
import { CustomerReservations } from '../CustomerReservations';
import { PrivacyData } from './PrivacyData';
import { toast } from 'sonner@2.0.3';
import { api } from '../../utils/api';
import { projectId, publicAnonKey } from '../../utils/supabase/info';

type Tab = 'overview' | 'loyalty' | 'orders' | 'reservations' | 'favorites' | 'reviews' | 'privacy';

interface AccountPageProps {
  user: any;
  onBack: () => void;
  onLogout: () => void;
  onRestaurantClick: (restaurantId: string) => void;
}

// Mock data
const MOCK_LOYALTY_TRANSACTIONS = [
  {
    id: 'trans_1',
    type: 'earned' as const,
    points: 20,
    description: 'Order at Bella Italia',
    date: '2 days ago',
    restaurantId: 'rest_1'
  },
  {
    id: 'trans_2',
    type: 'redeemed' as const,
    points: 150,
    description: 'Redeemed for €7.50 discount',
    date: '2 days ago',
    restaurantId: 'rest_1'
  },
  {
    id: 'trans_3',
    type: 'earned' as const,
    points: 45,
    description: 'Order at Sakura Sushi',
    date: '1 week ago',
    restaurantId: 'rest_2'
  }
];

// Mock per-restaurant loyalty data
const MOCK_RESTAURANT_LOYALTY = [
  {
    restaurantId: 'rest_1',
    restaurantName: 'Bella Italia',
    restaurantLogo: undefined,
    points: 120,
    minimumRedemption: 100,
    redemptionRate: 0.05,
    transactions: [
      {
        id: 'trans_1',
        type: 'earned' as const,
        points: 20,
        description: 'Order #1234',
        date: '2 days ago',
        restaurantId: 'rest_1'
      },
      {
        id: 'trans_2',
        type: 'redeemed' as const,
        points: 150,
        description: 'Redeemed for €7.50 discount',
        date: '1 week ago',
        restaurantId: 'rest_1'
      }
    ]
  },
  {
    restaurantId: 'rest_2',
    restaurantName: 'Sakura Sushi',
    restaurantLogo: undefined,
    points: 85,
    minimumRedemption: 100,
    redemptionRate: 0.05,
    transactions: [
      {
        id: 'trans_3',
        type: 'earned' as const,
        points: 45,
        description: 'Order #5678',
        date: '1 week ago',
        restaurantId: 'rest_2'
      },
      {
        id: 'trans_4',
        type: 'earned' as const,
        points: 40,
        description: 'Order #5679',
        date: '2 weeks ago',
        restaurantId: 'rest_2'
      }
    ]
  },
  {
    restaurantId: 'rest_3',
    restaurantName: 'Burger Palace',
    restaurantLogo: undefined,
    points: 200,
    minimumRedemption: 100,
    redemptionRate: 0.05,
    transactions: [
      {
        id: 'trans_5',
        type: 'earned' as const,
        points: 100,
        description: 'Order #9101',
        date: '3 days ago',
        restaurantId: 'rest_3'
      },
      {
        id: 'trans_6',
        type: 'earned' as const,
        points: 100,
        description: 'Order #9102',
        date: '1 week ago',
        restaurantId: 'rest_3'
      }
    ]
  }
];

const MOCK_ORDERS = [
  {
    id: 'order_1',
    restaurantId: 'rest_1',
    restaurantName: 'Bella Italia',
    restaurantLogo: undefined,
    date: 'Dec 10, 2024',
    amount: 42.50,
    items: [
      { name: 'Pasta Carbonara', quantity: 2, price: 15.00 },
      { name: 'Tiramisu', quantity: 1, price: 8.50 }
    ],
    status: 'completed',
    subtotal: 35.00,
    serviceFee: 3.50,
    vatAmount: 4.00,
    vatPercent: 13,
    tip: 0,
    orderNumber: '1234',
    numPeople: 2,
    orderType: 'dine-in' as const,
    tableNumber: '12',
    reviews: [{ rating: 5, comment: 'Excellent pasta! Best carbonara in town.' }]
  },
  {
    id: 'order_2',
    restaurantId: 'rest_2',
    restaurantName: 'Sakura Sushi',
    restaurantLogo: undefined,
    date: 'Dec 5, 2024',
    amount: 68.00,
    items: [
      { name: 'Sushi Platter', quantity: 1, price: 45.00 },
      { name: 'Miso Soup', quantity: 2, price: 6.00 }
    ],
    status: 'completed',
    subtotal: 57.00,
    serviceFee: 5.70,
    vatAmount: 5.30,
    vatPercent: 10,
    tip: 0,
    orderNumber: '5678',
    numPeople: 2,
    orderType: 'dine-in' as const,
    tableNumber: '5'
  },
  {
    id: 'order_3',
    restaurantId: 'rest_1',
    restaurantName: 'Bella Italia',
    restaurantLogo: undefined,
    date: 'Nov 28, 2024',
    amount: 35.00,
    items: [
      { name: 'Margherita Pizza', quantity: 1, price: 12.00 },
      { name: 'Caesar Salad', quantity: 1, price: 9.50 }
    ],
    status: 'completed',
    subtotal: 28.00,
    serviceFee: 2.80,
    vatAmount: 4.20,
    vatPercent: 13,
    tip: 0,
    orderNumber: '1235',
    numPeople: 1,
    orderType: 'dine-in' as const,
    tableNumber: '8'
  },
  {
    id: 'order_4',
    restaurantId: 'rest_3',
    restaurantName: 'Burger Palace',
    restaurantLogo: undefined,
    date: 'Nov 20, 2024',
    amount: 28.50,
    items: [
      { name: 'Classic Burger', quantity: 2, price: 10.00 },
      { name: 'Fries', quantity: 2, price: 3.50 }
    ],
    status: 'completed',
    subtotal: 27.00,
    serviceFee: 0,
    vatAmount: 1.50,
    vatPercent: 10,
    tip: 0,
    orderNumber: '9101',
    numPeople: 2,
    orderType: 'takeaway' as const
  },
  {
    id: 'order_5',
    restaurantId: 'rest_2',
    restaurantName: 'Sakura Sushi',
    restaurantLogo: undefined,
    date: 'Nov 15, 2024',
    amount: 52.00,
    items: [
      { name: 'Dragon Roll', quantity: 2, price: 18.00 },
      { name: 'Edamame', quantity: 1, price: 5.50 }
    ],
    status: 'completed',
    subtotal: 41.50,
    serviceFee: 4.15,
    vatAmount: 6.35,
    vatPercent: 10,
    tip: 0,
    orderNumber: '5679',
    numPeople: 2,
    orderType: 'dine-in' as const,
    tableNumber: '3'
  },
  {
    id: 'order_6',
    restaurantId: 'rest_1',
    restaurantName: 'Bella Italia',
    restaurantLogo: undefined,
    date: 'Nov 10, 2024',
    amount: 47.00,
    items: [
      { name: 'Lasagna', quantity: 1, price: 14.00 },
      { name: 'Bruschetta', quantity: 1, price: 7.50 },
      { name: 'Tiramisu', quantity: 1, price: 8.50 }
    ],
    status: 'completed',
    subtotal: 30.00,
    serviceFee: 3.00,
    vatAmount: 14.00,
    vatPercent: 13,
    tip: 0,
    orderNumber: '1236',
    numPeople: 1,
    orderType: 'dine-in' as const,
    tableNumber: '15'
  }
];

const MOCK_RESERVATIONS = [
  {
    id: 'res_1',
    restaurantName: 'Bella Italia',
    date: 'Dec 15, 2024',
    time: '19:00',
    guests: 2,
    status: 'confirmed'
  },
  {
    id: 'res_2',
    restaurantName: 'Sakura Sushi',
    date: 'Dec 20, 2024',
    time: '20:30',
    guests: 4,
    status: 'confirmed'
  }
];

const MOCK_FAVORITES = [
  {
    id: 'rest_2',
    name: 'Sakura Sushi',
    cuisine: 'Japanese',
    rating: 4.9,
    distance: '1.2 km',
    priceLevel: 3,
    image: 'https://images.unsplash.com/photo-1700324822763-956100f79b0d?w=400'
  }
];

const MOCK_REVIEWS = [
  {
    id: 'review_1',
    restaurantName: 'Bella Italia',
    rating: 5,
    text: 'Amazing pasta! The carbonara was perfectly creamy and the service was excellent. Will definitely come back.',
    date: 'Dec 10, 2024'
  }
];

export function AccountPage({ user, onBack, onLogout, onRestaurantClick }: AccountPageProps) {
  const [activeTab, setActiveTab] = useState<Tab>('overview');
  const [isEditingProfile, setIsEditingProfile] = useState(false);
  const [currentUser, setCurrentUser] = useState(user);

  const tabs = [
    { id: 'overview' as Tab, label: 'Overview' },
    { id: 'loyalty' as Tab, label: 'Loyalty Points' },
    { id: 'orders' as Tab, label: 'Order History' },
    { id: 'reservations' as Tab, label: 'Reservations' },
    { id: 'favorites' as Tab, label: 'Favorites' },
    { id: 'reviews' as Tab, label: 'Reviews' },
    { id: 'privacy' as Tab, label: 'Privacy & Data' }
  ];

  const handleEditProfile = () => {
    setIsEditingProfile(true);
  };

  const handleSaveProfile = async (updates: any) => {
    try {
      // Update customer via API
      await api.updateCustomer(currentUser.id, updates);
      
      // Update local state
      setCurrentUser({ ...currentUser, ...updates });
      
      // Emit event to update user in parent component (App.tsx)
      window.dispatchEvent(new CustomEvent('user-updated', { detail: { ...currentUser, ...updates } }));
      
      setIsEditingProfile(false);
    } catch (error) {
      console.error('Error updating profile:', error);
      throw error;
    }
  };

  const handleReorder = (orderId: string) => {
    toast.success('Items added to cart!');
  };

  const handleViewOrder = (orderId: string) => {
    // This is now handled internally by OrderHistoryList
    console.log('Viewing order:', orderId);
  };

  const handleWriteReview = (order: any) => {
    toast.info('Review feature coming soon!');
    // TODO: Open review modal
  };

  const handleRemoveFavorite = (restaurantId: string) => {
    toast.success('Removed from favorites');
  };

  const handleEditReview = (reviewId: string) => {
    toast.info('Review editing coming soon!');
  };

  const handleDeleteReview = (reviewId: string) => {
    if (confirm('Are you sure you want to delete this review?')) {
      toast.success('Review deleted');
    }
  };

  const handleAvatarChange = async (file: File) => {
    try {
      toast.loading('Uploading avatar...', { id: 'avatar-upload' });

      // Create unique filename
      const fileExt = file.name.split('.').pop();
      const fileName = `${currentUser.id}-${Date.now()}.${fileExt}`;
      const filePath = `avatars/${fileName}`;

      // Upload to Supabase Storage
      const formData = new FormData();
      formData.append('file', file);
      formData.append('path', filePath);

      const uploadResponse = await fetch(
        `https://${projectId}.supabase.co/functions/v1/make-server-1dccd8d3/upload-avatar`,
        {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${publicAnonKey}`,
          },
          body: formData,
        }
      );

      if (!uploadResponse.ok) {
        const error = await uploadResponse.text();
        throw new Error(error || 'Failed to upload avatar');
      }

      const { url } = await uploadResponse.json();

      // Update user profile with new avatar URL
      await api.updateCustomer(currentUser.id, { avatarUrl: url });

      // Update local state
      const updatedUser = { ...currentUser, avatarUrl: url };
      setCurrentUser(updatedUser);

      // Emit event to update user in parent component
      window.dispatchEvent(new CustomEvent('user-updated', { detail: updatedUser }));

      toast.success('Avatar updated successfully!', { id: 'avatar-upload' });
    } catch (error) {
      console.error('Error uploading avatar:', error);
      toast.error('Failed to upload avatar. Please try again.', { id: 'avatar-upload' });
      throw error;
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#101828] to-[#101828] text-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6">
          <div className="flex items-center justify-between mb-4">
            <button
              onClick={onBack}
              className="flex items-center gap-2 hover:bg-white/10 rounded-lg px-3 py-2 transition-colors"
            >
              <ArrowLeft className="w-5 h-5" />
              <span>Back</span>
            </button>
            <Button
              onClick={onLogout}
              variant="ghost"
              className="text-white hover:bg-white/10 gap-2"
            >
              <LogOut className="w-4 h-4" />
              <span className="hidden sm:inline">Logout</span>
            </Button>
          </div>
          <h1 className="text-2xl sm:text-3xl">My Account</h1>
        </div>
      </div>

      {/* Tabs */}
      <div className="bg-white border-b sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6">
          <div className="flex gap-2 overflow-x-auto hide-scrollbar">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`px-4 py-3 text-sm whitespace-nowrap border-b-2 transition-colors ${
                  activeTab === tab.id
                    ? 'border-[#101828] text-[#101828]'
                    : 'border-transparent text-gray-600 hover:text-gray-900'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        {activeTab === 'overview' && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-3">
              <ProfileOverview 
                user={{ ...currentUser, restaurantLoyalty: MOCK_RESTAURANT_LOYALTY }}
                onEdit={handleEditProfile}
                onAvatarChange={handleAvatarChange}
                onViewAllLoyalty={() => setActiveTab('loyalty')}
                onRestaurantLoyaltyClick={(restaurantId) => setActiveTab('loyalty')}
                recentOrders={MOCK_ORDERS}
              />
            </div>
          </div>
        )}

        {activeTab === 'loyalty' && (
          <div className="max-w-3xl mx-auto">
            <LoyaltyPointsCard
              restaurantLoyalties={MOCK_RESTAURANT_LOYALTY}
            />
          </div>
        )}

        {activeTab === 'orders' && (
          <div className="max-w-3xl mx-auto">
            <OrderHistoryList
              orders={MOCK_ORDERS}
              onReorder={handleReorder}
              onViewOrder={handleViewOrder}
              onRestaurantClick={onRestaurantClick}
              onWriteReview={handleWriteReview}
              vendorSettings={{ currency: 'EUR' }}
            />
          </div>
        )}

        {activeTab === 'reservations' && (
          <div className="max-w-4xl mx-auto">
            <CustomerReservations customerId={user.id} />
          </div>
        )}

        {activeTab === 'favorites' && (
          <SavedRestaurants
            restaurants={MOCK_FAVORITES}
            onRestaurantClick={onRestaurantClick}
            onRemoveFavorite={handleRemoveFavorite}
          />
        )}

        {activeTab === 'reviews' && (
          <div className="max-w-3xl mx-auto">
            <UserReviewsList
              reviews={MOCK_REVIEWS}
              onEdit={handleEditReview}
              onDelete={handleDeleteReview}
            />
          </div>
        )}

        {activeTab === 'privacy' && (
          <PrivacyData user={user} />
        )}
      </div>

      {/* Edit Profile Modal */}
      {isEditingProfile && (
        <ProfileEditForm
          user={currentUser}
          onClose={() => setIsEditingProfile(false)}
          onSave={handleSaveProfile}
          onAvatarChange={handleAvatarChange}
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