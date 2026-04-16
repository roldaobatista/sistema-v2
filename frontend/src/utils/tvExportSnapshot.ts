import html2canvas from 'html2canvas';
import { toast } from 'sonner';

/**
 * Captures the TV dashboard as PNG and triggers a browser download.
 */
export async function exportTvSnapshot(elementId = 'tv-dashboard-root') {
    const el = document.getElementById(elementId);
    if (!el) {
        toast.error('Elemento do dashboard não encontrado.');
        return;
    }

    try {
        const canvas = await html2canvas(el, {
            backgroundColor: '#0a0a0a',
            scale: 2,
            useCORS: true,
            logging: false,
            ignoreElements: (node) => {
                // Skip settings/popup overlays
                return node.classList?.contains('z-40') ?? false;
            },
        });

        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
        const link = document.createElement('a');
        link.download = `tv-dashboard-${timestamp}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (_err) {
        toast.error('Falha ao exportar imagem do dashboard.');
    }
}
