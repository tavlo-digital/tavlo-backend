import { useState, useEffect } from 'react';
import { X, Clock, Calendar, MapPin, Info } from 'lucide-react';
import { Button } from '../ui/button';
import { api } from '../../utils/api';

interface TakeawayModalProps {
  isOpen: boolean;
  onClose: () => void;
  restaurantId: string;
  restaurantName: string;
  pickupInstructions?: string;
  onConfirm: (pickupData: {
    pickupTime: string;
    scheduledFor: 'asap' | 'scheduled';
    displayTime: string;
  }) => void;
}

export function TakeawayModal({
  isOpen,
  onClose,
  restaurantId,
  restaurantName,
  pickupInstructions = 'Pick up at main counter',
  onConfirm
}: TakeawayModalProps) {
  const [loading, setLoading] = useState(true);
  const [selectedDate, setSelectedDate] = useState<string>('');
  const [selectedTime, setSelectedTime] = useState<string>('');
  const [timeSlots, setTimeSlots] = useState<any[]>([]);
  const [earliestTime, setEarliestTime] = useState<string>('');
  const [prepTime, setPrepTime] = useState<number>(25);
  const [availableDates, setAvailableDates] = useState<Array<{ date: string; label: string }>>([]);

  useEffect(() => {
    if (isOpen) {
      generateAvailableDates();
    }
  }, [isOpen]);

  useEffect(() => {
    if (selectedDate) {
      loadTimeSlots();
    }
  }, [selectedDate]);

  const generateAvailableDates = () => {
    const dates = [];
    const today = new Date();
    
    for (let i = 0; i < 7; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);
      
      const label = i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : 
        date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
      
      dates.push({
        date: date.toISOString().split('T')[0],
        label
      });
    }
    
    setAvailableDates(dates);
    setSelectedDate(dates[0].date);
  };

  const loadTimeSlots = async () => {
    try {
      setLoading(true);
      const result = await api.getAvailablePickupSlots(restaurantId, selectedDate);
      
      setTimeSlots(result.slots || []);
      setEarliestTime(result.earliestTime || '');
      setPrepTime(result.prepTime || 25);
      
      // Auto-select first available slot (ASAP)
      if (result.slots && result.slots.length > 0) {
        setSelectedTime(result.slots[0].time);
      }
    } catch (error) {
      console.error('Failed to load time slots:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleConfirm = () => {
    if (!selectedTime) return;
    
    const isAsap = timeSlots.find(slot => slot.time === selectedTime)?.asap || false;
    const pickupDateTime = new Date(`${selectedDate}T${selectedTime}:00`);
    
    const displayTime = isAsap 
      ? `Today at ${selectedTime} (ASAP)`
      : selectedDate === new Date().toISOString().split('T')[0]
        ? `Today at ${selectedTime}`
        : `${availableDates.find(d => d.date === selectedDate)?.label} at ${selectedTime}`;
    
    onConfirm({
      pickupTime: pickupDateTime.toISOString(),
      scheduledFor: isAsap ? 'asap' : 'scheduled',
      displayTime
    });
    
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
          <div>
            <h2 className="text-xl">🛍️ Choose Pickup Time</h2>
            <p className="text-sm text-gray-600 mt-1">{restaurantName}</p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-full transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 space-y-6">
          {loading ? (
            <div className="text-center py-8">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto mb-4"></div>
              <p className="text-gray-600">Loading available times...</p>
            </div>
          ) : (
            <>
              {/* ASAP Option */}
              {timeSlots.length > 0 && timeSlots[0].asap && (
                <div className="border-2 border-orange-500 rounded-xl p-4 bg-orange-50">
                  <div className="flex items-start gap-3">
                    <div className="flex-shrink-0 w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center">
                      <Clock className="w-5 h-5 text-white" />
                    </div>
                    <div className="flex-1">
                      <h3 className="text-lg mb-1">⚡ ASAP</h3>
                      <p className="text-sm text-gray-700 mb-3">
                        Ready in ~{prepTime} minutes ({earliestTime})
                      </p>
                      <Button
                        onClick={() => {
                          setSelectedTime(earliestTime);
                          setTimeout(handleConfirm, 100);
                        }}
                        className="w-full bg-orange-500 hover:bg-orange-600"
                      >
                        Select ASAP Pickup
                      </Button>
                    </div>
                  </div>
                </div>
              )}

              {/* Schedule for Later */}
              <div className="border rounded-xl p-4">
                <div className="flex items-center gap-2 mb-4">
                  <Calendar className="w-5 h-5 text-gray-600" />
                  <h3 className="text-lg">📅 Schedule for Later</h3>
                </div>

                {/* Date Selection */}
                <div className="mb-4">
                  <label className="text-sm text-gray-600 mb-2 block">Select Date</label>
                  <div className="grid grid-cols-2 gap-2">
                    {availableDates.map((date) => (
                      <button
                        key={date.date}
                        onClick={() => setSelectedDate(date.date)}
                        className={`px-4 py-3 border-2 rounded-lg transition-all ${
                          selectedDate === date.date
                            ? 'border-orange-500 bg-orange-50 font-medium'
                            : 'border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        {date.label}
                      </button>
                    ))}
                  </div>
                </div>

                {/* Time Selection */}
                <div>
                  <label className="text-sm text-gray-600 mb-2 block">Select Time</label>
                  <div className="grid grid-cols-3 gap-2 max-h-64 overflow-y-auto">
                    {timeSlots.map((slot) => (
                      <button
                        key={slot.time}
                        onClick={() => setSelectedTime(slot.time)}
                        disabled={!slot.available}
                        className={`px-3 py-2 border-2 rounded-lg text-sm transition-all ${
                          selectedTime === slot.time
                            ? 'border-orange-500 bg-orange-50 font-medium'
                            : slot.available
                            ? 'border-gray-200 hover:border-gray-300'
                            : 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed'
                        }`}
                      >
                        {slot.time}
                      </button>
                    ))}
                  </div>
                </div>
              </div>

              {/* Pickup Instructions */}
              <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 flex gap-3">
                <MapPin className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-medium text-blue-900 mb-1">Pickup Location</h4>
                  <p className="text-sm text-blue-800">{pickupInstructions}</p>
                </div>
              </div>

              {/* Info Notice */}
              <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 flex gap-3">
                <Info className="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
                <div className="text-sm text-gray-700">
                  <p>• Please arrive within 15 minutes of your pickup time</p>
                  <p>• You'll receive a notification when your order is ready</p>
                  <p>• Bring your order confirmation number</p>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        {!loading && (
          <div className="sticky bottom-0 bg-white border-t px-6 py-4 flex gap-3">
            <Button
              variant="outline"
              onClick={onClose}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button
              onClick={handleConfirm}
              disabled={!selectedTime}
              className="flex-1 bg-orange-500 hover:bg-orange-600"
            >
              Continue to Menu
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}
