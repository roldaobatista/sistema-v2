import { createContext, useContext, useId, useMemo, useState, type ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface TabsContextValue {
    value: string
    onChange: (value: string) => void
    baseId: string
}

const TabsContext = createContext<TabsContextValue>({ value: '', onChange: () => { }, baseId: '' })

interface TabsProps {
    value?: string
    defaultValue?: string
    onValueChange?: (value: string) => void
    children: ReactNode
    className?: string
}

export function Tabs({
    value: controlledValue,
    defaultValue = '',
    onValueChange,
    children,
    className,
}: TabsProps) {
    const [internalValue, setInternalValue] = useState(defaultValue)
    const isControlled = controlledValue !== undefined
    const value = isControlled ? controlledValue : internalValue
    const baseId = useId()

    const handleChange = (nextValue: string) => {
        if (!isControlled) {
            setInternalValue(nextValue)
        }
        onValueChange?.(nextValue)
    }

    const contextValue = useMemo(
        () => ({ value, onChange: handleChange, baseId }),
        [value, handleChange, baseId]
    )

    return (
        <TabsContext.Provider value={contextValue}>
            <div className={className}>{children}</div>
        </TabsContext.Provider>
    )
}

interface TabsListProps {
    children: ReactNode
    className?: string
}

export function TabsList({ children, className }: TabsListProps) {
    return (
        <div role="tablist" className={cn('inline-flex items-center gap-1 rounded-[var(--radius-lg)] bg-surface-100 dark:bg-white/[0.04] p-1', className)}>
            {children}
        </div>
    )
}

interface TabsTriggerProps {
    value: string
    children: ReactNode
    className?: string
}

export function TabsTrigger({ value, children, className }: TabsTriggerProps) {
    const ctx = useContext(TabsContext)
    const isActive = ctx.value === value

    return (
        <button
            type="button"
            role="tab"
            id={`${ctx.baseId}-tab-${value}`}
            aria-selected={isActive}
            aria-controls={`${ctx.baseId}-panel-${value}`}
            tabIndex={isActive ? 0 : -1}
            onClick={() => ctx.onChange(value)}
            className={cn(
                'rounded-[var(--radius-md)] px-3.5 py-1.5 text-sm font-semibold transition-all',
                isActive
                    ? 'bg-white dark:bg-white/[0.08] text-surface-900 dark:text-white shadow-sm'
                    : 'text-surface-500 hover:text-surface-700 dark:hover:text-surface-300',
                className
            )}
        >
            {children}
        </button>
    )
}

interface TabsContentProps {
    value: string
    children: ReactNode
    className?: string
}

export function TabsContent({ value, children, className }: TabsContentProps) {
    const ctx = useContext(TabsContext)
    if (ctx.value !== value) return null
    return (
        <div
            role="tabpanel"
            id={`${ctx.baseId}-panel-${value}`}
            aria-labelledby={`${ctx.baseId}-tab-${value}`}
            tabIndex={0}
            className={className}
        >
            {children}
        </div>
    )
}
