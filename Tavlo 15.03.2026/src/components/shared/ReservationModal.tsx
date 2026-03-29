import { X, Calendar, Clock, Users } from 'lucide-react';
import { Button } from '../ui/button';
import { useState } from 'react';
import { toast } from 'sonner@2.0.3';
import { ReservationBooking } from '../ReservationBooking';

interface ReservationModalProps {
  isOpen: boolean;
  onClose: () => void;
  restaurantName: string;
  restaurantId?: string;
  user?: any;
  guestId?: string;
}

export function ReservationModal({ 
  isOpen, 
  onClose, 
  restaurantName,
  restaurantId = 'rest_1',
  user,
  guestId
}: ReservationModalProps) {
  if (!isOpen) return null;

  return (
    <ReservationBooking
      restaurantId={restaurantId}
      restaurantName={restaurantName}
      onClose={onClose}
      onSuccess={() => {
        onClose();
      }}
      user={user}
      guestId={guestId}
    />
  );
}