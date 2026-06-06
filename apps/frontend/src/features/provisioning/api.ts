import { api } from '@/lib/axios'
import type {
  CreateProvisioningTemplatePayload,
  ProvisioningTemplateRecord,
  UpdateProvisioningTemplatePayload,
} from '@/features/provisioning/types'

interface PaginatedResponse<T> {
  data: T[]
}

interface ResourceResponse<T> {
  data: T
}

export async function fetchProvisioningTemplates(
  organizationId: string,
): Promise<ProvisioningTemplateRecord[]> {
  const response = await api.get<PaginatedResponse<ProvisioningTemplateRecord>>(
    `/api/v1/organizations/${organizationId}/provisioning-templates`,
    { params: { per_page: 100 } },
  )

  return response.data.data
}

export async function createProvisioningTemplate(
  organizationId: string,
  payload: CreateProvisioningTemplatePayload,
): Promise<ProvisioningTemplateRecord> {
  const response = await api.post<ResourceResponse<ProvisioningTemplateRecord>>(
    `/api/v1/organizations/${organizationId}/provisioning-templates`,
    payload,
  )

  return response.data.data
}

export async function updateProvisioningTemplate(
  templateId: string,
  payload: UpdateProvisioningTemplatePayload,
): Promise<ProvisioningTemplateRecord> {
  const response = await api.patch<ResourceResponse<ProvisioningTemplateRecord>>(
    `/api/v1/provisioning-templates/${templateId}`,
    payload,
  )

  return response.data.data
}

export async function deleteProvisioningTemplate(templateId: string): Promise<void> {
  await api.delete(`/api/v1/provisioning-templates/${templateId}`)
}
