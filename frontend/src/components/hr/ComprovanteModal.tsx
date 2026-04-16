import React from 'react';
import { CheckCircle2, Download } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';

export interface ComprovanteData {
  employee_name: string;
  pis: string;
  cpf?: string;
  nsr: string;
  date: string;
  time: string;
  type: string;
  clock_in?: string;
  clock_out?: string;
  break_start?: string;
  break_end?: string;
  clock_method?: string;
  location?: string;
  hash?: string;
  duration_hours?: string;
}

interface ComprovanteModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  data: ComprovanteData | null;
}

export function ComprovanteModal({ open, onOpenChange, data }: ComprovanteModalProps) {
  if (!data) return null;

  const handlePrint = () => {
    window.print();
  };

  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title="Comprovante de Ponto"
      description="Portaria 671/2021 - MTP"
      className="max-w-md"
    >
      <div className="space-y-4 print:p-0 print:m-0">
        <div className="flex flex-col items-center justify-center py-4 bg-emerald-50 rounded-xl mb-6 print:hidden">
          <CheckCircle2 className="w-10 h-10 text-emerald-500 mb-2" />
          <p className="text-emerald-800 font-semibold text-center">Registro Efetuado com Sucesso</p>
        </div>

        <div className="space-y-3 font-mono text-xs text-slate-700 bg-white border border-slate-200 p-4 rounded-xl print:border-none print:text-black">
          <div className="flex justify-between border-b border-dashed border-slate-200 pb-2">
            <span className="font-semibold text-slate-500">Funcionário:</span>
            <span className="font-bold">{data.employee_name}</span>
          </div>
          <div className="flex justify-between border-b border-dashed border-slate-200 pb-2">
            <span className="font-semibold text-slate-500">PIS/PASEP:</span>
            <span>{data.pis || 'N/I'}</span>
          </div>
          <div className="flex justify-between border-b border-dashed border-slate-200 pb-2">
            <span className="font-semibold text-slate-500">CPF:</span>
            <span>{data.cpf || 'N/I'}</span>
          </div>
          <div className="flex justify-between border-b border-dashed border-slate-200 pb-2">
            <span className="font-semibold text-slate-500">Data e Hora:</span>
            <span className="font-bold">{data.date} às {data.time}</span>
          </div>
          <div className="flex justify-between border-b border-dashed border-slate-200 pb-2">
            <span className="font-semibold text-slate-500">Tipo de Registro:</span>
            <span>{data.type}</span>
          </div>
          <div className="flex justify-between border-b border-dashed border-slate-200 pb-2">
            <span className="font-semibold text-slate-500">Localização:</span>
            <span className="text-right max-w-[200px] truncate" title={data.location}>{data.location || 'N/I'}</span>
          </div>
          <div className="flex justify-between border-b border-dashed border-slate-200 pb-2">
            <span className="font-semibold text-slate-500">Método:</span>
            <span>{data.clock_method || 'N/I'}</span>
          </div>
          <div className="flex justify-between border-b border-dashed border-slate-200 pb-2">
            <span className="font-semibold text-slate-500">NSR:</span>
            <span>{data.nsr || 'N/I'}</span>
          </div>
          <div className="pt-2">
            <span className="font-semibold text-slate-500 block mb-1">Assinatura Digital (Hash):</span>
            <span className="break-all text-[10px] text-slate-400 leading-tight block">{data.hash || 'N/I'}</span>
          </div>
          <div className="pt-4 text-center text-[10px] text-slate-400 italic">
            Documento gerado eletronicamente.
            <br />
            Válido conforme Portaria 671/2021 do MTP.
          </div>
        </div>
      </div>

      <div className="flex justify-end gap-3 mt-6 print:hidden">
        <Button variant="outline" onClick={() => onOpenChange(false)}>
          Fechar
        </Button>
        <Button onClick={handlePrint} icon={<Download className="w-4 h-4" />}>
          Salvar / Imprimir
        </Button>
      </div>
    </Modal>
  );
}
