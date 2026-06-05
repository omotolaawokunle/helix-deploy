import { api } from '@/lib/axios'
import type { CommandRecord } from '@/types'
import type { CursorPaginatedResponse } from '@/features/deployments/types'
interface ResourceResponse<T> {
  data: T
}

export interface RunCommandQueuedResponse {
  data: CommandRecord
  message: string
  status: 'queued'
}

export interface CommandConfirmationRequired {
  status: 'confirmation_required'
  reason: string
  warningType: string
}

export async function fetchCommands(
  serverId: string,
  params?: { cursor?: string; per_page?: number },
): Promise<CursorPaginatedResponse<CommandRecord>> {
  const response = await api.get<CursorPaginatedResponse<CommandRecord>>(
    `/api/v1/servers/${serverId}/commands`,
    { params },
  )

  return response.data
}

export async function runCommand(
  serverId: string,
  payload: { command: string; confirmed?: boolean; timeout?: number },
): Promise<RunCommandQueuedResponse | CommandConfirmationRequired> {
  const response = await api.post<RunCommandQueuedResponse | CommandConfirmationRequired>(
    `/api/v1/servers/${serverId}/commands`,
    payload,
  )

  return response.data
}

export async function cancelCommand(commandId: string): Promise<CommandRecord> {
  const response = await api.post<ResourceResponse<CommandRecord>>(
    `/api/v1/commands/${commandId}/cancel`,
  )

  return response.data.data
}

export function isCommandConfirmationRequired(
  result: RunCommandQueuedResponse | CommandConfirmationRequired,
): result is CommandConfirmationRequired {
  return 'status' in result && result.status === 'confirmation_required'
}

export function isCommandQueued(
  result: RunCommandQueuedResponse | CommandConfirmationRequired,
): result is RunCommandQueuedResponse {
  return 'data' in result && result.status === 'queued'
}
