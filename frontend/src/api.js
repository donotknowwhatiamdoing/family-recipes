const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '';

export async function askChatbot(message) {
  const response = await fetch(`${API_BASE_URL}/api/chat`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ message }),
  });

  if (!response.ok) {
    throw new Error(`Chat request failed (${response.status})`);
  }

  const data = await response.json();
  return data.reply || 'Keine Antwort erhalten.';
}

export async function fetchHomeIdeas({ hour, locale = 'de-DE' }) {
  const params = new URLSearchParams({
    hour: String(hour),
    locale,
  });

  const response = await fetch(`${API_BASE_URL}/api/insights?${params.toString()}`);
  if (!response.ok) {
    throw new Error(`Insights request failed (${response.status})`);
  }

  const data = await response.json();
  if (!Array.isArray(data.items)) {
    throw new Error('Invalid insights format');
  }

  return data.items;
}
