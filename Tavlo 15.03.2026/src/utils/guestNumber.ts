// Generate a consistent guest number from a guestId (UUID)
export function getGuestNumber(guestId: string): number {
  // Simple hash function to convert UUID to a number
  let hash = 0;
  for (let i = 0; i < guestId.length; i++) {
    const char = guestId.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash; // Convert to 32bit integer
  }
  // Return absolute value modulo 10000 to get a number between 1-9999
  return Math.abs(hash) % 9999 + 1;
}

export function getReviewerName(customerName?: string, isGuest?: boolean, guestId?: string): string {
  if (!isGuest && customerName) {
    return customerName;
  }
  
  if (guestId) {
    return `User${getGuestNumber(guestId)}`;
  }
  
  return 'Anonymous User';
}
