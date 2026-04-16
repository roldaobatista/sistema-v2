import React, { useState, useEffect, useRef } from 'react';
import { Search, Loader2, X } from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';
import api from '@/lib/api';
import { captureError } from '@/lib/sentry';

export interface AsyncSelectOption<TValue = unknown> {
    id: number;
    label: string;
    subLabel?: string;
    value: TValue;
}

interface AsyncSelectProps<TItem extends Record<string, unknown>, TValue> {
    value?: number | null;
    onChange: (value: AsyncSelectOption<TValue> | null) => void;
    endpoint: string;
    placeholder?: string;
    label?: string;
    disabled?: boolean;
    renderOption?: (option: AsyncSelectOption<TValue>) => React.ReactNode;
    mapData?: (data: TItem[]) => AsyncSelectOption<TValue>[];
    initialOption?: AsyncSelectOption<TValue> | null;
}

export function AsyncSelect<TItem extends Record<string, unknown> = Record<string, unknown>, TValue = unknown>({
    value,
    onChange,
    endpoint,
    placeholder = 'Selecione...',
    label,
    disabled = false,
    renderOption,
    mapData,
    initialOption,
}: AsyncSelectProps<TItem, TValue>) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [options, setOptions] = useState<AsyncSelectOption<TValue>[]>([]);
    const [loading, setLoading] = useState(false);
    const [selectedOption, setSelectedOption] = useState<AsyncSelectOption<TValue> | null>(initialOption ?? null);
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        // `undefined` means the parent is not controlling selection state.
        if (value === undefined) {
            return;
        }

        if (value === null) {
            if (selectedOption) {
                setSelectedOption(null);
            }
            return;
        }

        if (initialOption && selectedOption?.id !== initialOption.id) {
            setSelectedOption(initialOption);
        }
    }, [value, initialOption, selectedOption]);

    const fetchOptions = async (query: string) => {
        setLoading(true);
        try {
            const res = await api.get(endpoint, { params: { search: query, per_page: 20 } });
            const payload = res.data?.data ?? res.data;
            const data = Array.isArray(payload)
                ? payload
                : (Array.isArray(payload?.data) ? payload.data : []);

            let mapped: AsyncSelectOption<TValue>[] = [];
            if (mapData) {
                mapped = mapData(data as TItem[]);
            } else {
                mapped = (data || []).map((item) => ({
                    id: Number(item.id),
                    label: String(item.name ?? item.title ?? `#${String(item.id ?? '')}`),
                    subLabel: item.price != null ? `R$ ${String(item.price)}` : undefined,
                    value: item as TValue,
                }));
            }
            setOptions(mapped);
        } catch (error) {
            captureError(error, { endpoint, storeName: 'AsyncSelect' });
            toast.error('Erro ao carregar opções');
            setOptions([]);
        } finally {
            setLoading(false);
        }
    };

    // Debounce search
    useEffect(() => {
        if (isOpen) {
            const timeout = setTimeout(() => {
                fetchOptions(search);
            }, 300);
            return () => clearTimeout(timeout);
        }
    }, [search, isOpen, endpoint]);

    // Close on click outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleSelect = (opt: AsyncSelectOption<TValue>) => {
        setSelectedOption(opt);
        onChange(opt);
        setIsOpen(false);
        setSearch('');
    };

    return (
        <div className="relative" ref={containerRef}>
            {label && <label className="block text-sm font-medium text-surface-700 mb-1">{label}</label>}
            <div
                role="combobox"
                tabIndex={disabled ? -1 : 0}
                aria-expanded={isOpen}
                aria-haspopup="listbox"
                aria-label={label ?? placeholder}
                onClick={() => !disabled && setIsOpen(!isOpen)}
                onKeyDown={(e) => { if (!disabled && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); setIsOpen(!isOpen); } }}
                className={cn(
                    "relative flex w-full items-center justify-between rounded-lg border border-default bg-surface-50 px-3 py-2 text-sm shadow-sm cursor-pointer hover:bg-surface-100 transition-colors",
                    disabled && "opacity-50 cursor-not-allowed",
                    isOpen && "ring-2 ring-brand-500/20 border-brand-500"
                )}
            >
                <div className="flex-1 truncate">
                    {selectedOption ? (
                        <span className="font-medium text-surface-900">{selectedOption.label}</span>
                    ) : (
                        <span className="text-surface-400">{placeholder}</span>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    {selectedOption && !disabled && (
                        <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); setSelectedOption(null); onChange(null); }}
                            className="rounded-full p-0.5 hover:bg-surface-200 text-surface-400"
                            aria-label="Limpar seleção"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    )}
                    <Search className="h-4 w-4 text-surface-400" aria-hidden="true" />
                </div>
            </div>

            {isOpen && !disabled && (
                <div className="absolute z-50 mt-1 w-full rounded-xl border border-default bg-surface-0 shadow-xl overflow-hidden">
                    <div className="p-2 border-b border-subtle">
                        <input
                            ref={inputRef}
                            autoFocus
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar..."
                            aria-label="Buscar opções"
                            className="w-full rounded-lg bg-surface-50 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-brand-500"
                        />
                    </div>
                    <div className="max-h-60 overflow-y-auto p-1" role="listbox" aria-label={label ?? placeholder}>
                        {loading ? (
                            <div className="flex items-center justify-center py-4 text-surface-400" role="status">
                                <Loader2 className="h-5 w-5 animate-spin" aria-hidden="true" />
                                <span className="sr-only">Carregando opções...</span>
                            </div>
                        ) : options.length === 0 ? (
                            <div className="py-3 text-center text-sm text-surface-500" role="status">
                                Nenhum resultado encontrado
                            </div>
                        ) : (
                            (options || []).map((opt) => (
                                <div
                                    key={opt.id}
                                    role="option"
                                    tabIndex={0}
                                    aria-selected={selectedOption?.id === opt.id}
                                    onClick={() => handleSelect(opt)}
                                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleSelect(opt); } }}
                                    className="cursor-pointer rounded-lg px-3 py-2 text-sm hover:bg-surface-50 transition-colors"
                                >
                                    {renderOption ? renderOption(opt) : (
                                        <>
                                            <div className="font-medium text-surface-900">{opt.label}</div>
                                            {opt.subLabel && <div className="text-xs text-surface-500">{opt.subLabel}</div>}
                                        </>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
