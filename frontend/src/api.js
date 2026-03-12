const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '';

function withAuth(headers = {}) {
  const token = window.localStorage.getItem('auth_token') || '';
  if (!token) return headers;
  return {
    ...headers,
    Authorization: `Bearer ${token}`,
  };
}

function optionalAuth(headers = {}) {
  const token = window.localStorage.getItem('auth_token') || '';
  if (!token) return headers;
  return {
    ...headers,
    Authorization: `Bearer ${token}`,
  };
}

async function request(path, options = {}) {
  const response = await fetch(`${API_BASE_URL}${path}`, options);
  const contentType = response.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const payload = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    const message = isJson ? payload?.error || `HTTP ${response.status}` : `HTTP ${response.status}`;
    throw new Error(message);
  }

  return payload;
}

export async function fetchParties() {
  const data = await request('/api/parties', { method: 'GET' });
  return Array.isArray(data.items) ? data.items : [];
}

export async function askChatbot(message) {
  const data = await request('/api/chat', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message }),
  });
  return data.reply || 'Keine Antwort erhalten.';
}

export async function fetchHomeIdeas({ hour, locale = 'de-DE' }) {
  const params = new URLSearchParams({ hour: String(hour), locale });
  const data = await request(`/api/insights?${params.toString()}`);
  return Array.isArray(data.items) ? data.items : [];
}

export async function registerUser(payload) {
  return request('/api/auth/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
}

export async function loginUser(payload) {
  return request('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
}

export async function fetchMe() {
  return request('/api/me', {
    method: 'GET',
    headers: withAuth(),
  });
}

export async function listRecipes(filters = {}) {
  const params = new URLSearchParams();
  if (filters.party_id) params.set('party_id', String(filters.party_id));
  if ((filters.q || '').trim() !== '') params.set('q', filters.q.trim());
  if ((filters.ingredients || '').trim() !== '') params.set('ingredients', filters.ingredients.trim());
  if ((filters.tags || '').trim() !== '') params.set('tags', filters.tags.trim());
  if ((filters.day_time || '').trim() !== '') params.set('day_time', filters.day_time.trim());
  if ((filters.max_minutes || '').trim() !== '') params.set('max_minutes', filters.max_minutes.trim());
  if ((filters.max_kcal || '').trim() !== '') params.set('max_kcal', filters.max_kcal.trim());
  if ((filters.min_protein || '').trim() !== '') params.set('min_protein', filters.min_protein.trim());
  if ((filters.max_carbs || '').trim() !== '') params.set('max_carbs', filters.max_carbs.trim());
  if ((filters.max_fat || '').trim() !== '') params.set('max_fat', filters.max_fat.trim());
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return request(`/api/recipes${suffix}`, {
    method: 'GET',
    headers: optionalAuth(),
  });
}

export async function fetchRecipeSearchOptions() {
  return request('/api/recipes/search-options', {
    method: 'GET',
    headers: withAuth(),
  });
}

export async function createRecipe(payload) {
  return request('/api/recipes', {
    method: 'POST',
    headers: withAuth({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(payload),
  });
}

export async function updateRecipe(recipeId, payload) {
  return request(`/api/recipes/${recipeId}`, {
    method: 'PUT',
    headers: withAuth({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(payload),
  });
}

export async function shareRecipePublic(recipeId, expiresAt = '') {
  return request(`/api/recipes/${recipeId}/share-public`, {
    method: 'POST',
    headers: withAuth({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({
      expires_at: expiresAt || null,
    }),
  });
}

export function printRecipeUrl(recipeId) {
  return `${API_BASE_URL}/api/recipes/${recipeId}/print`;
}

export async function fetchRecipe(recipeId) {
  const data = await request(`/api/recipes/${recipeId}`, {
    method: 'GET',
    headers: optionalAuth(),
  });
  return data.item || null;
}

export async function fetchFavorites() {
  const data = await request('/api/recipes/favorites', {
    method: 'GET',
    headers: withAuth(),
  });
  return Array.isArray(data.items) ? data.items : [];
}

export async function toggleFavorite(recipeId, add) {
  return request(`/api/recipes/${recipeId}/favorite`, {
    method: add ? 'POST' : 'DELETE',
    headers: withAuth(),
  });
}

export async function fetchRecentRecipes(partyId) {
  const params = partyId ? `?party_id=${partyId}` : '';
  const data = await request(`/api/recipes/recent${params}`, {
    method: 'GET',
    headers: optionalAuth(),
  });
  return Array.isArray(data.items) ? data.items : [];
}

export async function fetchMyRecipes() {
  const data = await request('/api/recipes/mine', {
    method: 'GET',
    headers: withAuth(),
  });
  return Array.isArray(data.items) ? data.items : [];
}

export async function deleteRecipe(recipeId) {
  return request(`/api/recipes/${recipeId}`, {
    method: 'DELETE',
    headers: withAuth(),
  });
}

export async function importFromUrl(url) {
  return request('/api/import/url', {
    method: 'POST',
    headers: withAuth({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ url }),
  });
}

export async function importFromFile(file) {
  const formData = new FormData();
  formData.append('file', file);
  return request('/api/import/file', {
    method: 'POST',
    headers: withAuth(),
    body: formData,
  });
}
