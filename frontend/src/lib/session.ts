import type { UserProfile } from '../types'

export const ACTIVE_USER_KEY = 'wc-auth-user-id-v2'

export function getActiveUserId() {
  const value = localStorage.getItem(ACTIVE_USER_KEY)
  return value ? Number(value) : null
}

export function setActiveUserId(id: number) {
  localStorage.setItem(ACTIVE_USER_KEY, String(id))
}

export function activeUserFrom(users?: UserProfile[]) {
  if (!users?.length) return undefined
  const activeId = getActiveUserId()
  return activeId ? users.find((user) => user.id === activeId) : undefined
}
