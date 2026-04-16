import { useState } from 'react'
import { ClipboardCheck } from 'lucide-react'
import { ApprovalQueue } from '@/components/journey/ApprovalQueue'
import { PageHeader } from '@/components/ui/pageheader'
import { cn } from '@/lib/utils'

type Tab = 'operational' | 'hr'

export default function JourneyApprovalPage() {
  const [activeTab, setActiveTab] = useState<Tab>('operational')

  return (
    <div className="space-y-6">
      <PageHeader
        title="Aprovação de Jornada"
        subtitle="Aprovação dual: Operacional → RH"
        icon={ClipboardCheck}
      />

      <div className="flex gap-1 rounded-lg border bg-muted p-1">
        {(['operational', 'hr'] as Tab[]).map((tab) => (
          <button
            key={tab}
            type="button"
            className={cn(
              'flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors',
              activeTab === tab
                ? 'bg-background shadow-sm'
                : 'text-muted-foreground hover:text-foreground',
            )}
            onClick={() => setActiveTab(tab)}
            aria-label={`Aba ${tab === 'operational' ? 'Operacional' : 'RH'}`}
          >
            {tab === 'operational' ? 'Aprovação Operacional' : 'Aprovação RH'}
          </button>
        ))}
      </div>

      <ApprovalQueue level={activeTab} />
    </div>
  )
}
