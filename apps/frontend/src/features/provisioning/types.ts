export interface ProvisioningTemplateRecord {
  id: string
  organizationId: string | null
  name: string
  description: string | null
  services: string[]
  options: Record<string, unknown>
  isSystem: boolean
  createdAt?: string
  updatedAt?: string
}

export interface CreateProvisioningTemplatePayload {
  name: string
  description?: string | null
  services: string[]
  options?: {
    phpVersion?: string
    nodeVersion?: number
  }
}

export interface UpdateProvisioningTemplatePayload {
  name?: string
  description?: string | null
  services?: string[]
  options?: {
    phpVersion?: string
    nodeVersion?: number
  }
}

export type ProvisioningTemplateFormState = {
  name: string
  description: string
  services: string[]
  phpVersion: string
  nodeVersion: number
}
