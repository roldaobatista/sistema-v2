import { PageHeader } from '@/components/ui/pageheader'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Shield, Monitor, Lock } from 'lucide-react'

export function SecurityPage() {
  return (
    <div className="space-y-6">
      <PageHeader title="Segurança" description="Configurações de segurança da conta" />

      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2"><Shield className="w-5 h-5" /> Proteção da Conta</CardTitle>
            <CardDescription>Status de segurança da sua conta</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center gap-3">
              <Badge variant="default" className="gap-1"><Lock className="w-3 h-3" /> Protegida</Badge>
            </div>
            <p className="text-sm text-muted-foreground">
              Sua conta está protegida com senha segura e sessão autenticada.
              Recomendamos alterar sua senha periodicamente.
            </p>
            <Button variant="outline" size="sm" onClick={() => window.location.href = '/configuracoes/perfil'}>
              Alterar Senha
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2"><Monitor className="w-5 h-5" /> Sessões Ativas</CardTitle>
            <CardDescription>Gerencie suas sessões de login</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">Utilize a página de perfil para gerenciar suas sessões ativas e revogar acessos suspeitos.</p>
            <Button variant="outline" size="sm" className="mt-3" onClick={() => window.location.href = '/configuracoes/perfil'}>Ir para Perfil</Button>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

export default SecurityPage
