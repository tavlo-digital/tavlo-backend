import { useState, useEffect } from 'react';
import { Calendar as CalendarIcon, Clock, Users, MapPin, X, AlertCircle } from 'lucide-react';
import { Button } from './ui/button';
import { toast } from 'sonner@2.0.3';
import { api } from '../utils/api';

interface CustomerReservationsProps {
  customerId: string;
  onClose?: () => void;
}

type TabType = 'upcoming' | 'pending' | 'past' | 'cancelled';

export function CustomerReservations({ customerId, onClose }: CustomerReservationsProps) {
  const [activeTab, setActiveTab] = useState<TabType>('upcoming');
  const [reservations, setReservations] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [cancellingId, setCancellingId] = useState<string | null>(null);
  
  const loadReservations = async () => {
    setLoading(true);
    try {
      const data = await api.getCustomerReservations(customerId);
      setReservations(data);
    } catch (error) {
      console.error('Error loading reservations:', error);
      toast.error('Failed to load reservations');
    } finally {
      setLoading(false);
    }
  };
  
  useEffect(() => {
    loadReservations();
    
    // Poll for updates every 30 seconds
    const interval = setInterval(loadReservations, 30000);
    return () => clearInterval(interval);
  }, [customerId]);
  
  const handleCancel = async (reservationId: string) => {
    if (!confirm('Are you sure you want to cancel this reservation?')) {
      return;
    }
    
    setCancellingId(reservationId);
    try {
      await api.cancelReservation(reservationId, customerId);
      toast.success('Reservation cancelled');
      await loadReservations();
    } catch (error: any) {
      console.error('Error cancelling reservation:', error);
      toast.error(error.message || 'Failed to cancel reservation');
    } finally {
      setCancellingId(null);
    }
  };
  
  const getStatusBadge = (status: string) => {
    const styles = {
      pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
      confirmed: 'bg-green-100 text-green-800 border-green-200',
      declined: 'bg-red-100 text-red-800 border-red-200',
      cancelled: 'bg-gray-100 text-gray-800 border-gray-200',
      completed: 'bg-blue-100 text-blue-800 border-blue-200'
    };
    
    const labels = {
      pending: 'Pending',
      confirmed: 'Confirmed',
      declined: 'Declined',
      cancelled: 'Cancelled',
      completed: 'Completed'
    };
    
    return (
      <span className={`px-3 py-1 rounded-full text-xs border ${styles[status as keyof typeof styles]}`}>
        {labels[status as keyof typeof labels]}
      </span>
    );
  };
  
  const filterReservations = (type: TabType) => {
    const now = new Date();
    
    return reservations.filter(res => {
      const resDateTime = new Date(`${res.date}T${res.time}`);
      
      if (type === 'upcoming') {
        return res.status === 'confirmed' && resDateTime >= now;
      } else if (type === 'pending') {
        return res.status === 'pending' && resDateTime >= now;
      } else if (type === 'past') {
        return (res.status === 'completed' || (res.status === 'confirmed' && resDateTime < now));
      } else if (type === 'cancelled') {
        return res.status === 'cancelled' || res.status === 'declined';
      }
      return false;
    });
  };
  
  const filteredReservations = filterReservations(activeTab);
  
  const tabs = [
    { id: 'upcoming' as TabType, label: 'Upcoming', count: filterReservations('upcoming').length },
    { id: 'pending' as TabType, label: 'Pending', count: filterReservations('pending').length },
    { id: 'past' as TabType, label: 'Past', count: filterReservations('past').length },
    { id: 'cancelled' as TabType, label: 'Cancelled', count: filterReservations('cancelled').length }
  ];
  
  return (
    <div className="max-w-4xl mx-auto">
      {onClose && (
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-2xl">My Reservations</h2>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full">
            <X className="w-6 h-6" />
          </button>
        </div>
      )}
      
      {/* Tabs */}
      <div className="flex gap-2 border-b mb-6 overflow-x-auto">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`
              px-4 py-3 border-b-2 transition whitespace-nowrap
              ${activeTab === tab.id 
                ? 'border-orange-500 text-orange-500' 
                : 'border-transparent text-gray-600 hover:text-gray-900'
              }
            `}
          >
            {tab.label}
            {tab.count > 0 && (
              <span className={`
                ml-2 px-2 py-0.5 rounded-full text-xs
                ${activeTab === tab.id ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-600'}
              `}>
                {tab.count}
              </span>
            )}
          </button>
        ))}
      </div>
      
      {/* Content */}
      {loading ? (
        <div className="text-center py-12">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading reservations...</p>
        </div>
      ) : filteredReservations.length === 0 ? (
        <div className="text-center py-12">
          <CalendarIcon className="w-16 h-16 text-gray-300 mx-auto mb-4" />
          <p className="text-gray-600">No {activeTab} reservations</p>
        </div>
      ) : (
        <div className="space-y-4">
          {filteredReservations.map(reservation => {
            const resDate = new Date(reservation.date);
            const isPast = new Date(`${reservation.date}T${reservation.time}`) < new Date();
            
            return (
              <div
                key={reservation.id}
                className="border rounded-xl p-6 hover:shadow-lg transition"
              >
                <div className="flex items-start justify-between mb-4">
                  <div>
                    <h3 className="text-lg">{reservation.restaurantName}</h3>
                    <p className="text-sm text-gray-600 mt-1">{reservation.restaurantAddress}</p>
                  </div>
                  {getStatusBadge(reservation.status)}
                </div>
                
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                  <div className="flex items-center gap-2 text-gray-600">
                    <Users className="w-4 h-4" />
                    <span className="text-sm">{reservation.partySize} {reservation.partySize === 1 ? 'guest' : 'guests'}</span>
                  </div>
                  <div className="flex items-center gap-2 text-gray-600">
                    <CalendarIcon className="w-4 h-4" />
                    <span className="text-sm">
                      {resDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </span>
                  </div>
                  <div className="flex items-center gap-2 text-gray-600">
                    <Clock className="w-4 h-4" />
                    <span className="text-sm">{reservation.time}</span>
                  </div>
                  <div className="text-sm text-gray-500">
                    #{reservation.id.slice(-8)}
                  </div>
                </div>
                
                {reservation.specialRequests && (
                  <div className="mb-4 p-3 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-600">
                      <span className="font-medium">Special Requests: </span>
                      {reservation.specialRequests}
                    </p>
                  </div>
                )}
                
                {reservation.vendorNote && reservation.status === 'declined' && (
                  <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex gap-2">
                    <AlertCircle className="w-4 h-4 text-red-600 mt-0.5 flex-shrink-0" />
                    <p className="text-sm text-red-800">
                      <span className="font-medium">Reason: </span>
                      {reservation.vendorNote}
                    </p>
                  </div>
                )}
                
                <div className="flex gap-3 mt-4">
                  {reservation.status === 'confirmed' && !isPast && (
                    <Button
                      onClick={() => handleCancel(reservation.id)}
                      disabled={cancellingId === reservation.id}
                      variant="outline"
                      className="border-red-200 text-red-600 hover:bg-red-50"
                    >
                      {cancellingId === reservation.id ? 'Cancelling...' : 'Cancel Reservation'}
                    </Button>
                  )}
                  
                  {reservation.status === 'pending' && !isPast && (
                    <Button
                      onClick={() => handleCancel(reservation.id)}
                      disabled={cancellingId === reservation.id}
                      variant="outline"
                    >
                      {cancellingId === reservation.id ? 'Cancelling...' : 'Cancel Request'}
                    </Button>
                  )}
                  
                  {reservation.restaurantAddress && (
                    <Button
                      variant="outline"
                      onClick={() => {
                        const address = encodeURIComponent(reservation.restaurantAddress);
                        window.open(`https://www.google.com/maps/search/?api=1&query=${address}`, '_blank');
                      }}
                    >
                      <MapPin className="w-4 h-4 mr-2" />
                      Directions
                    </Button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
