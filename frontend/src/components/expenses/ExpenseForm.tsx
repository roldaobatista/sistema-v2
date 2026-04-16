import { useCallback, useEffect } from 'react'
import { Loader2, CheckCircle2, Trash2, Camera, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { compressImage } from '@/lib/compress-image'
import { CurrencyInputInline } from '@/components/common/CurrencyInput'
import type { ExpenseCategory } from '@/types/expense'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'

interface ExpenseFormProps {
    categories: ExpenseCategory[]
    /** Called on submit with form data. Return resolved data from API. */
    onSubmit: (data: ExpenseFormSubmitData) => Promise<void>
    /** If true the form is in edit mode */
    editingId?: number | null
    /** Pre-fill data for editing */
    initialData?: Partial<ExpenseFormValues>
    /** How to render: 'inline' shows in-page, 'sheet' shows as bottom sheet overlay */
    variant?: 'inline' | 'sheet'
    /** Close/cancel handler */
    onClose?: () => void
    /** Whether to show a date field (default: false, auto-sets today) */
    showDateField?: boolean
    /** Whether to show work_order_id hidden field */
    workOrderId?: string
}

interface ExpenseFormValues {
    categoryId: number | null
    description: string
    amount: string
    date: string
    photo: File | null
    photoPreview: string | null
    paymentMethod: 'cash' | 'corporate_card'
}

const fileSchema = z.custom<File>(
    (value) => typeof File !== 'undefined' && value instanceof File,
    { message: 'Arquivo inválido' },
)

export interface ExpenseFormSubmitData {
    expense_category_id: number
    description: string
    amount: string
    expense_date: string
    notes: string
    photo: File | null
    categoryName: string
    payment_method: 'cash' | 'corporate_card'
}

const expenseSchema = z.object({
    categoryId: z.number({ required_error: 'Selecione uma categoria', invalid_type_error: 'Selecione uma categoria' }),
    description: z.string().optional(),
    amount: z.string().refine((val) => {
        const num = parseFloat(val)
        return !isNaN(num) && num > 0
    }, { message: 'Valor deve ser maior que 0' }),
    date: z.string().min(1, 'Data é obrigatória'),
    paymentMethod: z.enum(['cash', 'corporate_card']),
    photo: fileSchema.nullable().optional(),
    photoPreview: z.string().nullable().optional(),
}).refine(data => !!data.photo || !!data.photoPreview, {
    message: 'O comprovante (foto/pdf) é obrigatório',
    path: ['photo']
})

type FormData = z.infer<typeof expenseSchema>

export default function ExpenseForm({
    categories,
    onSubmit,
    editingId = null,
    initialData,
    variant = 'inline',
    onClose,
    showDateField = false,
}: ExpenseFormProps) {
    const {
        register,
        control,
        handleSubmit,
        setValue,
        watch,
        reset,
        formState: { errors, isValid, isSubmitting },
    } = useForm<FormData>({
        resolver: zodResolver(expenseSchema),
        defaultValues: {
            categoryId: initialData?.categoryId ?? undefined,
            description: initialData?.description ?? '',
            amount: initialData?.amount ?? '',
            date: initialData?.date ?? new Date().toISOString().slice(0, 10),
            paymentMethod: initialData?.paymentMethod ?? 'corporate_card',
            photo: initialData?.photo ?? null,
            photoPreview: initialData?.photoPreview ?? null,
        },
        mode: 'onChange'
    })

    const categoryId = watch('categoryId')
    const photoPreview = watch('photoPreview')
    const paymentMethod = watch('paymentMethod')

    const selectedCategory = categories.find(c => c.id === categoryId)
    const isPdfPreview = (photoPreview ?? '').toLowerCase().includes('.pdf')

    useEffect(() => {
        if (initialData) {
            reset({
                categoryId: initialData.categoryId ?? undefined,
                description: initialData.description ?? '',
                amount: initialData.amount ?? '',
                date: initialData.date ?? new Date().toISOString().slice(0, 10),
                paymentMethod: initialData.paymentMethod ?? 'corporate_card',
                photo: initialData.photo ?? null,
                photoPreview: initialData.photoPreview ?? null,
            })
        }
    }, [initialData, reset])

    const handlePhotoCapture = useCallback(async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (!file) return

        const compressed = await compressImage(file)
        setValue('photo', compressed, { shouldValidate: true })
        const reader = new FileReader()
        reader.onload = () => setValue('photoPreview', reader.result as string, { shouldValidate: true })
        reader.readAsDataURL(compressed)
    }, [setValue])

    const removePhoto = useCallback(() => {
        setValue('photo', null, { shouldValidate: true })
        setValue('photoPreview', null, { shouldValidate: true })
    }, [setValue])

    const handleFormSubmit = useCallback(async (data: FormData) => {
        const categoryMenu = categories.find(c => c.id === data.categoryId)
        await onSubmit({
            expense_category_id: data.categoryId,
            description: data.description || categoryMenu?.name || '',
            amount: data.amount,
            expense_date: data.date,
            notes: '',
            photo: data.photo,
            categoryName: categoryMenu?.name ?? '',
            payment_method: data.paymentMethod,
        })
    }, [categories, onSubmit])

    const formContent = (
        <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-4">
            {/* Category chips */}
            <div>
                <label className="text-xs text-surface-500 font-medium mb-2 block">Categoria *</label>
                <Controller
                    name="categoryId"
                    control={control}
                    render={({ field }) => (
                        <div className="flex flex-wrap gap-2">
                            {(categories || []).map((cat) => (
                                <button
                                    key={cat.id}
                                    type="button"
                                    onClick={() => field.onChange(cat.id)}
                                    className={cn(
                                        'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                        field.value === cat.id
                                            ? 'text-white'
                                            : 'bg-surface-100 text-surface-600'
                                    )}
                                    style={field.value === cat.id ? { backgroundColor: cat.color } : undefined}
                                >
                                    {cat.name}
                                </button>
                            ))}
                        </div>
                    )}
                />
                {errors.categoryId && <p className="mt-1 text-xs text-red-500">{errors.categoryId.message}</p>}
            </div>

            {/* Payment Method */}
            {selectedCategory?.affects_technician_cash && (
                <div>
                    <label className="text-xs text-surface-500 font-medium mb-1 block">Forma de Pagamento *</label>
                    <p className="text-[10px] text-surface-400 mb-2 leading-tight">
                        Exigido apenas para despesas que afetam seu saldo (prestação de contas).
                    </p>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => setValue('paymentMethod', 'corporate_card', { shouldValidate: true })}
                            className={cn(
                                'flex-1 rounded-lg py-2.5 text-xs font-medium transition-colors border',
                                paymentMethod === 'corporate_card' ? 'bg-brand-50 border-brand-500 text-brand-700' : 'bg-surface-50 border-surface-200 text-surface-600'
                            )}
                        >
                            Cartão Corporativo
                        </button>
                        <button
                            type="button"
                            onClick={() => setValue('paymentMethod', 'cash', { shouldValidate: true })}
                            className={cn(
                                'flex-1 rounded-lg py-2.5 text-xs font-medium transition-colors border',
                                paymentMethod === 'cash' ? 'bg-brand-50 border-brand-500 text-brand-700' : 'bg-surface-50 border-surface-200 text-surface-600'
                            )}
                        >
                            Dinheiro Pessoal
                        </button>
                    </div>
                    {errors.paymentMethod && <p className="mt-1 text-xs text-red-500">{errors.paymentMethod.message}</p>}
                </div>
            )}

            {/* Amount */}
            <div>
                <label className="text-xs text-surface-500 font-medium mb-1.5 block">Valor (R$) *</label>
                <Controller
                    name="amount"
                    control={control}
                    render={({ field }) => (
                        <CurrencyInputInline
                            value={Number(field.value) || 0}
                            onChange={(value) => field.onChange(String(value))}
                            placeholder="0,00"
                            className={cn(
                                "w-full px-3 py-2.5 rounded-lg bg-surface-100 border text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none",
                                errors.amount ? "border-red-500" : "border-transparent"
                            )}
                        />
                    )}
                />
                {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount.message}</p>}
            </div>

            {/* Date (optional) */}
            {showDateField && (
                <div>
                    <label className="text-xs text-surface-500 font-medium mb-1.5 block">Data</label>
                    <input
                        type="date"
                        {...register('date')}
                        max={new Date().toISOString().slice(0, 10)}
                        aria-label="Data da despesa"
                        className={cn(
                            "w-full px-3 py-2.5 rounded-lg bg-surface-100 border text-sm focus:ring-2 focus:ring-brand-500/30 focus:outline-none",
                            errors.date ? "border-red-500" : "border-transparent"
                        )}
                    />
                    {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date.message}</p>}
                </div>
            )}

            {/* Description */}
            <div>
                <label className="text-xs text-surface-500 font-medium mb-1.5 block">Descrição</label>
                <textarea
                    {...register('description')}
                    placeholder="Detalhes da despesa..."
                    rows={2}
                    className="w-full px-3 py-2.5 rounded-lg bg-surface-100 border-0 text-sm placeholder:text-surface-400 focus:ring-2 focus:ring-brand-500/30 focus:outline-none resize-none"
                />
            </div>

            {/* Photo capture */}
            <div>
                <label className="text-xs text-surface-500 font-medium mb-1.5 block">Comprovante (foto)</label>
                {photoPreview ? (
                    <div className="relative">
                        {isPdfPreview ? (
                            <div className="flex h-32 items-center justify-center rounded-lg bg-surface-100 text-center text-sm text-surface-500">
                                Comprovante em PDF anexado
                            </div>
                        ) : (
                            <img src={photoPreview} alt="Preview" className="w-full h-32 object-cover rounded-lg" />
                        )}
                        <button
                            type="button"
                            onClick={removePhoto}
                            aria-label="Remover foto"
                            className="absolute top-2 right-2 w-7 h-7 rounded-full bg-red-600 text-white flex items-center justify-center"
                        >
                            <Trash2 className="w-3.5 h-3.5" />
                        </button>
                    </div>
                ) : (
                    <label className="flex items-center justify-center gap-2 py-6 rounded-lg border-2 border-dashed border-surface-300 cursor-pointer active:bg-surface-50 dark:active:bg-surface-800 transition-colors">
                        <Camera className="w-5 h-5 text-surface-400" />
                        <span className="text-sm text-surface-500">Tirar foto ou selecionar</span>
                        <input
                            type="file"
                            accept="image/*,application/pdf,.pdf"
                            capture="environment"
                            onChange={handlePhotoCapture}
                            className="hidden"
                            aria-label="Selecionar comprovante"
                        />
                    </label>
                )}
                {errors.photo?.message && <p className="mt-1 text-xs text-red-500">{String(errors.photo.message)}</p>}
            </div>

            {/* Save */}
            <button
                type="submit"
                disabled={isSubmitting || !isValid}
                className={cn(
                    'w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-colors',
                    isValid
                        ? 'bg-brand-600 active:bg-brand-700'
                        : 'bg-surface-300',
                    isSubmitting && 'opacity-70',
                )}
            >
                {isSubmitting ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
                {editingId ? 'Atualizar' : 'Salvar Despesa'}
            </button>
        </form>
    )

    if (variant === 'sheet') {
        return (
            <div className="absolute inset-0 z-20 flex flex-col">
                <div className="flex-1 bg-black/40" onClick={onClose} />
                <div className="bg-card rounded-t-2xl px-4 pt-4 pb-6 shadow-2xl animate-in slide-in-from-bottom duration-200 max-h-[85vh] overflow-y-auto">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-base font-bold text-foreground">
                            {editingId ? 'Editar Despesa' : 'Nova Despesa Avulsa'}
                        </h2>
                        <button type="button" onClick={onClose} aria-label="Fechar formulário" className="p-1 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800">
                            <X className="w-5 h-5 text-surface-400" />
                        </button>
                    </div>
                    {formContent}
                </div>
            </div>
        )
    }

    return (
        <div className="bg-card rounded-xl p-4 space-y-4 animate-in slide-in-from-top duration-200">
            <h3 className="text-sm font-semibold text-foreground">{editingId ? 'Editar Despesa' : 'Nova Despesa'}</h3>
            {formContent}
        </div>
    )
}
