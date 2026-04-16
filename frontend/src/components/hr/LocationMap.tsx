import React from 'react';
import { Card } from '@/components/ui/card';
import { MapPin } from 'lucide-react';

interface LocationMapProps {
  latitude?: number | null;
  longitude?: number | null;
  address?: string | null;
  height?: string;
}

export function LocationMap({ latitude, longitude, address, height = 'h-64' }: LocationMapProps) {
  if (!latitude || !longitude) {
    return (
      <Card className={`${height} bg-gray-50 flex flex-col items-center justify-center text-gray-400 border border-gray-200 shadow-sm`}>
        <MapPin className="w-8 h-8 mb-2 opacity-50" />
        <span className="text-sm">Coordenadas indisponíveis</span>
      </Card>
    );
  }

  // Simple OSM embed. In a production app, coordinate limits would be smaller for tighter zoom.
  const bbox = `${longitude - 0.005},${latitude - 0.005},${longitude + 0.005},${latitude + 0.005}`;
  const src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${latitude},${longitude}`;

  return (
    <Card className={`${height} overflow-hidden border border-gray-200 shadow-sm relative`}>
      <iframe
        width="100%"
        height="100%"
        frameBorder="0"
        scrolling="no"
        marginHeight={0}
        marginWidth={0}
        src={src}
        title={`Mapa para ${address || 'localização'}`}
        className="w-full h-full border-0"
      />
      {address && (
        <div className="absolute bottom-0 left-0 right-0 bg-white/90 backdrop-blur-sm p-2 text-xs text-gray-700 border-t border-gray-200 z-10 truncate">
          <MapPin className="w-3 h-3 inline-block mr-1 text-primary-600" />
          {address}
        </div>
      )}
    </Card>
  );
}
