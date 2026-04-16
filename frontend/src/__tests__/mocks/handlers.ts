/**
 * Re-exporta todos os handlers por domínio para manter compatibilidade.
 * Novos testes devem importar de ./handlers/index ou de ./handlers/<domain>-handlers.
 */
export { handlers } from './handlers/index'
