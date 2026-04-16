import React from 'react';
import { MapPin, AlertTriangle, ShieldCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';

interface LocationBadgeProps {
  latitude?: number | null;
  longitude?: number | null;
  accuracy?: number | null;
  address?: string | null;
  isSpoofed?: boolean;
}

export function LocationBadge({ latitude, longitude, accuracy, address, isSpoofed }: LocationBadgeProps) {
  if (!latitude || !longitude) {
    return (
      <span className="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
        <MapPin className="w-3 h-3" /> Sem GPS
      </span>
    );
  }

  const isHighAccuracy = accuracy !== null && accuracy <= 50;
  const badgeColor = isSpoofed
    ? 'bg-red-100 text-red-800 border-red-200'
    : isHighAccuracy
    ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
    : 'bg-yellow-100 text-yellow-800 border-yellow-200';

  const icon = isSpoofed ? (
    <AlertTriangle className="w-3 h-3 text-red-600" />
  ) : isHighAccuracy ? (
    <ShieldCheck className="w-3 h-3 text-emerald-600" />
  ) : (
    <MapPin className="w-3 h-3 text-yellow-600" />
  );

  return (
    <TooltipProvider delayDuration={200}>
      <Tooltip>
        <TooltipTrigger asChild>
          <span className={cn('inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded border cursor-default', badgeColor)}>
            {icon}
            {address ? (
              <span className="truncate max-w-[150px]">{address.split(',')[0]}</span>
            ) : (
              <span>{latitude.toFixed(4)}, {longitude.toFixed(4)}</span>
            )}
          </span>
        </TooltipTrigger>
        <TooltipContent side="top" className="text-xs space-y-1">
          {address && <div><strong>Endereço:</strong> {address}</div>}
          <div><strong>Coordenadas:</strong> {latitude}, {longitude}</div>
          {accuracy !== null && <div><strong>Precisão:</strong> ±{accuracy.toFixed(0)}m</div>}
          {isSpoofed && <div className="text-red-500 font-semibold mt-1">⚠️ Suspeita de Fake GPS</div>}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}
