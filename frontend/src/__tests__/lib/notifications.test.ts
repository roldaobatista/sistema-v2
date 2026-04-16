import { describe, expect, it } from 'vitest'
import { countUnreadNotifications, extractNotificationList, extractUnreadCount } from '@/lib/notifications'

describe('notifications helpers', () => {
    it('extracts notifications from enveloped payload', () => {
        const payload = {
            success: true,
            data: {
                notifications: [
                    { id: 1, title: 'A' },
                    { id: 2, title: 'B' },
                ],
                unread_count: 1,
            },
        }

        expect(extractNotificationList(payload)).toHaveLength(2)
    })

    it('extracts notifications from plain payload', () => {
        const payload = {
            notifications: [
                { id: 1, title: 'A' },
            ],
        }

        expect(extractNotificationList(payload)).toEqual([{ id: 1, title: 'A' }])
    })

    it('extracts unread count from enveloped payload', () => {
        expect(extractUnreadCount({ success: true, data: { unread_count: 4 } })).toBe(4)
    })

    it('counts unread notifications using read and read_at flags', () => {
        const notifications = [
            { id: 1, read: false, read_at: null },
            { id: 2, read: true, read_at: '2026-03-06 10:00:00' },
            { id: 3, read_at: null },
        ]

        expect(countUnreadNotifications(notifications)).toBe(2)
    })
})
