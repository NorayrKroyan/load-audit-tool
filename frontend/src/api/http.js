const API_BASE = ''

export async function api(path, options = {}) {
  const token = localStorage.getItem('load_audit_token')

  const headers = {
    Accept: 'application/json',
    ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    ...(options.headers || {}),
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
  })

  if (response.status === 401) {
    localStorage.removeItem('load_audit_token')
    localStorage.removeItem('load_audit_user')
    if (window.location.pathname !== '/login') {
      window.location.href = '/login'
    }
  }

  const text = await response.text()
  let data = {}

  try {
    data = text ? JSON.parse(text) : {}
  } catch {
    data = { message: text || 'Unknown response.' }
  }

  if (!response.ok) {
    const error = new Error(data.message || 'Request failed.')
    error.status = response.status
    error.data = data
    throw error
  }

  return data
}
