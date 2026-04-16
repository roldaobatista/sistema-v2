import type { OfflineRequest } from './indexedDB'
import { updateRequestStatus, deleteRequest } from './indexedDB'

export type ConflictStrategy = 'local_wins' | 'server_wins' | 'manual'

export interface ConflictResult {
  requestId: number
  uuid: string
  strategy: ConflictStrategy
  resolved: boolean
}

/**
 * Resolve conflict for a single request.
 * - local_wins: re-send the local version (force update)
 * - server_wins: discard local version
 * - manual: mark for user review
 */
export async function resolveConflict(
  request: OfflineRequest,
  strategy: ConflictStrategy,
): Promise<ConflictResult> {
  const id = request.id!

  switch (strategy) {
    case 'server_wins':
      await deleteRequest(id)
      return { requestId: id, uuid: request.uuid, strategy, resolved: true }

    case 'local_wins':
      await updateRequestStatus(id, 'pending')
      return { requestId: id, uuid: request.uuid, strategy, resolved: true }

    case 'manual':
      await updateRequestStatus(id, 'conflict')
      return { requestId: id, uuid: request.uuid, strategy, resolved: false }
  }
}

/**
 * Auto-resolve conflicts using default strategy (last-write-wins = local_wins).
 */
export async function autoResolveConflicts(
  conflicts: OfflineRequest[],
  strategy: ConflictStrategy = 'local_wins',
): Promise<ConflictResult[]> {
  const results: ConflictResult[] = []

  for (const request of conflicts) {
    const result = await resolveConflict(request, strategy)
    results.push(result)
  }

  return results
}
