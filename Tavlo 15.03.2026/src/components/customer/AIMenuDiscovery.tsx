import { useState } from 'react';
import { Sparkles, ThumbsUp, Clock, Leaf, Flame, Star } from 'lucide-react';
import { AISuggestionChip, AITooltip } from '../ai/AIComponents';

interface MenuItem {
  id: string;
  name: string;
  description: string;
  price: number;
  image: string;
  rating: number;
  orders: number;
  prepTime: number;
  tags: string[];
  aiScore: number;
}

export function AIMenuDiscovery() {
  const [activeFilter, setActiveFilter] = useState<string | null>(null);

  const menuItems: MenuItem[] = [
    {
      id: '1',
      name: 'Margherita Pizza',
      description: 'Classic tomato sauce, mozzarella, fresh basil',
      price: 12.90,
      image: 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002',
      rating: 4.9,
      orders: 487,
      prepTime: 15,
      tags: ['vegetarian', 'popular', 'quick'],
      aiScore: 95
    },
    {
      id: '2',
      name: 'Caesar Salad',
      description: 'Romaine lettuce, parmesan, croutons, Caesar dressing',
      price: 11.00,
      image: 'https://images.unsplash.com/photo-1546793665-c74683f339c1',
      rating: 3.9,
      orders: 78,
      prepTime: 8,
      tags: ['vegetarian', 'quick', 'light'],
      aiScore: 72
    },
    {
      id: '3',
      name: 'Truffle Pasta',
      description: 'Fresh pasta with black truffle, parmesan, butter',
      price: 16.50,
      image: 'https://images.unsplash.com/photo-1621996346565-e3dbc646d9a9',
      rating: 4.8,
      orders: 102,
      prepTime: 18,
      tags: ['vegetarian', 'premium'],
      aiScore: 88
    },
    {
      id: '4',
      name: 'Grilled Salmon',
      description: 'Fresh salmon fillet with seasonal vegetables',
      price: 19.90,
      image: 'https://images.unsplash.com/photo-1467003909585-2f8a72700288',
      rating: 4.7,
      orders: 156,
      prepTime: 22,
      tags: ['healthy', 'gluten-free'],
      aiScore: 91
    }
  ];

  const aiSuggestions = [
    { id: 'popular', label: 'Most Popular', icon: <ThumbsUp className="w-3.5 h-3.5" /> },
    { id: 'quick', label: 'Quick Lunch', icon: <Clock className="w-3.5 h-3.5" /> },
    { id: 'vegetarian', label: 'Vegetarian', icon: <Leaf className="w-3.5 h-3.5" /> },
    { id: 'spicy', label: 'Spicy Options', icon: <Flame className="w-3.5 h-3.5" /> },
  ];

  const handleFilterClick = (filterId: string) => {
    setActiveFilter(activeFilter === filterId ? null : filterId);
  };

  const getFilteredItems = () => {
    if (!activeFilter) return menuItems;
    
    switch (activeFilter) {
      case 'popular':
        return menuItems.filter(item => item.tags.includes('popular') || item.orders > 100);
      case 'quick':
        return menuItems.filter(item => item.prepTime <= 15);
      case 'vegetarian':
        return menuItems.filter(item => item.tags.includes('vegetarian'));
      default:
        return menuItems;
    }
  };

  const filteredItems = getFilteredItems();

  return (
    <div className="max-w-4xl mx-auto p-6">
      {/* AI Discovery Header */}
      <div className="mb-6">
        <div className="flex items-center gap-2 mb-3">
          <Sparkles className="w-5 h-5 text-purple-600" />
          <h2 className="text-lg font-medium">Discover Your Perfect Dish</h2>
          <AITooltip
            title="How this works"
            explanation="We suggest dishes based on popularity, your preferences, and what's quick to prepare"
          />
        </div>
        
        {/* AI Suggestion Chips */}
        <div className="flex flex-wrap gap-2">
          {aiSuggestions.map((suggestion) => (
            <button
              key={suggestion.id}
              onClick={() => handleFilterClick(suggestion.id)}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm transition-colors ${
                activeFilter === suggestion.id
                  ? 'bg-purple-600 text-white'
                  : 'bg-purple-50 hover:bg-purple-100 text-purple-700'
              }`}
            >
              {suggestion.icon}
              {suggestion.label}
            </button>
          ))}
        </div>
      </div>

      {/* Active Filter Info */}
      {activeFilter && (
        <div className="mb-4 p-3 bg-purple-50 border border-purple-200 rounded-lg">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2 text-sm text-purple-900">
              <Sparkles className="w-4 h-4" />
              <span>
                Showing {filteredItems.length} {activeFilter === 'popular' && 'most popular'}
                {activeFilter === 'quick' && 'quick'} {activeFilter === 'vegetarian' && 'vegetarian'} 
                {' '}dishes
              </span>
            </div>
            <button
              onClick={() => setActiveFilter(null)}
              className="text-sm text-purple-700 font-medium hover:underline"
            >
              Clear filter
            </button>
          </div>
        </div>
      )}

      {/* Menu Items Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {filteredItems.map((item) => (
          <div
            key={item.id}
            className="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow cursor-pointer"
          >
            {/* Image */}
            <div className="relative h-40">
              <img
                src={item.image}
                alt={item.name}
                className="w-full h-full object-cover"
              />
              {/* AI Badge for top recommendations */}
              {item.aiScore >= 90 && (
                <div className="absolute top-2 right-2 px-2 py-1 bg-purple-600 text-white rounded-lg text-xs font-medium flex items-center gap-1">
                  <Sparkles className="w-3 h-3" />
                  AI Pick
                </div>
              )}
              {/* Popular Badge */}
              {item.orders > 100 && (
                <div className="absolute top-2 left-2 px-2 py-1 bg-orange-500 text-white rounded-lg text-xs font-medium flex items-center gap-1">
                  <ThumbsUp className="w-3 h-3" />
                  Popular
                </div>
              )}
            </div>

            {/* Content */}
            <div className="p-4">
              <div className="flex items-start justify-between mb-2">
                <h3 className="font-medium text-lg">{item.name}</h3>
                <span className="text-lg font-semibold text-purple-600">
                  €{item.price.toFixed(2)}
                </span>
              </div>

              <p className="text-sm text-gray-600 mb-3">{item.description}</p>

              {/* Metrics */}
              <div className="flex items-center gap-4 text-sm text-gray-600 mb-3">
                <div className="flex items-center gap-1">
                  <Star className="w-4 h-4 text-yellow-400 fill-yellow-400" />
                  <span className="font-medium">{item.rating}</span>
                </div>
                <div className="flex items-center gap-1">
                  <ThumbsUp className="w-4 h-4" />
                  <span>{item.orders} orders</span>
                </div>
                <div className="flex items-center gap-1">
                  <Clock className="w-4 h-4" />
                  <span>{item.prepTime} min</span>
                </div>
              </div>

              {/* Tags */}
              <div className="flex flex-wrap gap-1.5">
                {item.tags.map((tag) => (
                  <span
                    key={tag}
                    className="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs"
                  >
                    {tag}
                  </span>
                ))}
              </div>

              {/* Add to Order Button */}
              <button className="w-full mt-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-medium">
                Add to Order
              </button>
            </div>
          </div>
        ))}
      </div>

      {/* AI Summary Section */}
      {activeFilter === 'popular' && (
        <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <div className="flex items-start gap-3">
            <Sparkles className="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
            <div>
              <div className="font-medium text-blue-900 mb-1">What people say</div>
              <p className="text-sm text-blue-800">
                Margherita Pizza is our #1 bestseller! Customers love the fresh ingredients and quick preparation. 
                "Perfect every time" is the most common review.
              </p>
              <div className="text-xs text-blue-700 mt-2">Based on 487 orders and 124 reviews</div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
