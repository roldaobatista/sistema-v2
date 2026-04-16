import { describe, expect, it } from 'vitest'

import { normalizePermissionGroups, normalizeRoleDetail, normalizeRoleList } from '@/pages/iam/role-contract'

describe('role-contract', () => {
    it('normaliza lista de roles com payload cru', () => {
        expect(normalizeRoleList({
            data: [
                { id: 1, name: 'admin', display_name: 'Administrador' },
            ],
        })).toEqual([
            { id: 1, name: 'admin', display_name: 'Administrador' },
        ])
    })

    it('normaliza grupos de permissao com envelope data', () => {
        expect(normalizePermissionGroups({
            data: {
                data: [
                    {
                        id: 10,
                        name: 'IAM',
                        permissions: [
                            { id: 101, name: 'iam.user.view', criticality: 'HIGH' },
                        ],
                    },
                ],
            },
        })).toEqual([
            {
                id: 10,
                name: 'IAM',
                permissions: [
                    { id: 101, name: 'iam.user.view', criticality: 'HIGH' },
                ],
            },
        ])
    })

    it('normaliza detalhe de role com envelope data para edicao', () => {
        expect(normalizeRoleDetail({
            data: {
                data: {
                    id: 3,
                    name: 'supervisor',
                    display_name: 'Supervisor',
                    description: 'Role operacional',
                    permissions: [
                        { id: 201, name: 'os.work_order.view' },
                        { id: 202, name: 'os.work_order.update' },
                    ],
                },
            },
        })).toEqual({
            id: 3,
            name: 'supervisor',
            display_name: 'Supervisor',
            description: 'Role operacional',
            permissions: [
                { id: 201, name: 'os.work_order.view' },
                { id: 202, name: 'os.work_order.update' },
            ],
        })
    })
})
