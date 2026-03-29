// Utility functions for business hours checking

export interface BusinessHours {
  open: string;
  close: string;
  closed: boolean;
}

export interface WeeklyHours {
  monday: BusinessHours;
  tuesday: BusinessHours;
  wednesday: BusinessHours;
  thursday: BusinessHours;
  friday: BusinessHours;
  saturday: BusinessHours;
  sunday: BusinessHours;
}

/**
 * Check if the restaurant is currently open
 */
export function isRestaurantOpen(businessHours?: WeeklyHours): boolean {
  if (!businessHours) return true; // If no hours set, assume always open
  
  const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
  const now = new Date();
  const today = days[now.getDay()];
  const hours = businessHours[today as keyof WeeklyHours];
  
  if (!hours || hours.closed) {
    return false;
  }
  
  // Parse current time
  const currentMinutes = now.getHours() * 60 + now.getMinutes();
  
  // Parse opening and closing times
  const [openHour, openMin] = hours.open.split(':').map(Number);
  const [closeHour, closeMin] = hours.close.split(':').map(Number);
  const openMinutes = openHour * 60 + openMin;
  const closeMinutes = closeHour * 60 + closeMin;
  
  // Handle overnight hours (e.g., 23:00 - 02:00)
  if (closeMinutes < openMinutes) {
    return currentMinutes >= openMinutes || currentMinutes < closeMinutes;
  }
  
  return currentMinutes >= openMinutes && currentMinutes < closeMinutes;
}

/**
 * Get the next opening time
 */
export function getNextOpeningTime(businessHours?: WeeklyHours): string | null {
  if (!businessHours) return null;
  
  const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
  const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const now = new Date();
  const currentDay = now.getDay();
  
  // Check next 7 days
  for (let i = 0; i < 7; i++) {
    const dayIndex = (currentDay + i) % 7;
    const dayKey = days[dayIndex];
    const hours = businessHours[dayKey as keyof WeeklyHours];
    
    if (!hours || hours.closed) continue;
    
    // If checking today, make sure opening time is in the future
    if (i === 0) {
      const [openHour, openMin] = hours.open.split(':').map(Number);
      const openMinutes = openHour * 60 + openMin;
      const currentMinutes = now.getHours() * 60 + now.getMinutes();
      
      if (currentMinutes >= openMinutes) {
        continue; // Already passed today's opening
      }
    }
    
    // Return the next opening time
    if (i === 0) {
      return `Today at ${hours.open}`;
    } else if (i === 1) {
      return `Tomorrow at ${hours.open}`;
    } else {
      return `${dayNames[dayIndex]} at ${hours.open}`;
    }
  }
  
  return null;
}

/**
 * Get current day's hours display string
 */
export function getCurrentDayHours(businessHours?: WeeklyHours): string {
  if (!businessHours) return '';
  
  const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
  const today = days[new Date().getDay()];
  const hours = businessHours[today as keyof WeeklyHours];
  
  if (!hours || hours.closed) {
    return 'Closed today';
  }
  
  return `${hours.open} - ${hours.close}`;
}

/**
 * Get time until restaurant closes (in minutes)
 */
export function getMinutesUntilClose(businessHours?: WeeklyHours): number | null {
  if (!businessHours) return null;
  if (!isRestaurantOpen(businessHours)) return null;
  
  const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
  const now = new Date();
  const today = days[now.getDay()];
  const hours = businessHours[today as keyof WeeklyHours];
  
  if (!hours || hours.closed) return null;
  
  const currentMinutes = now.getHours() * 60 + now.getMinutes();
  const [closeHour, closeMin] = hours.close.split(':').map(Number);
  const closeMinutes = closeHour * 60 + closeMin;
  
  // Handle overnight hours
  if (closeMinutes < currentMinutes) {
    return (24 * 60 - currentMinutes) + closeMinutes;
  }
  
  return closeMinutes - currentMinutes;
}
