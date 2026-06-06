import type { ProvisioningScript } from '@/features/servers/types'

export const PROVISIONING_SERVICE_LABELS: Record<ProvisioningScript, string> = {
  'create-deploy-user': 'Deploy user',
  nginx: 'Nginx',
  php: 'PHP',
  mysql: 'MySQL',
  postgresql: 'PostgreSQL',
  redis: 'Redis',
  nodejs: 'Node.js',
  python: 'Python',
  supervisor: 'Supervisor',
  docker: 'Docker',
  certbot: 'Certbot (Let\'s Encrypt)',
}

export function formatProvisioningTemplateName(name: string): string {
  return name
    .split('-')
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

export function servicesSummary(services: string[]): string {
  if (services.length === 0) {
    return 'No services'
  }

  return `${services.length} service${services.length === 1 ? '' : 's'}`
}
