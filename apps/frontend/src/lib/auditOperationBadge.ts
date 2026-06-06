export function auditOperationBadgeClass(operation: string): string {
  if (operation.includes('delete') || operation.includes('rollback')) {
    return 'badge-op-destructive'
  }

  if (operation.includes('deploy') || operation.includes('provision')) {
    return 'badge-op-primary'
  }

  return 'badge-op-muted'
}
