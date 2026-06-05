export interface ProjectRecord {
  id: string
  organizationId: string
  name: string
  description: string | null
  environmentsCount?: number
  serversCount?: number
  sitesCount?: number
  createdAt: string
  updatedAt: string
}

export interface EnvironmentRecord {
  id: string
  projectId: string
  organizationId: string
  name: string
  label: string | null
  isProduction: boolean
  createdAt: string
  updatedAt: string
}

export interface CreateProjectPayload {
  name: string
  description?: string | null
}

export interface UpdateProjectPayload {
  name: string
  description?: string | null
}

export interface CreateEnvironmentPayload {
  name: string
  label?: string | null
  isProduction: boolean
}

export interface UpdateEnvironmentPayload {
  name: string
  label?: string | null
  isProduction: boolean
}

export interface PaginatedMeta {
  currentPage: number
  perPage: number
  total: number
}
