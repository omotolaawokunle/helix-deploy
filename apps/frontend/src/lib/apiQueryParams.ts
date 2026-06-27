/** Laravel `boolean` validation accepts 0/1 in query strings, not "true"/"false". */
export function refreshQueryParam(refresh: boolean | undefined): 1 | undefined {
  return refresh === true ? 1 : undefined
}
