import { useState, useEffect } from 'react';
import { X, Calendar as CalendarIcon, Users, Clock, ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Textarea } from './ui/textarea';
import { toast } from 'sonner@2.0.3';
import { api } from '../utils/api';

interface ReservationBookingProps {
  restaurantId: string;
  restaurantName: string;
  onClose: () => void;
  onSuccess: () => void;
  user?: any;
  guestId?: string;
}

export function ReservationBooking({ 
  restaurantId, 
  restaurantName, 
  onClose, 
  onSuccess,
  user,
  guestId
}: ReservationBookingProps) {
  const [step, setStep] = useState<'details' | 'slots' | 'confirm'>('details');
  const [loading, setLoading] = useState(false);
  const [slotsLoading, setSlotsLoading] = useState(false);
  
  // Form data
  const [partySize, setPartySize] = useState(2);
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedTime, setSelectedTime] = useState('');
  const [customerName, setCustomerName] = useState(user?.name || '');
  const [customerEmail, setCustomerEmail] = useState(user?.email || '');
  const [customerPhone, setCustomerPhone] = useState(user?.phone || '');
  const [specialRequests, setSpecialRequests] = useState('');
  
  // Available slots
  const [availableSlots, setAvailableSlots] = useState<any[]>([]);
  
  // Date navigation
  const [currentWeekStart, setCurrentWeekStart] = useState(new Date());
  
  // Initialize dates (today onwards for the next 7 days)
  const [dates, setDates] = useState<Date[]>([]);
  
  useEffect(() => {
    const generateDates = () => {
      const datesArray = [];
      const start = new Date(currentWeekStart);
      for (let i = 0; i < 7; i++) {
        const date = new Date(start);
        date.setDate(start.getDate() + i);
        datesArray.push(date);
      }
      setDates(datesArray);
      
      // Auto-select first date if none selected
      if (!selectedDate && datesArray.length > 0) {
        setSelectedDate(datesArray[0].toISOString().split('T')[0]);
      }
    };
    
    generateDates();
  }, [currentWeekStart]);
  
  const handlePrevWeek = () => {
    const newStart = new Date(currentWeekStart);
    newStart.setDate(newStart.getDate() - 7);
    
    // Don't go before today
    if (newStart >= new Date(new Date().setHours(0, 0, 0, 0))) {
      setCurrentWeekStart(newStart);
    }
  };
  
  const handleNextWeek = () => {
    const newStart = new Date(currentWeekStart);
    newStart.setDate(newStart.getDate() + 7);
    setCurrentWeekStart(newStart);
  };
  
  const fetchSlots = async () => {
    if (!selectedDate || partySize < 1) return;
    
    setSlotsLoading(true);
    try {
      const result = await api.getAvailableSlots(restaurantId, selectedDate, partySize);
      setAvailableSlots(result.slots || []);
    } catch (error) {
      console.error('Error fetching slots:', error);
      toast.error('Failed to load available time slots');
    } finally {
      setSlotsLoading(false);
    }
  };
  
  useEffect(() => {
    if (step === 'slots' && selectedDate) {
      fetchSlots();
    }
  }, [step, selectedDate, partySize]);
  
  const handleContinue = () => {
    if (!partySize || partySize < 1) {
      toast.error('Please select number of guests');
      return;
    }
    if (!selectedDate) {
      toast.error('Please select a date');
      return;
    }
    setStep('slots');
  };
  
  const handleSelectTime = (time: string) => {
    setSelectedTime(time);
    setStep('confirm');
  };
  
  const handleSubmit = async () => {
    if (!customerName.trim()) {
      toast.error('Please enter your name');
      return;
    }
    
    setLoading(true);
    try {
      await api.createReservation({
        restaurantId,
        customerId: user?.id || null,
        customerName: customerName.trim(),
        customerEmail: customerEmail.trim(),
        customerPhone: customerPhone.trim(),
        date: selectedDate,
        time: selectedTime,
        partySize,
        specialRequests: specialRequests.trim(),
        isGuest: !user
      });
      
      toast.success('Reservation request submitted! You\'ll be notified once confirmed.');
      onSuccess();
    } catch (error: any) {
      console.error('Error creating reservation:', error);
      toast.error(error.message || 'Failed to create reservation');
    } finally {
      setLoading(false);
    }
  };
  
  const formatDate = (date: Date) => {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    if (date.toDateString() === today.toDateString()) {
      return 'Today';
    } else if (date.toDateString() === tomorrow.toDateString()) {
      return 'Tomorrow';
    } else {
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
  };
  
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-white border-b p-6 flex items-center justify-between">
          <div>
            <h2 className="text-2xl">Reserve a Table</h2>
            <p className="text-gray-600 mt-1">{restaurantName}</p>
          </div>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full transition">
            <X className="w-6 h-6" />
          </button>
        </div>
        
        {/* Step Indicator */}
        <div className="px-6 py-4 border-b">
          <div className="flex items-center gap-2">
            <div className={`flex items-center justify-center w-8 h-8 rounded-full ${step === 'details' ? 'bg-orange-500 text-white' : 'bg-green-500 text-white'}`}>
              {step === 'details' ? '1' : '✓'}
            </div>
            <div className={`flex-1 h-1 ${step !== 'details' ? 'bg-green-500' : 'bg-gray-200'}`} />
            <div className={`flex items-center justify-center w-8 h-8 rounded-full ${step === 'slots' ? 'bg-orange-500 text-white' : step === 'confirm' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600'}`}>
              {step === 'confirm' ? '✓' : '2'}
            </div>
            <div className={`flex-1 h-1 ${step === 'confirm' ? 'bg-green-500' : 'bg-gray-200'}`} />
            <div className={`flex items-center justify-center w-8 h-8 rounded-full ${step === 'confirm' ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-600'}`}>
              3
            </div>
          </div>
          <div className="flex justify-between mt-2 text-sm">
            <span className={step === 'details' ? 'text-orange-500' : 'text-gray-600'}>Details</span>
            <span className={step === 'slots' ? 'text-orange-500' : 'text-gray-600'}>Time</span>
            <span className={step === 'confirm' ? 'text-orange-500' : 'text-gray-600'}>Confirm</span>
          </div>
        </div>
        
        {/* Content */}
        <div className="p-6">
          {/* Step 1: Party Size & Date */}
          {step === 'details' && (
            <div className="space-y-6">
              <div>
                <Label className="text-gray-600">Party Size</Label>
                <div className="mt-2 flex items-center gap-3">
                  <select
                    value={partySize}
                    onChange={(e) => setPartySize(Number(e.target.value))}
                    className="flex-1 px-4 py-3 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                  >
                    {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(num => (
                      <option key={num} value={num}>
                        {num} {num === 1 ? 'guest' : 'guests'}
                      </option>
                    ))}
                  </select>
                  <div className="p-3 bg-gray-100 rounded-lg">
                    <Users className="w-6 h-6 text-gray-600" />
                  </div>
                </div>
              </div>
              
              <div>
                <Label className="text-gray-600">Select Date</Label>
                <div className="mt-2">
                  {/* Week Navigation */}
                  <div className="flex items-center justify-between mb-3">
                    <button
                      onClick={handlePrevWeek}
                      disabled={currentWeekStart.toDateString() === new Date(new Date().setHours(0, 0, 0, 0)).toDateString()}
                      className="p-2 hover:bg-gray-100 rounded-lg disabled:opacity-30 disabled:cursor-not-allowed"
                    >
                      <ChevronLeft className="w-5 h-5" />
                    </button>
                    <span className="text-sm text-gray-600">
                      {dates[0]?.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                    </span>
                    <button
                      onClick={handleNextWeek}
                      className="p-2 hover:bg-gray-100 rounded-lg"
                    >
                      <ChevronRight className="w-5 h-5" />
                    </button>
                  </div>
                  
                  {/* Date Grid */}
                  <div className="grid grid-cols-7 gap-2">
                    {dates.map((date) => {
                      const dateStr = date.toISOString().split('T')[0];
                      const isSelected = selectedDate === dateStr;
                      const isToday = date.toDateString() === new Date().toDateString();
                      
                      return (
                        <button
                          key={dateStr}
                          onClick={() => setSelectedDate(dateStr)}
                          className={`
                            p-3 rounded-lg border-2 text-center transition
                            ${isSelected ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-gray-300'}
                          `}
                        >
                          <div className="text-xs text-gray-600 mb-1">
                            {date.toLocaleDateString('en-US', { weekday: 'short' })}
                          </div>
                          <div className={`${isSelected ? 'text-orange-500' : 'text-gray-900'}`}>
                            {date.getDate()}
                          </div>
                          {isToday && (
                            <div className="text-xs text-orange-500 mt-1">Today</div>
                          )}
                        </button>
                      );
                    })}
                  </div>
                </div>
              </div>
              
              <Button
                onClick={handleContinue}
                className="w-full bg-orange-500 hover:bg-orange-600 text-white py-6"
              >
                Continue
              </Button>
            </div>
          )}
          
          {/* Step 2: Time Slots */}
          {step === 'slots' && (
            <div className="space-y-6">
              <div className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                <Users className="w-5 h-5 text-gray-600" />
                <span>{partySize} {partySize === 1 ? 'guest' : 'guests'}</span>
                <CalendarIcon className="w-5 h-5 text-gray-600 ml-auto" />
                <span>{formatDate(new Date(selectedDate))}</span>
                <button 
                  onClick={() => setStep('details')}
                  className="ml-2 text-orange-500 text-sm hover:underline"
                >
                  Change
                </button>
              </div>
              
              {slotsLoading ? (
                <div className="text-center py-12">
                  <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto"></div>
                  <p className="mt-4 text-gray-600">Loading available times...</p>
                </div>
              ) : availableSlots.length === 0 ? (
                <div className="text-center py-12">
                  <Clock className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                  <p className="text-gray-600">No available times for this date</p>
                  <Button
                    onClick={() => setStep('details')}
                    className="mt-4 bg-gray-200 hover:bg-gray-300 text-gray-800"
                  >
                    Select Different Date
                  </Button>
                </div>
              ) : (
                <div>
                  <Label className="text-gray-600">Available Times</Label>
                  <div className="mt-3 grid grid-cols-3 gap-3">
                    {availableSlots.map((slot) => (
                      <button
                        key={slot.time}
                        onClick={() => slot.available && handleSelectTime(slot.time)}
                        disabled={!slot.available}
                        className={`
                          p-4 rounded-lg border-2 text-center transition
                          ${slot.available 
                            ? 'border-gray-200 hover:border-orange-500 hover:bg-orange-50 cursor-pointer' 
                            : 'border-gray-100 bg-gray-100 text-gray-400 cursor-not-allowed'
                          }
                        `}
                      >
                        <div className={slot.available ? 'text-orange-500' : 'text-gray-400'}>
                          {slot.time}
                        </div>
                        {!slot.available && (
                          <div className="text-xs text-gray-400 mt-1">Full</div>
                        )}
                      </button>
                    ))}
                  </div>
                </div>
              )}
              
              <Button
                onClick={() => setStep('details')}
                variant="outline"
                className="w-full"
              >
                Back
              </Button>
            </div>
          )}
          
          {/* Step 3: Confirm Details */}
          {step === 'confirm' && (
            <div className="space-y-6">
              <div className="p-4 bg-orange-50 border-2 border-orange-200 rounded-lg">
                <div className="flex items-center gap-3 mb-3">
                  <Users className="w-5 h-5 text-orange-600" />
                  <span>{partySize} {partySize === 1 ? 'guest' : 'guests'}</span>
                </div>
                <div className="flex items-center gap-3 mb-3">
                  <CalendarIcon className="w-5 h-5 text-orange-600" />
                  <span>{new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}</span>
                </div>
                <div className="flex items-center gap-3">
                  <Clock className="w-5 h-5 text-orange-600" />
                  <span>{selectedTime}</span>
                </div>
                <button 
                  onClick={() => setStep('slots')}
                  className="mt-3 text-orange-500 text-sm hover:underline"
                >
                  Change time
                </button>
              </div>
              
              <div className="space-y-4">
                <div>
                  <Label>Name *</Label>
                  <Input
                    value={customerName}
                    onChange={(e) => setCustomerName(e.target.value)}
                    placeholder="Your full name"
                    className="mt-1"
                  />
                </div>
                
                <div>
                  <Label>Email (Optional)</Label>
                  <Input
                    type="email"
                    value={customerEmail}
                    onChange={(e) => setCustomerEmail(e.target.value)}
                    placeholder="your@email.com"
                    className="mt-1"
                  />
                  <p className="text-xs text-gray-500 mt-1">For confirmation updates</p>
                </div>
                
                <div>
                  <Label>Phone (Optional)</Label>
                  <Input
                    type="tel"
                    value={customerPhone}
                    onChange={(e) => setCustomerPhone(e.target.value)}
                    placeholder="+43 123 456 7890"
                    className="mt-1"
                  />
                </div>
                
                <div>
                  <Label>Special Requests (Optional)</Label>
                  <Textarea
                    value={specialRequests}
                    onChange={(e) => setSpecialRequests(e.target.value)}
                    placeholder="Any dietary requirements, preferences, or special occasions..."
                    className="mt-1 h-24"
                  />
                </div>
              </div>
              
              <div className="flex gap-3">
                <Button
                  onClick={() => setStep('slots')}
                  variant="outline"
                  className="flex-1"
                  disabled={loading}
                >
                  Back
                </Button>
                <Button
                  onClick={handleSubmit}
                  className="flex-1 bg-orange-500 hover:bg-orange-600 text-white"
                  disabled={loading}
                >
                  {loading ? 'Submitting...' : 'Confirm Reservation'}
                </Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
