import { MapPin, Navigation, Phone, Clock } from 'lucide-react';
import { Button } from '../ui/button';

interface LocationSectionProps {
  address: string;
  phone: string;
  hours: string;
  coordinates?: { lat: number; lng: number };
}

export function LocationSection({ address, phone, hours, coordinates }: LocationSectionProps) {
  const handleGetDirections = () => {
    if (coordinates) {
      const url = `https://www.google.com/maps/dir/?api=1&destination=${coordinates.lat},${coordinates.lng}`;
      window.open(url, '_blank');
    }
  };

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Map */}
        <div className="bg-gray-100 rounded-2xl overflow-hidden h-[300px] lg:h-[400px]">
          <iframe
            src={`https://www.openstreetmap.org/export/embed.html?bbox=${coordinates ? `${coordinates.lng - 0.01}%2C${coordinates.lat - 0.01}%2C${coordinates.lng + 0.01}%2C${coordinates.lat + 0.01}` : '16.3%2C48.1%2C16.5%2C48.3'}&layer=mapnik&marker=${coordinates ? `${coordinates.lat}%2C${coordinates.lng}` : '48.2082%2C16.3738'}`}
            className="w-full h-full"
            style={{ border: 0 }}
            loading="lazy"
            title="Restaurant location"
          />
        </div>

        {/* Location Details */}
        <div className="space-y-6">
          <div className="bg-white rounded-2xl p-6 border border-gray-100">
            <h3 className="text-xl mb-4">Contact & Hours</h3>
            
            <div className="space-y-4">
              {/* Address */}
              <div className="flex gap-3">
                <MapPin className="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
                <div>
                  <div className="text-sm text-gray-500 mb-1">Address</div>
                  <div className="text-gray-900">{address}</div>
                </div>
              </div>

              {/* Phone */}
              <div className="flex gap-3">
                <Phone className="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
                <div>
                  <div className="text-sm text-gray-500 mb-1">Phone</div>
                  <a href={`tel:${phone}`} className="text-gray-900 hover:text-orange-600">
                    {phone}
                  </a>
                </div>
              </div>

              {/* Hours */}
              <div className="flex gap-3">
                <Clock className="w-5 h-5 text-gray-400 shrink-0 mt-0.5" />
                <div>
                  <div className="text-sm text-gray-500 mb-1">Hours</div>
                  <div className="text-gray-900 whitespace-pre-line">{hours}</div>
                </div>
              </div>
            </div>
          </div>

          {/* Get Directions Button */}
          <Button
            onClick={handleGetDirections}
            className="w-full gap-2"
            size="lg"
          >
            <Navigation className="w-4 h-4" />
            Get Directions
          </Button>
        </div>
      </div>
    </div>
  );
}
