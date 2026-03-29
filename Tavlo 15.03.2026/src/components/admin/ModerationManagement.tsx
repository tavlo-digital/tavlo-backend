import { useState } from 'react';
import { 
  Search,
  Filter,
  Star,
  Eye,
  EyeOff,
  Trash2,
  Flag,
  MessageSquare,
  AlertTriangle,
  CheckCircle,
  Clock,
  User,
  Store,
  Image as ImageIcon
} from 'lucide-react';
import { Input } from '../ui/input';
import { toast } from 'sonner@2.0.3';

interface Review {
  id: string;
  type: 'restaurant' | 'item';
  customerName: string;
  customerId: string;
  vendorName: string;
  itemName?: string;
  rating: number;
  text: string;
  photos: string[];
  date: string;
  status: 'pending' | 'approved' | 'hidden';
  isReported: boolean;
  reportReason?: string;
}

interface Complaint {
  id: string;
  customerName: string;
  customerId: string;
  vendorName: string;
  vendorId: string;
  orderId: string;
  issue: string;
  description: string;
  status: 'open' | 'under_review' | 'resolved';
  priority: 'low' | 'medium' | 'high';
  createdAt: string;
  assignedTo?: string;
  notes: string[];
}

interface ModerationManagementProps {
  page?: string;
}

export function ModerationManagement({ page }: ModerationManagementProps) {
  const [activeTab, setActiveTab] = useState<'reviews' | 'complaints'>('reviews');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [selectedReview, setSelectedReview] = useState<Review | null>(null);
  const [selectedComplaint, setSelectedComplaint] = useState<Complaint | null>(null);

  const reviews: Review[] = [
    {
      id: 'rev_001',
      type: 'restaurant',
      customerName: 'John Smith',
      customerId: 'c_001',
      vendorName: 'Bella Italia',
      rating: 5,
      text: 'Absolutely amazing experience! The pasta was perfectly cooked and the service was exceptional. Will definitely come back!',
      photos: [],
      date: '2024-06-10T14:30:00',
      status: 'approved',
      isReported: false
    },
    {
      id: 'rev_002',
      type: 'item',
      customerName: 'Sarah Johnson',
      customerId: 'c_002',
      vendorName: 'Sakura Sushi',
      itemName: 'California Roll',
      rating: 4,
      text: 'Fresh and delicious! Great portion size.',
      photos: ['https://images.unsplash.com/photo-1579584425555-c3ce17fd4351'],
      date: '2024-06-09T18:20:00',
      status: 'approved',
      isReported: false
    },
    {
      id: 'rev_003',
      type: 'restaurant',
      customerName: 'Mike Peterson',
      customerId: 'c_003',
      vendorName: 'Burger Palace',
      rating: 1,
      text: 'This is completely unacceptable! Worst food ever! Total scam!!!',
      photos: [],
      date: '2024-06-08T20:15:00',
      status: 'pending',
      isReported: true,
      reportReason: 'Inappropriate language and false claims'
    },
    {
      id: 'rev_004',
      type: 'item',
      customerName: 'Emma Wilson',
      customerId: 'c_005',
      vendorName: 'Taco House',
      itemName: 'Beef Tacos',
      rating: 5,
      text: 'Best tacos in Vienna! Authentic Mexican flavor.',
      photos: [],
      date: '2024-06-07T19:45:00',
      status: 'pending',
      isReported: false
    }
  ];

  const complaints: Complaint[] = [
    {
      id: 'comp_001',
      customerName: 'John Doe',
      customerId: 'c_006',
      vendorName: 'Bella Italia',
      vendorId: 'v_001',
      orderId: 'ord_12345',
      issue: 'Wrong order delivered',
      description: 'I ordered Margherita pizza but received a Pepperoni instead. Called the restaurant but they refused to fix it.',
      status: 'open',
      priority: 'high',
      createdAt: '2024-06-10T15:30:00',
      notes: []
    },
    {
      id: 'comp_002',
      customerName: 'Sarah M.',
      customerId: 'c_007',
      vendorName: 'Cafe Noir',
      vendorId: 'v_003',
      orderId: 'ord_12346',
      issue: 'Food quality issue',
      description: 'The sandwich was stale and the coffee was cold.',
      status: 'under_review',
      priority: 'medium',
      createdAt: '2024-06-09T12:20:00',
      assignedTo: 'Admin User',
      notes: ['Contacted vendor for explanation', 'Vendor offered refund']
    },
    {
      id: 'comp_003',
      customerName: 'Mike P.',
      customerId: 'c_003',
      vendorName: 'Taco House',
      vendorId: 'v_006',
      orderId: 'ord_12347',
      issue: 'Long wait time',
      description: 'Waited 45 minutes for my order when it said 15-20 minutes.',
      status: 'resolved',
      priority: 'low',
      createdAt: '2024-06-08T18:00:00',
      assignedTo: 'Admin User',
      notes: ['Contacted customer', 'Issued €10 credit', 'Case resolved']
    }
  ];

  const filteredReviews = reviews.filter(review => {
    const matchesSearch = 
      review.customerName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      review.vendorName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      review.text.toLowerCase().includes(searchQuery.toLowerCase());
    
    const matchesStatus = statusFilter === 'all' || review.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  const filteredComplaints = complaints.filter(complaint => {
    const matchesSearch = 
      complaint.customerName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      complaint.vendorName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      complaint.issue.toLowerCase().includes(searchQuery.toLowerCase());
    
    const matchesStatus = statusFilter === 'all' || complaint.status === statusFilter;

    return matchesSearch && matchesStatus;
  });

  const handleApproveReview = (reviewId: string) => {
    toast.success('Review approved and published');
  };

  const handleHideReview = (reviewId: string) => {
    toast.success('Review hidden from public view');
  };

  const handleDeleteReview = (reviewId: string) => {
    if (confirm('Permanently delete this review?')) {
      toast.success('Review deleted');
    }
  };

  const handleResolveComplaint = (complaintId: string) => {
    toast.success('Complaint marked as resolved');
  };

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl mb-1">Reviews & Complaints</h1>
        <p className="text-sm text-gray-500">Moderate customer reviews and handle complaints</p>
      </div>

      {/* Moderation Policy Banner */}
      <div className="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 text-amber-600 mt-0.5" />
          <div>
            <h3 className="font-medium text-amber-900 mb-1">Moderation Policy</h3>
            <p className="text-sm text-amber-700">
              <strong>Moderation affects visibility only. Original reviews are preserved in audit logs.</strong>
              {' '}Admin can hide abusive content or approve reviews, but cannot edit review text or modify star ratings. 
              All moderation actions are logged with reason and admin user.
            </p>
          </div>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Pending Reviews</span>
            <Clock className="w-4 h-4 text-orange-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">
            {reviews.filter(r => r.status === 'pending').length}
          </div>
          <div className="text-xs text-orange-600">Awaiting moderation</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Reported</span>
            <Flag className="w-4 h-4 text-red-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">
            {reviews.filter(r => r.isReported).length}
          </div>
          <div className="text-xs text-red-600">Requires attention</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Open Complaints</span>
            <AlertTriangle className="w-4 h-4 text-yellow-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">
            {complaints.filter(c => c.status === 'open').length}
          </div>
          <div className="text-xs text-yellow-600">Needs resolution</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Avg Rating</span>
            <Star className="w-4 h-4 text-yellow-500 fill-yellow-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">4.6</div>
          <div className="text-xs text-gray-500">Across all vendors</div>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-6">
        <div className="flex gap-6">
          {[
            { id: 'reviews', label: 'Reviews', icon: Star, count: reviews.length },
            { id: 'complaints', label: 'Complaints', icon: MessageSquare, count: complaints.length }
          ].map((tab) => {
            const Icon = tab.icon;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`flex items-center gap-2 pb-3 border-b-2 transition-colors ${
                  activeTab === tab.id
                    ? 'border-purple-600 text-purple-600'
                    : 'border-transparent text-gray-600 hover:text-gray-900'
                }`}
              >
                <Icon className="w-4 h-4" />
                <span className="text-sm font-medium">{tab.label}</span>
                <span className="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-xs">
                  {tab.count}
                </span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl border border-gray-200 p-4 mb-6">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <Input
                type="text"
                placeholder={`Search ${activeTab}...`}
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>
          <div className="flex gap-3">
            <select 
              className="px-4 py-2 border border-gray-200 rounded-lg text-sm"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              {activeTab === 'reviews' ? (
                <>
                  <option value="all">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="hidden">Hidden</option>
                </>
              ) : (
                <>
                  <option value="all">All Status</option>
                  <option value="open">Open</option>
                  <option value="under_review">Under Review</option>
                  <option value="resolved">Resolved</option>
                </>
              )}
            </select>
            {activeTab === 'reviews' && (
              <select className="px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option>All Ratings</option>
                <option>5 Stars</option>
                <option>4 Stars</option>
                <option>3 Stars</option>
                <option>2 Stars</option>
                <option>1 Star</option>
              </select>
            )}
          </div>
        </div>
      </div>

      {/* Reviews View */}
      {activeTab === 'reviews' && (
        <div className="space-y-4">
          {filteredReviews.map((review) => (
            <div 
              key={review.id}
              className={`bg-white rounded-xl border-2 p-6 ${
                review.isReported ? 'border-red-300 bg-red-50' : 'border-gray-200'
              }`}
            >
              {review.isReported && (
                <div className="mb-4 p-3 bg-red-100 border border-red-200 rounded-lg flex items-center gap-2">
                  <Flag className="w-4 h-4 text-red-600" />
                  <span className="text-sm text-red-900 font-medium">Reported: {review.reportReason}</span>
                </div>
              )}

              <div className="flex items-start justify-between mb-4">
                <div className="flex items-start gap-4">
                  <div className="w-10 h-10 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                    <User className="w-5 h-5 text-white" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <span className="font-medium">{review.customerName}</span>
                      <span className="text-xs text-gray-500">→</span>
                      <span className="text-sm text-gray-700">{review.vendorName}</span>
                      {review.itemName && (
                        <>
                          <span className="text-xs text-gray-500">•</span>
                          <span className="text-sm text-purple-600">{review.itemName}</span>
                        </>
                      )}
                    </div>
                    <div className="flex items-center gap-2 mb-2">
                      <div className="flex">
                        {[...Array(5)].map((_, i) => (
                          <Star 
                            key={i} 
                            className={`w-4 h-4 ${
                              i < review.rating 
                                ? 'text-yellow-400 fill-yellow-400' 
                                : 'text-gray-300'
                            }`}
                          />
                        ))}
                      </div>
                      <span className="text-xs text-gray-500">
                        {new Date(review.date).toLocaleDateString()}
                      </span>
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                        review.status === 'approved' ? 'bg-green-100 text-green-700' :
                        review.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                        'bg-gray-100 text-gray-700'
                      }`}>
                        {review.status}
                      </span>
                    </div>
                    <p className="text-sm text-gray-700">{review.text}</p>
                    {review.photos.length > 0 && (
                      <div className="flex gap-2 mt-3">
                        {review.photos.map((photo, index) => (
                          <img 
                            key={index}
                            src={photo}
                            alt="Review"
                            className="w-20 h-20 object-cover rounded-lg border border-gray-200"
                          />
                        ))}
                      </div>
                    )}
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  {review.status === 'pending' && (
                    <button
                      onClick={() => handleApproveReview(review.id)}
                      className="p-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                      title="Approve"
                    >
                      <CheckCircle className="w-4 h-4" />
                    </button>
                  )}
                  {review.status !== 'hidden' && (
                    <button
                      onClick={() => handleHideReview(review.id)}
                      className="p-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                      title="Hide"
                    >
                      <EyeOff className="w-4 h-4" />
                    </button>
                  )}
                  <button
                    onClick={() => handleDeleteReview(review.id)}
                    className="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                    title="Delete"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Complaints View */}
      {activeTab === 'complaints' && (
        <div className="space-y-4">
          {filteredComplaints.map((complaint) => (
            <div 
              key={complaint.id}
              className="bg-white rounded-xl border-2 border-gray-200 p-6"
            >
              <div className="flex items-start justify-between mb-4">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <span className={`w-2 h-2 rounded-full ${
                      complaint.priority === 'high' ? 'bg-red-500' :
                      complaint.priority === 'medium' ? 'bg-orange-500' :
                      'bg-yellow-500'
                    }`}></span>
                    <span className="font-medium text-lg">{complaint.issue}</span>
                    <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                      complaint.status === 'open' ? 'bg-red-100 text-red-700' :
                      complaint.status === 'under_review' ? 'bg-yellow-100 text-yellow-700' :
                      'bg-green-100 text-green-700'
                    }`}>
                      {complaint.status.replace('_', ' ')}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-4 mb-3 text-sm">
                    <div>
                      <span className="text-gray-500">Customer:</span>{' '}
                      <span className="font-medium">{complaint.customerName}</span>
                    </div>
                    <div>
                      <span className="text-gray-500">Vendor:</span>{' '}
                      <span className="font-medium">{complaint.vendorName}</span>
                    </div>
                    <div>
                      <span className="text-gray-500">Order:</span>{' '}
                      <span className="font-mono text-xs">{complaint.orderId}</span>
                    </div>
                    <div>
                      <span className="text-gray-500">Created:</span>{' '}
                      <span>{new Date(complaint.createdAt).toLocaleDateString()}</span>
                    </div>
                  </div>

                  <p className="text-sm text-gray-700 mb-3">{complaint.description}</p>

                  {complaint.notes.length > 0 && (
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                      <div className="text-xs font-medium text-blue-900 mb-2">Admin Notes:</div>
                      <ul className="space-y-1">
                        {complaint.notes.map((note, index) => (
                          <li key={index} className="text-xs text-blue-800">• {note}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>

                <div className="flex flex-col gap-2 ml-4">
                  {complaint.status !== 'resolved' && (
                    <>
                      <button
                        onClick={() => handleResolveComplaint(complaint.id)}
                        className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm whitespace-nowrap"
                      >
                        Resolve
                      </button>
                      <button className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                        Add Note
                      </button>
                    </>
                  )}
                  <button className="px-4 py-2 border border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 text-sm">
                    View Order
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}