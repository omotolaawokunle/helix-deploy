import { api } from '@/lib/axios'
import type { CommandRecord } from '@/types'
import type { CursorPaginatedResponse } from '@/features/deployments/types'

export interface RunCommandResult {
  status: 'queued'
  message: string
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
  payload: { command: string; confirmed?: boolean },
): Promise<RunCommandResult | CommandConfirmationRequired> {
  const response = await api.post<RunCommandResult | CommandConfirmationRequired>(
    `/api/v1/servers/${serverId}/commands`,
    payload,
  )

  return response.data
}

export function isCommandConfirmationRequired(
  result: RunCommandResult | CommandConfirmationRequired,
): result is CommandConfirmationRequired {
  return 'status' in result && result.status === 'confirmation_required'
}
