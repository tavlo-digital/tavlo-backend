import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { Input } from '../ui/input';
import { Textarea } from '../ui/textarea';
import { 
  Star, 
  Search, 
  MessageSquare,
  AlertCircle,
  Clock,
  Sparkles,
  Info,
  CheckCircle,
  Settings as SettingsIcon,
  Send,
  ChevronDown,
  ChevronUp
} from 'lucide-react';
import { api } from '../../utils/api';
import { toast } from 'sonner@2.0.3';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '../ui/tabs';

interface Review {
  id: string;
  customerName?: string;
  rating: number;
  text: string;
  createdAt: string;
  orderId?: string;
  reply?: string;
  repliedAt?: string;
  isAnonymous?: boolean;
  orderType?: 'dine-in' | 'takeaway' | 'delivery';
  servicePeriod?: 'breakfast' | 'lunch' | 'dinner' | 'late-night';
  isPeakHour?: boolean;
  hadLongerWait?: boolean;
}

interface VendorSettings {
  enableReviews: boolean;
  enableMenuReviews: boolean;
  allowAnonymousReviews: boolean;
}

interface ReviewsManagementProps {
  vendorId: string;
}

export function ReviewsManagement({ vendorId }: ReviewsManagementProps) {
  const [reviews, setReviews] = useState<Review[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [activeTab, setActiveTab] = useState('all');
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  const [replyText, setReplyText] = useState('');
  const [aiDraftLoading, setAiDraftLoading] = useState(false);
  const [showReplyGuidance, setShowReplyGuidance] = useState<{ [key: string]: boolean }>({});
  
  // Vendor settings
  const [vendorSettings, setVendorSettings] = useState<VendorSettings>({
    enableReviews: true,
    enableMenuReviews: false,
    allowAnonymousReviews: false
  });

  useEffect(() => {
    loadReviewsAndSettings();
  }, []);

  const loadReviewsAndSettings = async () => {
    setLoading(true);
    try {
      // Load reviews
      const reviewData = await api.getComplaints(vendorId);
      
      // Mock contextual data (in production, this would come from order data)
      const enrichedReviews = reviewData.map((review: any) => ({
        ...review,
        isAnonymous: !review.customerName || review.customerName === 'Anonymous',
        orderType: ['dine-in', 'takeaway', 'delivery'][Math.floor(Math.random() * 3)] as Review['orderType'],
        servicePeriod: ['lunch', 'dinner', 'late-night'][Math.floor(Math.random() * 3)] as Review['servicePeriod'],
        isPeakHour: Math.random() > 0.6,
        hadLongerWait: Math.random() > 0.7
      }));
      
      setReviews(enrichedReviews);

      // Load vendor settings
      const settings = await api.getVendorSettings(vendorId);
      setVendorSettings({
        enableReviews: settings.enableReviews !== undefined ? settings.enableReviews : true,
        enableMenuReviews: settings.enableMenuReviews !== undefined ? settings.enableMenuReviews : false,
        allowAnonymousReviews: settings.allowAnonymousReviews !== undefined ? settings.allowAnonymousReviews : false
      });
    } catch (error) {
      console.error('Error loading reviews:', error);
      toast.error('Failed to load reviews');
    } finally {
      setLoading(false);
    }
  };

  const reseedData = async () => {
    try {
      await api.seedData(true); // force reseed
      toast.success('Demo data reseeded successfully');
      // Reload reviews
      await loadReviewsAndSettings();
    } catch (error) {
      console.error('Error reseeding data:', error);
      toast.error('Failed to reseed data');
    }
  };

  // Redefine "Needs Attention" - Rating ≤ 3★, not replied yet, created within last 7 days
  const needsAttention = (review: Review): boolean => {
    if (review.rating > 3) return false;
    if (review.reply) return false;
    
    const daysSinceCreated = Math.floor((Date.now() - new Date(review.createdAt).getTime()) / (1000 * 60 * 60 * 24));
    return daysSinceCreated <= 7;
  };

  const filteredReviews = reviews.filter((review) => {
    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      if (!review.customerName?.toLowerCase().includes(query) &&
          !review.text?.toLowerCase().includes(query)) {
        return false;
      }
    }
    if (activeTab === 'positive' && review.rating < 4) {
      return false;
    }
    if (activeTab === 'needs-attention' && !needsAttention(review)) {
      return false;
    }
    return true;
  });

  const stats = {
    total: reviews.length,
    positive: reviews.filter(r => r.rating >= 4).length,
    needsAttention: reviews.filter(r => needsAttention(r)).length,
    avgRating: reviews.length > 0 
      ? (reviews.reduce((sum, r) => sum + r.rating, 0) / reviews.length).toFixed(1)
      : '0.0'
  };

  // Generate context line for review
  const getReviewContext = (review: Review): string | null => {
    const parts: string[] = [];
    
    if (review.servicePeriod) {
      const periodLabels = {
        'breakfast': 'Breakfast',
        'lunch': 'Lunch',
        'dinner': 'Dinner',
        'late-night': 'Late night'
      };
      parts.push(periodLabels[review.servicePeriod]);
    }
    
    if (review.isPeakHour) {
      parts.push('Peak hour');
    } else if (parts.length > 0) {
      parts.push('Off-peak');
    }
    
    if (review.hadLongerWait) {
      parts.push('Longer wait than usual');
    }
    
    return parts.length > 0 ? parts.join(' · ') : null;
  };

  // Generate AI draft reply
  const generateAIDraft = async (review: Review) => {
    setAiDraftLoading(true);
    try {
      // Simulate AI generation (in production, this would call an actual AI service)
      await new Promise(resolve => setTimeout(resolve, 1500));
      
      let draft = '';
      if (review.rating >= 4) {
        draft = `Thank you for your kind words. We're delighted to hear you enjoyed your experience with us. We look forward to welcoming you back soon.`;
      } else {
        draft = `Thank you for taking the time to share your feedback. We apologize that your experience didn't meet expectations. We'd appreciate the opportunity to make this right. Please feel free to contact us directly.`;
      }
      
      setReplyText(draft);
      toast.success('Draft reply generated. Please review and edit before sending.');
    } catch (error) {
      toast.error('Failed to generate draft');
    } finally {
      setAiDraftLoading(false);
    }
  };

  const submitReply = async (reviewId: string) => {
    if (!replyText.trim()) {
      toast.error('Please enter a reply');
      return;
    }

    try {
      // Submit reply to backend
      await api.replyToReview(vendorId, reviewId, replyText);
      
      // Update local state
      setReviews(reviews.map(r => 
        r.id === reviewId 
          ? { ...r, reply: replyText, repliedAt: new Date().toISOString() }
          : r
      ));
      
      setReplyingTo(null);
      setReplyText('');
      toast.success('Reply posted successfully');
    } catch (error) {
      toast.error('Failed to post reply');
    }
  };

  const toggleReplyGuidance = (reviewId: string) => {
    setShowReplyGuidance(prev => ({
      ...prev,
      [reviewId]: !prev[reviewId]
    }));
  };

  // Reviews disabled state
  if (!vendorSettings.enableReviews) {
    return (
      <div className="p-6 space-y-6 max-w-[1400px] mx-auto">
        <div>
          <h2 className="text-2xl font-semibold text-neutral-900">Reviews & Feedback</h2>
          <p className="text-neutral-600 mt-1">
            Monitor customer feedback and respond to reviews
          </p>
        </div>

        <Card className="border-2">
          <CardContent className="p-12">
            <div className="text-center max-w-md mx-auto">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <MessageSquare className="w-8 h-8 text-gray-400" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                Reviews are currently disabled
              </h3>
              <p className="text-sm text-gray-600 mb-6">
                Enable reviews in your settings to start collecting customer feedback.
              </p>
              <Button
                onClick={() => {
                  // Navigate to settings - assuming navigation handler exists
                  window.location.hash = '#settings';
                }}
                className="bg-blue-600 hover:bg-blue-700"
              >
                <SettingsIcon className="w-4 h-4 mr-2" />
                Enable reviews in Settings
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6 max-w-[1400px] mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-neutral-900">Reviews & Feedback</h2>
          <p className="text-neutral-600 mt-1">
            Monitor customer feedback and respond to reviews
          </p>
        </div>
        
        {/* Reseed button for testing (remove in production) */}
        {reviews.length === 0 && (
          <Button
            onClick={reseedData}
            variant="outline"
            className="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
          >
            <Sparkles className="w-4 h-4 mr-2" />
            Load Demo Reviews
          </Button>
        )}
      </div>

      {/* Stats Cards - Context Only, No Trends */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Total Reviews</p>
                <p className="text-2xl font-semibold mt-1">{stats.total}</p>
              </div>
              <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <MessageSquare className="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Average Rating</p>
                <p className="text-2xl font-semibold mt-1 flex items-center gap-1">
                  {stats.avgRating}
                  <Star className="w-5 h-5 text-yellow-500 fill-yellow-500" />
                </p>
              </div>
              <div className="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <Star className="w-6 h-6 text-yellow-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Positive Reviews</p>
                <p className="text-2xl font-semibold mt-1">{stats.positive}</p>
                <p className="text-xs text-neutral-500 mt-1">4–5 stars</p>
              </div>
              <div className="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                <CheckCircle className="w-6 h-6 text-emerald-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div>
                  <p className="text-sm text-neutral-600">Needs Attention</p>
                  <p className="text-2xl font-semibold mt-1">{stats.needsAttention}</p>
                </div>
                <div className="group relative">
                  <Info className="w-4 h-4 text-gray-400 cursor-help" />
                  <div className="absolute left-0 top-full mt-2 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-3 py-2 w-56 z-10">
                    Reviews rated 3★ or lower, not yet replied to, and posted recently
                  </div>
                </div>
              </div>
              <div className="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <AlertCircle className="w-6 h-6 text-orange-600" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Common Themes in Recent Reviews - Only if ≥10 reviews */}
      {reviews.length >= 10 ? (
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <Sparkles className="w-5 h-5 text-gray-600" />
              <CardTitle className="text-lg">Common Themes in Recent Reviews</CardTitle>
            </div>
          </CardHeader>
          <CardContent>
            <div className="p-4 bg-gray-50 rounded-lg border border-gray-200">
              <p className="text-sm text-gray-700 leading-relaxed">
                Recent reviews mention wait times during dinner and praise food quality. Several guests noted friendly service and clean facilities.
              </p>
            </div>
            <div className="mt-3 flex items-center gap-2 text-xs text-gray-500">
              <Info className="w-3.5 h-3.5" />
              <span>Based on common professional response patterns · Medium confidence</span>
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card className="border-gray-200">
          <CardContent className="p-8">
            <div className="text-center text-gray-500">
              <Sparkles className="w-10 h-10 mx-auto mb-3 text-gray-300" />
              <p className="text-sm">Review themes will appear once more feedback is available.</p>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Search Filter */}
      <Card>
        <CardContent className="p-4">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
            <Input
              type="text"
              placeholder="Search reviews..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10"
            />
          </div>
        </CardContent>
      </Card>

      {/* Reviews List */}
      <Card>
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <CardHeader className="border-b">
            <TabsList className="w-full justify-start">
              <TabsTrigger value="all">
                All reviews ({stats.total})
              </TabsTrigger>
              <TabsTrigger value="positive">
                Positive ({stats.positive})
              </TabsTrigger>
              <TabsTrigger value="needs-attention">
                Needs Attention ({stats.needsAttention})
              </TabsTrigger>
            </TabsList>
          </CardHeader>

          <CardContent className="p-6">
            {loading ? (
              <div className="text-center py-12 text-neutral-500">
                Loading reviews...
              </div>
            ) : filteredReviews.length === 0 ? (
              <div className="text-center py-12 text-neutral-500">
                <MessageSquare className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                <p className="text-sm">No reviews found</p>
              </div>
            ) : (
              <div className="space-y-4">
                {filteredReviews.map((review, index) => {
                  const context = getReviewContext(review);
                  const requiresAttention = needsAttention(review);

                  return (
                    <div 
                      key={`${review.id}_${index}`} 
                      className="p-5 rounded-lg border-2 border-gray-200 bg-white hover:border-gray-300 transition-colors relative"
                    >
                      <div className="flex items-start justify-between mb-2">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 bg-neutral-200 rounded-full flex items-center justify-center font-semibold text-neutral-700">
                            {review.isAnonymous ? 'G' : review.customerName?.charAt(0)?.toUpperCase() || 'G'}
                          </div>
                          <div>
                            <div className="font-medium text-neutral-900">
                              {review.isAnonymous && vendorSettings.allowAnonymousReviews ? (
                                <div className="flex items-center gap-1.5">
                                  <span>Guest</span>
                                  <div className="group relative">
                                    <Info className="w-3.5 h-3.5 text-gray-400 cursor-help" />
                                    <div className="absolute left-0 top-full mt-1 hidden group-hover:block bg-gray-900 text-white text-xs rounded px-3 py-2 whitespace-nowrap z-10">
                                      Submitted without login
                                    </div>
                                  </div>
                                </div>
                              ) : (
                                review.customerName || 'Guest'
                              )}
                            </div>
                            <div className="flex items-center gap-2 mt-0.5">
                              <div className="flex">
                                {[...Array(5)].map((_, i) => (
                                  <Star
                                    key={`${review.id}_star_${i}`}
                                    className={`w-4 h-4 ${
                                      i < review.rating
                                        ? 'text-yellow-400 fill-yellow-400'
                                        : 'text-neutral-300'
                                    }`}
                                  />
                                ))}
                              </div>
                              <span className="text-xs text-neutral-500">
                                {new Date(review.createdAt).toLocaleDateString('en-US', {
                                  month: 'short',
                                  day: 'numeric',
                                  year: 'numeric'
                                })}
                              </span>
                            </div>
                          </div>
                        </div>
                        
                        {/* Needs Attention Label - Calm, Amber/Neutral */}
                        {requiresAttention && !review.reply && (
                          <div className="flex items-center gap-1.5 text-xs text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded">
                            Needs attention
                          </div>
                        )}
                      </div>

                      {/* Review Context Strip */}
                      {context && (
                        <div className="mb-2.5 text-xs text-gray-500 ml-[52px]">
                          {context}
                        </div>
                      )}

                      {/* Review Text - Primary Focus - Increased Contrast */}
                      <p className="text-neutral-800 font-medium mb-3 leading-relaxed ml-[52px]">
                        {review.text}
                      </p>

                      {/* Existing Reply - Secondary, Reduced Contrast */}
                      {review.reply && (
                        <div className="mt-4 ml-[52px] pl-3 border-l-2 border-blue-200 bg-blue-50/60 p-3 rounded-r-lg">
                          <div className="flex items-center gap-2 mb-1.5">
                            <span className="text-xs font-medium text-blue-800">Your reply</span>
                            <span className="text-xs text-blue-600/70">
                              {new Date(review.repliedAt!).toLocaleDateString('en-US', {
                                month: 'short',
                                day: 'numeric'
                              })}
                            </span>
                          </div>
                          <p className="text-sm text-blue-800/80">{review.reply}</p>
                        </div>
                      )}

                      {/* Reply Actions */}
                      {!review.reply && (
                        <div className="mt-4">
                          {replyingTo === review.id ? (
                            <div className="space-y-3">
                              {/* Reply Guidance - Static Checklist */}
                              <button
                                onClick={() => toggleReplyGuidance(review.id)}
                                className="w-full flex items-center justify-between text-xs text-gray-600 hover:text-gray-900 p-2 rounded hover:bg-gray-50 transition-colors"
                              >
                                <span>Reply guidance</span>
                                {showReplyGuidance[review.id] ? (
                                  <ChevronUp className="w-3.5 h-3.5" />
                                ) : (
                                  <ChevronDown className="w-3.5 h-3.5" />
                                )}
                              </button>

                              {showReplyGuidance[review.id] && (
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                                  <ul className="text-xs text-blue-900 space-y-1.5">
                                    <li className="flex items-start gap-2">
                                      <CheckCircle className="w-3.5 h-3.5 mt-0.5 text-blue-600 flex-shrink-0" />
                                      <span>Acknowledge the issue</span>
                                    </li>
                                    <li className="flex items-start gap-2">
                                      <CheckCircle className="w-3.5 h-3.5 mt-0.5 text-blue-600 flex-shrink-0" />
                                      <span>Apologize briefly if appropriate</span>
                                    </li>
                                    <li className="flex items-start gap-2">
                                      <CheckCircle className="w-3.5 h-3.5 mt-0.5 text-blue-600 flex-shrink-0" />
                                      <span>Avoid excuses</span>
                                    </li>
                                    <li className="flex items-start gap-2">
                                      <CheckCircle className="w-3.5 h-3.5 mt-0.5 text-blue-600 flex-shrink-0" />
                                      <span>Offer offline follow-up if needed</span>
                                    </li>
                                  </ul>
                                </div>
                              )}

                              <Textarea
                                value={replyText}
                                onChange={(e) => setReplyText(e.target.value)}
                                placeholder="Write a professional reply..."
                                className="min-h-[120px]"
                              />

                              <div className="flex items-center gap-2">
                                <Button
                                  onClick={() => submitReply(review.id)}
                                  className="bg-blue-600 hover:bg-blue-700"
                                  size="sm"
                                >
                                  <Send className="w-3.5 h-3.5 mr-1.5" />
                                  Post Reply
                                </Button>
                                
                                {/* AI-Assisted Reply - Optional, Controlled */}
                                <Button
                                  onClick={() => generateAIDraft(review)}
                                  variant="outline"
                                  size="sm"
                                  disabled={aiDraftLoading}
                                >
                                  <Sparkles className="w-3.5 h-3.5 mr-1.5" />
                                  {aiDraftLoading ? 'Drafting...' : 'Draft a polite reply'}
                                </Button>

                                <Button
                                  onClick={() => {
                                    setReplyingTo(null);
                                    setReplyText('');
                                  }}
                                  variant="ghost"
                                  size="sm"
                                >
                                  Cancel
                                </Button>
                              </div>
                            </div>
                          ) : (
                            <Button 
                              size="sm" 
                              variant="outline"
                              onClick={() => {
                                setReplyingTo(review.id);
                                setReplyText('');
                              }}
                              className="text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                            >
                              <MessageSquare className="w-3.5 h-3.5 mr-1.5" />
                              Reply
                            </Button>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Tabs>
      </Card>
    </div>
  );
}