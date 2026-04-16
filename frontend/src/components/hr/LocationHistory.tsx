import React from 'react';
import { Clock } from 'lucide-react';
import { LocationBadge } from './LocationBadge';

export interface LocationEntry {
  id: number;
  time: string;
  type: string;
  latitude: number | null;
  longitude: number | null;
  accuracy: number | null;
  address: string | null;
  isSpoofed: boolean;
}

interface LocationHistoryProps {
  entries: LocationEntry[];
}

export function LocationHistory({ entries }: LocationHistoryProps) {
  if (!entries || entries.length === 0) {
    return <div className="text-sm text-gray-500 py-4">Nenhum histórico de localização disponível para este dia.</div>;
  }

  return (
    <div className="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-300 before:to-transparent">
      {entries.map((entry, index) => (
        <div key={entry.id || index} className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
          <div className="flex items-center justify-center w-10 h-10 rounded-full border border-white bg-slate-100 group-[.is-active]:bg-emerald-50 text-slate-500 group-[.is-active]:text-emerald-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10">
            <Clock className="w-4 h-4" />
          </div>

          <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] bg-white p-4 rounded border border-slate-200 shadow-sm">
            <div className="flex items-center justify-between space-x-2 mb-1">
              <div className="font-bold text-slate-900">{entry.type}</div>
              <time className="font-caveat font-medium text-emerald-500">{new Date(entry.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit'})}</time>
            </div>
            <div className="text-slate-500 text-sm mb-3">
              Captura de localização do evento de ponto.
            </div>
            <div>
              <LocationBadge
                latitude={entry.latitude}
                longitude={entry.longitude}
                accuracy={entry.accuracy}
                address={entry.address}
                isSpoofed={entry.isSpoofed}
              />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
