import { setupServer } from 'msw/node'
import { handlers } from './handlers'

// MSW server instance — shared across all tests
export const server = setupServer(...handlers)
