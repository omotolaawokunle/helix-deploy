export interface ServiceVersionDefinition {
  serviceKey: string
  optionKey: string
  label: string
  values: Array<string | number>
  default: string | number
}

export type ProvisioningServiceVersionCatalog = Record<string, ServiceVersionDefinition>

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
    postgresqlVersion?: string
    mysqlVersion?: string
    pythonVersion?: string
    redisPassword?: string
  }
}

export interface UpdateProvisioningTemplatePayload {
  name?: string
  description?: string | null
  services?: string[]
  options?: {
    phpVersion?: string
    nodeVersion?: number
    postgresqlVersion?: string
    mysqlVersion?: string
    pythonVersion?: string
    redisPassword?: string
  }
}

export type ProvisioningTemplateFormState = {
  name: string
  description: string
  services: string[]
  phpVersion: string
  nodeVersion: number
  postgresqlVersion: string
  mysqlVersion: string
  pythonVersion: string
  redisPassword: string
}
