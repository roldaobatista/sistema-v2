import React, { useState, useEffect } from "react"
import { Input, InputProps } from "@/components/ui/input"

interface CurrencyInputProps extends Omit<InputProps, "onChange" | "value"> {
    value?: number
    onChange?: (value: number) => void
}

const fmt = (val: number) =>
    new Intl.NumberFormat("pt-BR", {
        style: "currency",
        currency: "BRL",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val)

function useFormattedCurrency(value: number, onChange?: (v: number) => void) {
    const [displayValue, setDisplayValue] = useState(() => fmt(value))

    useEffect(() => {
        const v = value !== undefined && value !== null && !isNaN(value) ? value : 0
        setDisplayValue(fmt(v))
    }, [value])

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        let raw = e.target.value.replace(/\D/g, "")
        if (!raw) raw = "0"
        const numeric = parseInt(raw, 10) / 100
        setDisplayValue(fmt(numeric))
        onChange?.(numeric)
    }

    const handleFocus = (e: React.FocusEvent<HTMLInputElement>) => {
        e.target.select()
    }

    return { displayValue, handleChange, handleFocus }
}

export function CurrencyInput({ value = 0, onChange, onBlur, ...props }: CurrencyInputProps) {
    const { displayValue, handleChange, handleFocus } = useFormattedCurrency(value, onChange)

    return (
        <Input
            aria-label={props['aria-label'] || props.name || "Valor Monetário"}
            type="text"
            inputMode="numeric"
            value={displayValue}
            onChange={handleChange}
            onFocus={handleFocus}
            onBlur={onBlur}
            {...props}
        />
    )
}

interface CurrencyInputInlineProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, "onChange" | "value" | "type"> {
    value?: number
    onChange?: (value: number) => void
}

export function CurrencyInputInline({ value = 0, onChange, onBlur, className, ...props }: CurrencyInputInlineProps) {
    const { displayValue, handleChange, handleFocus } = useFormattedCurrency(value, onChange)

    return (
        <input
            aria-label={props['aria-label'] || props.name || "Valor Monetário"}
            type="text"
            inputMode="numeric"
            value={displayValue}
            onChange={handleChange}
            onFocus={handleFocus}
            onBlur={onBlur}
            className={className}
            {...props}
        />
    )
}
