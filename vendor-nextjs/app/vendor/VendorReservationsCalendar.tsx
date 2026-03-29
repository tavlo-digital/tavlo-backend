import { useState, useEffect } from 'react';
import { ChevronLeft, ChevronRight, Calendar as CalendarIcon, Users, Clock, Phone, Mail, CheckCircle, XCircle, AlertCircle, MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface VendorReservationsCalendarProps {
  vendorId: string;
}

export function VendorReservationsCalendar({ vendorId }: VendorReservationsCalendarProps) {
  const [currentDate, setCurrentDate] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [reservations, setReservations] = useState<any[]>([]);
  const [allMonthReservations, setAllMonthReservations] = useState<any[]>([]);
  const [upcomingReservations, setUpcomingReservations] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [selectedReservation, setSelectedReservation] = useState<any | null>(null);
  const [vendorNote, setVendorNote] = useState('');
  const [processing, setProcessing] = useState(false);
  const [viewMode, setViewMode] = useState<'calendar' | 'list'>('calendar');
  
  const loadReservations = async (date: Date) => {
    setLoading(true);
    try {
      const dateStr = date.toISOString().split('T')[0];
      const data = await api.getVendorReservations(vendorId, undefined, dateStr);
      setReservations(data as any);
    } catch (error) {
      console.error('Error loading reservations:', error);
      toast.error('Failed to load reservations');
    } finally {
      setLoading(false);
    }
  };

  const loadMonthReservations = async (date: Date) => {
    try {
      // Get first and last day of month
      const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
      const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
      
      // Fetch all reservations for the month
      const data = await api.getVendorReservations(vendorId) as any[];
      
      // Filter to current month
      const monthData = data.filter((res: any) => {
        const resDate = new Date(res.date);
        return resDate >= firstDay && resDate <= lastDay;
      });
      
      setAllMonthReservations(monthData);
    } catch (error) {
      console.error('Error loading month reservations:', error);
    }
  };

  const loadUpcomingReservations = async () => {
    try {
      const data = await api.getVendorReservations(vendorId, 'pending') as any[];
      const confirmed = await api.getVendorReservations(vendorId, 'confirmed') as any[];
      
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      const upcoming = [...data, ...confirmed]
        .filter((res: any) => new Date(res.date) >= today)
        .sort((a: any, b: any) => {
          const dateCompare = new Date(a.date).getTime() - new Date(b.date).getTime();
          if (dateCompare !== 0) return dateCompare;
          return a.time.localeCompare(b.time);
        })
        .slice(0, 10); // Show next 10 upcoming
      
      setUpcomingReservations(upcoming);
    } catch (error) {
      console.error('Error loading upcoming reservations:', error);
    }
  };
  
  useEffect(() => {
    loadReservations(selectedDate);
  }, [selectedDate, vendorId]);

  useEffect(() => {
    loadMonthReservations(currentDate);
    loadUpcomingReservations();
    
    // Refresh every 30 seconds
    const interval = setInterval(() => {
      loadMonthReservations(currentDate);
      loadUpcomingReservations();
    }, 30000);
    
    return () => clearInterval(interval);
  }, [currentDate, vendorId]);
  
  const getDaysInMonth = (date: Date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    const days = [];
    
    // Add empty cells for days before the first of the month
    for (let i = 0; i < startingDayOfWeek; i++) {
      days.push(null);
    }
    
    // Add all days of the month
    for (let day = 1; day <= daysInMonth; day++) {
      days.push(new Date(year, month, day));
    }
    
    return days;
  };
  
  const handlePrevMonth = () => {
    setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1));
  };
  
  const handleNextMonth = () => {
    setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1));
  };
  
  const handleStatusUpdate = async (reservationId: string, status: 'confirmed' | 'declined') => {
    setProcessing(true);
    try {
      await api.updateReservationStatus(reservationId, status, vendorNote.trim());
      toast.success(`Reservation ${status}!`);
      setSelectedReservation(null);
      setVendorNote('');
      await loadReservations(selectedDate);
      await loadMonthReservations(currentDate);
      await loadUpcomingReservations();
    } catch (error: any) {
      console.error('Error updating reservation:', error);
      toast.error(error.message || 'Failed to update reservation');
    } finally {
      setProcessing(false);
    }
  };
  
  const getReservationCountForDate = (date: Date) => {
    const dateStr = date.toISOString().split('T')[0];
    return allMonthReservations.filter(res => res.date === dateStr).length;
  };

  const getPendingCountForDate = (date: Date) => {
    const dateStr = date.toISOString().split('T')[0];
    return allMonthReservations.filter(res => res.date === dateStr && res.status === 'pending').length;
  };
  
  const days = getDaysInMonth(currentDate);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'confirmed': return 'bg-green-100 text-green-800 border-green-200';
      case 'declined': return 'bg-red-100 text-red-800 border-red-200';
      case 'cancelled': return 'bg-gray-100 text-gray-800 border-gray-200';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'pending': return <AlertCircle className="w-4 h-4" />;
      case 'confirmed': return <CheckCircle className="w-4 h-4" />;
      case 'declined': return <XCircle className="w-4 h-4" />;
      case 'cancelled': return <XCircle className="w-4 h-4" />;
      default: return null;
    }
  };
  
  // Group reservations by time
  const groupedReservations = reservations.reduce((acc, res) => {
    if (!acc[res.time]) {
      acc[res.time] = [];
    }
    acc[res.time].push(res);
    return acc;
  }, {} as Record<string, any[]>);
  
  const sortedTimes = Object.keys(groupedReservations).sort();

  const todayReservations = upcomingReservations.filter(res => res.date === today.toISOString().split('T')[0]);
  const tomorrowReservations = upcomingReservations.filter(res => res.date === tomorrow.toISOString().split('T')[0]);
  
  return (
    <div className="space-y-6">
      {/* Quick Stats & View Toggle */}
      <div className="flex items-center justify-between">
        <div className="flex gap-3">
          <div className="bg-yellow-50 border border-yellow-200 px-4 py-2 rounded-lg">
            <div className="text-xs text-yellow-600 mb-1">Pending</div>
            <div className="text-xl text-yellow-800">
              {upcomingReservations.filter(r => r.status === 'pending').length}
            </div>
          </div>
          <div className="bg-green-50 border border-green-200 px-4 py-2 rounded-lg">
            <div className="text-xs text-green-600 mb-1">Confirmed</div>
            <div className="text-xl text-green-800">
              {upcomingReservations.filter(r => r.status === 'confirmed').length}
            </div>
          </div>
          <div className="bg-blue-50 border border-blue-200 px-4 py-2 rounded-lg">
            <div className="text-xs text-blue-600 mb-1">Total Upcoming</div>
            <div className="text-xl text-blue-800">
              {upcomingReservations.length}
            </div>
          </div>
        </div>

        <div className="flex gap-2 bg-gray-100 p-1 rounded-lg">
          <button
            onClick={() => setViewMode('calendar')}
            className={`px-4 py-2 rounded-md text-sm transition ${
              viewMode === 'calendar' 
                ? 'bg-white shadow-sm' 
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            Calendar
          </button>
          <button
            onClick={() => setViewMode('list')}
            className={`px-4 py-2 rounded-md text-sm transition ${
              viewMode === 'list' 
                ? 'bg-white shadow-sm' 
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            List View
          </button>
        </div>
      </div>

      {/* Today & Tomorrow Quick View */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Today's Reservations */}
        <div className="bg-orange-50 border-2 border-orange-200 rounded-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="font-semibold text-orange-900 flex items-center gap-2">
              <CalendarIcon className="w-5 h-5" />
              Today's Reservations
            </h3>
            <span className="bg-orange-200 text-orange-900 px-2 py-1 rounded-full text-sm">
              {todayReservations.length}
            </span>
          </div>
          
          {todayReservations.length === 0 ? (
            <p className="text-sm text-orange-700">No reservations for today</p>
          ) : (
            <div className="space-y-2 max-h-48 overflow-y-auto">
              {todayReservations.map((res) => (
                <div
                  key={res.id}
                  onClick={() => setSelectedReservation(res)}
                  className="bg-white p-3 rounded-lg cursor-pointer hover:shadow-md transition"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Clock className="w-4 h-4 text-gray-500" />
                      <span className="font-medium">{res.time}</span>
                    </div>
                    <span className={`px-2 py-0.5 rounded-full text-xs border ${getStatusColor(res.status)}`}>
                      {res.status}
                    </span>
                  </div>
                  <div className="mt-1 text-sm text-gray-600 flex items-center gap-2">
                    <Users className="w-3 h-3" />
                    {res.partySize} guests - {res.customerName}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Tomorrow's Reservations */}
        <div className="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="font-semibold text-blue-900 flex items-center gap-2">
              <CalendarIcon className="w-5 h-5" />
              Tomorrow's Reservations
            </h3>
            <span className="bg-blue-200 text-blue-900 px-2 py-1 rounded-full text-sm">
              {tomorrowReservations.length}
            </span>
          </div>
          
          {tomorrowReservations.length === 0 ? (
            <p className="text-sm text-blue-700">No reservations for tomorrow</p>
          ) : (
            <div className="space-y-2 max-h-48 overflow-y-auto">
              {tomorrowReservations.map((res) => (
                <div
                  key={res.id}
                  onClick={() => setSelectedReservation(res)}
                  className="bg-white p-3 rounded-lg cursor-pointer hover:shadow-md transition"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Clock className="w-4 h-4 text-gray-500" />
                      <span className="font-medium">{res.time}</span>
                    </div>
                    <span className={`px-2 py-0.5 rounded-full text-xs border ${getStatusColor(res.status)}`}>
                      {res.status}
                    </span>
                  </div>
                  <div className="mt-1 text-sm text-gray-600 flex items-center gap-2">
                    <Users className="w-3 h-3" />
                    {res.partySize} guests - {res.customerName}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {viewMode === 'calendar' ? (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Calendar */}
          <div className="lg:col-span-2">
            <div className="bg-white rounded-xl border p-6">
              {/* Calendar Header */}
              <div className="flex items-center justify-between mb-6">
                <h3 className="text-xl">
                  {currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                </h3>
                <div className="flex gap-2">
                  <button
                    onClick={handlePrevMonth}
                    className="p-2 hover:bg-gray-100 rounded-lg"
                  >
                    <ChevronLeft className="w-5 h-5" />
                  </button>
                  <button
                    onClick={() => {
                      setCurrentDate(new Date());
                      setSelectedDate(new Date());
                    }}
                    className="px-4 py-2 hover:bg-gray-100 rounded-lg text-sm"
                  >
                    Today
                  </button>
                  <button
                    onClick={handleNextMonth}
                    className="p-2 hover:bg-gray-100 rounded-lg"
                  >
                    <ChevronRight className="w-5 h-5" />
                  </button>
                </div>
              </div>

              {/* Day Headers */}
              <div className="grid grid-cols-7 gap-2 mb-2">
                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => (
                  <div key={day} className="text-center text-sm text-gray-600 py-2">
                    {day}
                  </div>
                ))}
              </div>

              {/* Calendar Grid */}
              <div className="grid grid-cols-7 gap-2">
                {days.map((day, index) => {
                  if (!day) {
                    return <div key={`empty-${index}`} className="aspect-square" />;
                  }

                  const isToday = day.toDateString() === today.toDateString();
                  const isSelected = day.toDateString() === selectedDate.toDateString();
                  const isPast = day < today;
                  const reservationCount = getReservationCountForDate(day);
                  const pendingCount = getPendingCountForDate(day);

                  return (
                    <button
                      key={day.toISOString()}
                      onClick={() => setSelectedDate(day)}
                      disabled={isPast}
                      className={`
                        aspect-square p-2 rounded-lg border-2 transition relative
                        ${isSelected 
                          ? 'border-orange-500 bg-orange-50' 
                          : 'border-transparent hover:border-gray-300'
                        }
                        ${isToday && !isSelected ? 'border-orange-300' : ''}
                        ${isPast ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'}
                      `}
                    >
                      <div className="flex flex-col items-center justify-center h-full">
                        <span className={`text-sm ${isToday ? 'font-bold' : ''}`}>
                          {day.getDate()}
                        </span>
                        
                        {reservationCount > 0 && (
                          <div className="flex items-center gap-1 mt-1">
                            <div className="flex gap-0.5">
                              {pendingCount > 0 && (
                                <div className="w-1.5 h-1.5 bg-yellow-500 rounded-full" />
                              )}
                              <div className="w-1.5 h-1.5 bg-orange-500 rounded-full" />
                            </div>
                            <span className="text-xs font-medium text-orange-600">
                              {reservationCount}
                            </span>
                          </div>
                        )}
                      </div>
                    </button>
                  );
                })}
              </div>

              {/* Legend */}
              <div className="mt-6 pt-4 border-t flex items-center gap-4 text-xs text-gray-600">
                <div className="flex items-center gap-1">
                  <div className="w-3 h-3 bg-orange-500 rounded-full" />
                  <span>Has reservations</span>
                </div>
                <div className="flex items-center gap-1">
                  <div className="w-3 h-3 bg-yellow-500 rounded-full" />
                  <span>Pending requests</span>
                </div>
              </div>
            </div>
          </div>

          {/* Selected Day Details */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-xl border p-6 sticky top-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-semibold">
                  {selectedDate.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric',
                    year: 'numeric'
                  })}
                </h3>
                {reservations.length > 0 && (
                  <span className="bg-gray-100 px-2 py-1 rounded-full text-sm">
                    {reservations.length} reservations
                  </span>
                )}
              </div>

              {loading ? (
                <div className="text-center py-8 text-gray-500">Loading...</div>
              ) : reservations.length === 0 ? (
                <div className="text-center py-8">
                  <CalendarIcon className="w-12 h-12 text-gray-300 mx-auto mb-2" />
                  <p className="text-gray-500 text-sm">No reservations</p>
                </div>
              ) : (
                <div className="space-y-4 max-h-[600px] overflow-y-auto">
                  {sortedTimes.map((time) => (
                    <div key={time}>
                      <div className="text-sm text-gray-600 mb-2 flex items-center gap-2">
                        <Clock className="w-4 h-4" />
                        {time}
                      </div>
                      <div className="space-y-2 ml-6">
                        {groupedReservations[time].map((reservation: any) => (
                          <div
                            key={reservation.id}
                            onClick={() => setSelectedReservation(reservation)}
                            className={`p-3 rounded-lg border cursor-pointer hover:shadow-md transition ${
                              getStatusColor(reservation.status)
                            }`}
                          >
                            <div className="flex items-start justify-between">
                              <div className="flex-1">
                                <div className="flex items-center gap-2 mb-1">
                                  {getStatusIcon(reservation.status)}
                                  <span className="font-medium text-sm">
                                    {reservation.customerName}
                                  </span>
                                </div>
                                <div className="flex items-center gap-2 text-xs">
                                  <Users className="w-3 h-3" />
                                  {reservation.partySize} guests
                                </div>
                              </div>
                              <span className="text-xs capitalize">{reservation.status}</span>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      ) : (
        /* List View */
        <div className="bg-white rounded-xl border p-6">
          <h3 className="text-xl mb-4">Upcoming Reservations</h3>
          
          {upcomingReservations.length === 0 ? (
            <div className="text-center py-12">
              <CalendarIcon className="w-16 h-16 text-gray-300 mx-auto mb-3" />
              <p className="text-gray-500">No upcoming reservations</p>
            </div>
          ) : (
            <div className="space-y-3">
              {upcomingReservations.map((reservation) => {
                const resDate = new Date(reservation.date);
                const isToday = resDate.toDateString() === today.toDateString();
                const isTomorrow = resDate.toDateString() === tomorrow.toDateString();
                
                return (
                  <div
                    key={reservation.id}
                    onClick={() => setSelectedReservation(reservation)}
                    className="p-4 border rounded-xl hover:shadow-lg transition cursor-pointer"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <span className={`px-3 py-1 rounded-full text-sm border ${getStatusColor(reservation.status)}`}>
                            {reservation.status}
                          </span>
                          {isToday && (
                            <span className="bg-orange-100 text-orange-700 px-2 py-1 rounded-full text-xs">
                              Today
                            </span>
                          )}
                          {isTomorrow && (
                            <span className="bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs">
                              Tomorrow
                            </span>
                          )}
                        </div>
                        
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
                          <div>
                            <div className="text-xs text-gray-500 mb-1">Customer</div>
                            <div className="font-medium">{reservation.customerName}</div>
                          </div>
                          <div>
                            <div className="text-xs text-gray-500 mb-1">Date</div>
                            <div className="font-medium">
                              {new Date(reservation.date).toLocaleDateString('en-US', { 
                                month: 'short', 
                                day: 'numeric' 
                              })}
                            </div>
                          </div>
                          <div>
                            <div className="text-xs text-gray-500 mb-1">Time</div>
                            <div className="font-medium">{reservation.time}</div>
                          </div>
                          <div>
                            <div className="text-xs text-gray-500 mb-1">Party Size</div>
                            <div className="font-medium flex items-center gap-1">
                              <Users className="w-4 h-4" />
                              {reservation.partySize} guests
                            </div>
                          </div>
                        </div>

                        {reservation.specialRequests && (
                          <div className="mt-3 p-2 bg-gray-50 rounded text-sm text-gray-600">
                            <span className="font-medium">Note:</span> {reservation.specialRequests}
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Reservation Detail Modal */}
      {selectedReservation && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6">
              <div className="flex items-start justify-between mb-4">
                <div>
                  <h3 className="text-xl mb-1">Reservation Details</h3>
                  <span className={`inline-block px-3 py-1 rounded-full text-sm border ${
                    getStatusColor(selectedReservation.status)
                  }`}>
                    {selectedReservation.status}
                  </span>
                </div>
                <button
                  onClick={() => {
                    setSelectedReservation(null);
                    setVendorNote('');
                  }}
                  className="p-2 hover:bg-gray-100 rounded-lg"
                >
                  <XCircle className="w-5 h-5" />
                </button>
              </div>

              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Customer Name</div>
                    <div className="font-medium">{selectedReservation.customerName}</div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Party Size</div>
                    <div className="font-medium flex items-center gap-1">
                      <Users className="w-4 h-4" />
                      {selectedReservation.partySize} guests
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Date</div>
                    <div className="font-medium">
                      {new Date(selectedReservation.date).toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                      })}
                    </div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Time</div>
                    <div className="font-medium flex items-center gap-1">
                      <Clock className="w-4 h-4" />
                      {selectedReservation.time}
                    </div>
                  </div>
                </div>

                {selectedReservation.email && (
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Email</div>
                    <a
                      href={`mailto:${selectedReservation.email}`}
                      className="text-orange-600 hover:underline flex items-center gap-1"
                    >
                      <Mail className="w-4 h-4" />
                      {selectedReservation.email}
                    </a>
                  </div>
                )}

                {selectedReservation.phone && (
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Phone</div>
                    <a
                      href={`tel:${selectedReservation.phone}`}
                      className="text-orange-600 hover:underline flex items-center gap-1"
                    >
                      <Phone className="w-4 h-4" />
                      {selectedReservation.phone}
                    </a>
                  </div>
                )}

                {selectedReservation.specialRequests && (
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Special Requests</div>
                    <div className="p-3 bg-gray-50 rounded-lg">
                      {selectedReservation.specialRequests}
                    </div>
                  </div>
                )}

                {selectedReservation.vendorNote && (
                  <div>
                    <div className="text-sm text-gray-600 mb-1">Your Note</div>
                    <div className="p-3 bg-blue-50 rounded-lg border border-blue-200">
                      {selectedReservation.vendorNote}
                    </div>
                  </div>
                )}

                {selectedReservation.status === 'pending' && (
                  <>
                    <div>
                      <label className="block text-sm text-gray-600 mb-2">
                        Add a note (optional)
                      </label>
                      <Textarea
                        value={vendorNote}
                        onChange={(e) => setVendorNote(e.target.value)}
                        placeholder="Add a note for the customer..."
                        rows={3}
                        className="w-full"
                      />
                    </div>

                    <div className="flex gap-3">
                      <Button
                        onClick={() => handleStatusUpdate(selectedReservation.id, 'confirmed')}
                        disabled={processing}
                        className="flex-1 bg-green-600 hover:bg-green-700"
                      >
                        <CheckCircle className="w-4 h-4 mr-2" />
                        {processing ? 'Processing...' : 'Confirm'}
                      </Button>
                      <Button
                        onClick={() => handleStatusUpdate(selectedReservation.id, 'declined')}
                        disabled={processing}
                        variant="outline"
                        className="flex-1 border-red-600 text-red-600 hover:bg-red-50"
                      >
                        <XCircle className="w-4 h-4 mr-2" />
                        Decline
                      </Button>
                    </div>
                  </>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
