import { authHandlers } from './auth-handlers'
import { customerHandlers } from './customer-handlers'
import { supplierHandlers } from './supplier-handlers'
import { productHandlers } from './product-handlers'
import { serviceHandlers } from './service-handlers'
import { workOrderHandlers } from './work-order-handlers'
import { quoteHandlers } from './quote-handlers'
import { equipmentHandlers } from './equipment-handlers'
import { financialHandlers } from './financial-handlers'
import { stockHandlers } from './stock-handlers'
import { userHandlers } from './user-handlers'
import { centralHandlers } from './agenda-handlers'

export const handlers = [
    ...authHandlers,
    ...customerHandlers,
    ...supplierHandlers,
    ...productHandlers,
    ...serviceHandlers,
    ...workOrderHandlers,
    ...quoteHandlers,
    ...equipmentHandlers,
    ...financialHandlers,
    ...stockHandlers,
    ...userHandlers,
    ...centralHandlers,
]
