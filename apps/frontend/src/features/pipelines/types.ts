export type PipelineStepType =
  | 'deploy'
  | 'migrate'
  | 'health_check'
  | 'approve'
  | 'script'
  | 'notify'

export type TeamRole = 'owner' | 'admin' | 'developer' | 'viewer'

export interface PipelineStepRecord {
  id: string
  pipelineId: string
  name: string
  type: PipelineStepType
  order: number
  config: Record<string, unknown>
  requiresApproval: boolean
  approverRole: TeamRole | null
  retryAttempts: number
  createdAt: string | null
  updatedAt: string | null
}

export interface PipelineRecord {
  id: string
  organizationId: string
  projectId: string | null
  name: string
  description: string | null
  steps?: PipelineStepRecord[]
  sitesCount?: number
  createdAt: string | null
  updatedAt: string | null
}

export interface PipelineStepInput {
  id?: string
  name: string
  type: PipelineStepType
  order: number
  config: Record<string, unknown>
  requiresApproval: boolean
  approverRole: TeamRole | null
  retryAttempts: number
}

export interface CreatePipelinePayload {
  name: string
  description?: string | null
  projectId?: string | null
  steps?: PipelineStepInput[]
}

export interface UpdatePipelinePayload {
  name?: string
  description?: string | null
  projectId?: string | null
  steps?: PipelineStepInput[]
}

export const PIPELINE_STEP_TYPES: Array<{ value: PipelineStepType; label: string }> = [
  { value: 'deploy', label: 'Deploy' },
  { value: 'migrate', label: 'Migrate' },
  { value: 'health_check', label: 'Health check' },
  { value: 'approve', label: 'Approval gate' },
  { value: 'script', label: 'Script' },
  { value: 'notify', label: 'Notify' },
]

export const APPROVER_ROLES: Array<{ value: TeamRole; label: string }> = [
  { value: 'owner', label: 'Owner' },
  { value: 'admin', label: 'Admin' },
  { value: 'developer', label: 'Developer' },
  { value: 'viewer', label: 'Viewer' },
]
