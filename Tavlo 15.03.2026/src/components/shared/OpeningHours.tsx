import { useState } from 'react';
import { Clock, ChevronDown, ChevronUp } from 'lucide-react';

interface OpeningHoursProps {
  hours: {
    monday: { open: string; close: string; closed?: boolean };
    tuesday: { open: string; close: string; closed?: boolean };
    wednesday: { open: string; close: string; closed?: boolean };
    thursday: { open: string; close: string; closed?: boolean };
    friday: { open: string; close: string; closed?: boolean };
    saturday: { open: string; close: string; closed?: boolean };
    sunday: { open: string; close: string; closed?: boolean };
  };
  className?: string;
  showInline?: boolean;
}

export function OpeningHours({ hours, className = '', showInline = true }: OpeningHoursProps) {
  const [showAllHours, setShowAllHours] = useState(false);

  const daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
  const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  // Get current day status
  const getCurrentStatus = () => {
    const now = new Date();
    const currentDay = daysOfWeek[now.getDay() === 0 ? 6 : now.getDay() - 1]; // Adjust for Sunday
    const currentTime = now.getHours() * 60 + now.getMinutes();
    const todayHours = hours[currentDay as keyof typeof hours];

    if (todayHours.closed) {
      // Find next open day
      for (let i = 1; i <= 7; i++) {
        const nextDayIndex = (daysOfWeek.indexOf(currentDay) + i) % 7;
        const nextDay = daysOfWeek[nextDayIndex];
        const nextDayHours = hours[nextDay as keyof typeof hours];
        
        if (!nextDayHours.closed) {
          const dayName = i === 1 ? 'tomorrow' : dayNames[nextDayIndex];
          return {
            isOpen: false,
            text: `Closed · Opens ${dayName} at ${nextDayHours.open}`,
            color: 'text-red-600',
            bgColor: 'bg-red-50',
            borderColor: 'border-red-200'
          };
        }
      }
      return {
        isOpen: false,
        text: 'Closed',
        color: 'text-red-600',
        bgColor: 'bg-red-50',
        borderColor: 'border-red-200'
      };
    }

    const [openHour, openMin] = todayHours.open.split(':').map(Number);
    const [closeHour, closeMin] = todayHours.close.split(':').map(Number);
    const openTime = openHour * 60 + openMin;
    const closeTime = closeHour * 60 + closeMin;

    if (currentTime >= openTime && currentTime < closeTime) {
      // Currently open
      const closingSoon = closeTime - currentTime < 60; // Less than 1 hour
      return {
        isOpen: true,
        text: closingSoon 
          ? `Open · Closes soon at ${todayHours.close}`
          : `Open now · Closes at ${todayHours.close}`,
        color: closingSoon ? 'text-orange-600' : 'text-green-600',
        bgColor: closingSoon ? 'bg-orange-50' : 'bg-green-50',
        borderColor: closingSoon ? 'border-orange-200' : 'border-green-200'
      };
    } else if (currentTime < openTime) {
      // Opens later today
      return {
        isOpen: false,
        text: `Closed · Opens today at ${todayHours.open}`,
        color: 'text-orange-600',
        bgColor: 'bg-orange-50',
        borderColor: 'border-orange-200'
      };
    } else {
      // Closed for today, opens tomorrow
      const tomorrowIndex = (daysOfWeek.indexOf(currentDay) + 1) % 7;
      const tomorrow = daysOfWeek[tomorrowIndex];
      const tomorrowHours = hours[tomorrow as keyof typeof hours];
      
      if (tomorrowHours.closed) {
        return {
          isOpen: false,
          text: 'Closed',
          color: 'text-red-600',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-200'
        };
      }
      
      return {
        isOpen: false,
        text: `Closed · Opens tomorrow at ${tomorrowHours.open}`,
        color: 'text-red-600',
        bgColor: 'bg-red-50',
        borderColor: 'border-red-200'
      };
    }
  };

  const status = getCurrentStatus();
  const currentDayIndex = new Date().getDay() === 0 ? 6 : new Date().getDay() - 1;

  if (!showInline) {
    // Full week view
    return (
      <div className={className}>
        <h3 className="flex items-center gap-2 mb-3">
          <Clock className="w-5 h-5" />
          Opening Hours
        </h3>
        <div className="space-y-2">
          {daysOfWeek.map((day, index) => {
            const dayHours = hours[day as keyof typeof hours];
            const isToday = index === currentDayIndex;
            
            return (
              <div
                key={day}
                className={`flex justify-between py-2 px-3 rounded-lg ${
                  isToday ? 'bg-orange-50 border border-orange-200' : 'bg-gray-50'
                }`}
              >
                <span className={isToday ? 'font-medium' : ''}>
                  {dayNames[index]}
                  {isToday && <span className="text-xs text-orange-600 ml-2">(Today)</span>}
                </span>
                <span className={dayHours.closed ? 'text-red-600' : ''}>
                  {dayHours.closed ? 'Closed' : `${dayHours.open} - ${dayHours.close}`}
                </span>
              </div>
            );
          })}
        </div>
      </div>
    );
  }

  // Inline compact view with dropdown
  return (
    <div className={className}>
      <div
        className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full border ${status.bgColor} ${status.borderColor}`}
      >
        <Clock className={`w-4 h-4 ${status.color}`} />
        <span className={`text-sm font-medium ${status.color}`}>
          {status.text}
        </span>
        <button
          onClick={() => setShowAllHours(!showAllHours)}
          className="ml-1 hover:opacity-70 transition"
        >
          {showAllHours ? (
            <ChevronUp className="w-4 h-4" />
          ) : (
            <ChevronDown className="w-4 h-4" />
          )}
        </button>
      </div>

      {showAllHours && (
        <div className="mt-3 bg-white border rounded-xl p-4 shadow-lg">
          <div className="space-y-2">
            {daysOfWeek.map((day, index) => {
              const dayHours = hours[day as keyof typeof hours];
              const isToday = index === currentDayIndex;
              
              return (
                <div
                  key={day}
                  className={`flex justify-between text-sm py-1.5 ${
                    isToday ? 'font-medium text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span>{dayNames[index]}</span>
                  <span className={dayHours.closed ? 'text-red-600' : ''}>
                    {dayHours.closed ? 'Closed' : `${dayHours.open} - ${dayHours.close}`}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
