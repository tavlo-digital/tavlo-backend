import { User, Mail, Phone, Edit2, MapPin, Calendar, Camera, ChevronRight } from 'lucide-react';
import { Button } from '../ui/button';
import { useState, useRef } from 'react';
import { LoyaltyRestaurantCard } from './LoyaltyRestaurantCard';

interface ProfileOverviewProps {
  user: any;
  onEdit: () => void;
  onAvatarChange?: (file: File) => Promise<void>;
  onViewAllLoyalty?: () => void;
  onRestaurantLoyaltyClick?: (restaurantId: string) => void;
  recentOrders?: any[]; // Optional: for last activity line
}

export function ProfileOverview({ user, onEdit, onAvatarChange, onViewAllLoyalty, onRestaurantLoyaltyClick, recentOrders }: ProfileOverviewProps) {
  const [isUploadingAvatar, setIsUploadingAvatar] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleAvatarClick = () => {
    fileInputRef.current?.click();
  };

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
      alert('Please select an image file');
      return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert('Image size must be less than 5MB');
      return;
    }

    try {
      setIsUploadingAvatar(true);
      await onAvatarChange?.(file);
    } catch (error) {
      console.error('Error uploading avatar:', error);
      alert('Failed to upload avatar. Please try again.');
    } finally {
      setIsUploadingAvatar(false);
      // Reset input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  return (
    <div className="bg-white rounded-2xl p-6 border border-gray-100">
      <div className="flex items-start justify-between mb-6">
        <h2 className="text-2xl">Profile</h2>
        <Button onClick={onEdit} variant="outline" size="sm" className="gap-2">
          <Edit2 className="w-4 h-4" />
          Edit
        </Button>
      </div>

      <div className="flex items-center gap-4 mb-6">
        <div className="relative group">
          <div className="w-20 h-20 bg-gradient-to-br from-[#101828] to-[#101828] rounded-full flex items-center justify-center overflow-hidden">
            {user.avatarUrl ? (
              <img 
                src={user.avatarUrl} 
                alt={user.name}
                className="w-full h-full object-cover"
              />
            ) : (
              <User className="w-10 h-10 text-white" />
            )}
          </div>
          
          {/* Upload overlay button */}
          <button
            onClick={handleAvatarClick}
            disabled={isUploadingAvatar}
            className="absolute inset-0 bg-black/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer disabled:cursor-not-allowed"
            title="Change avatar"
          >
            {isUploadingAvatar ? (
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div>
            ) : (
              <Camera className="w-6 h-6 text-white" />
            )}
          </button>

          {/* Hidden file input */}
          <input
            ref={fileInputRef}
            type="file"
            accept="image/*"
            onChange={handleFileChange}
            className="hidden"
          />
        </div>
        
        <div>
          <h3 className="text-xl mb-1">{user.name}</h3>
          <p className="text-sm text-gray-500">Member since 2024</p>
        </div>
      </div>

      <div className="space-y-4">
        <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
          <Mail className="w-5 h-5 text-gray-400" />
          <div>
            <div className="text-xs text-gray-500 mb-0.5">Email</div>
            <div className="text-sm">{user.email || 'Not provided'}</div>
          </div>
        </div>

        <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
          <Phone className="w-5 h-5 text-gray-400" />
          <div>
            <div className="text-xs text-gray-500 mb-0.5">Phone</div>
            <div className="text-sm">{user.phone || 'Not provided'}</div>
          </div>
        </div>

        {user.address && (
          <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
            <MapPin className="w-5 h-5 text-gray-400" />
            <div>
              <div className="text-xs text-gray-500 mb-0.5">Address</div>
              <div className="text-sm">{user.address}</div>
            </div>
          </div>
        )}

        {user.gender && (
          <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
            <User className="w-5 h-5 text-gray-400" />
            <div>
              <div className="text-xs text-gray-500 mb-0.5">Gender</div>
              <div className="text-sm capitalize">{user.gender}</div>
            </div>
          </div>
        )}

        {user.dateOfBirth && (
          <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
            <Calendar className="w-5 h-5 text-gray-400" />
            <div>
              <div className="text-xs text-gray-500 mb-0.5">Date of Birth</div>
              <div className="text-sm">{new Date(user.dateOfBirth).toLocaleDateString()}</div>
            </div>
          </div>
        )}
      </div>

      {/* Loyalty by Restaurant Section */}
      {user.restaurantLoyalty && user.restaurantLoyalty.length > 0 && (() => {
        // Sort loyalty: redeemable first, then by points
        const sortedLoyalty = [...user.restaurantLoyalty].sort((a, b) => {
          const aCanRedeem = a.points >= (a.minimumRedemption || 100);
          const bCanRedeem = b.points >= (b.minimumRedemption || 100);
          
          if (aCanRedeem && !bCanRedeem) return -1;
          if (!aCanRedeem && bCanRedeem) return 1;
          
          return b.points - a.points;
        });
        
        // Count redeemable restaurants
        const redeemableCount = sortedLoyalty.filter(
          loyalty => loyalty.points >= (loyalty.minimumRedemption || 100)
        ).length;
        
        // Take only top 3
        const displayedLoyalty = sortedLoyalty.slice(0, 3);
        
        return (
          <div className="mt-6 pt-6 border-t border-gray-100">
            <h3 className="text-lg mb-1">Loyalty by Restaurant</h3>
            
            {/* Hint line */}
            {redeemableCount > 0 && (
              <p className="text-sm text-gray-500 mb-4">
                You have rewards available at {redeemableCount} {redeemableCount === 1 ? 'restaurant' : 'restaurants'}
              </p>
            )}
            
            <div className="space-y-2 mb-3">
              {displayedLoyalty.map((loyalty: any) => (
                <LoyaltyRestaurantCard
                  key={loyalty.restaurantId}
                  restaurantId={loyalty.restaurantId}
                  restaurantName={loyalty.restaurantName}
                  restaurantLogo={loyalty.restaurantLogo}
                  pointsBalance={loyalty.points}
                  minimumRedemption={loyalty.minimumRedemption || 100}
                  redemptionRate={loyalty.redemptionRate || 0.05}
                  variant="compact"
                  onClick={() => onRestaurantLoyaltyClick?.(loyalty.restaurantId)}
                />
              ))}
            </div>
            
            {/* View all link */}
            {user.restaurantLoyalty.length > 3 && (
              <button
                onClick={onViewAllLoyalty}
                className="text-sm text-[#101828] hover:underline"
              >
                View all loyalty wallets
              </button>
            )}
          </div>
        );
      })()}
      
      {/* Last Activity Line */}
      {recentOrders && recentOrders.length > 0 && (() => {
        const lastOrder = recentOrders[0];
        if (!lastOrder || !lastOrder.restaurantName) return null;
        
        // Calculate days ago
        let timeAgo = '';
        if (lastOrder.date) {
          const orderDate = new Date(lastOrder.date);
          const now = new Date();
          const diffTime = Math.abs(now.getTime() - orderDate.getTime());
          const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
          
          if (diffDays === 0) timeAgo = 'today';
          else if (diffDays === 1) timeAgo = '1 day ago';
          else timeAgo = `${diffDays} days ago`;
        }
        
        return (
          <div className="mt-6 pt-6 border-t border-gray-100">
            <p className="text-sm text-gray-500">
              Last order: {lastOrder.restaurantName}{timeAgo ? ` · ${timeAgo}` : ''}
            </p>
          </div>
        );
      })()}
    </div>
  );
}