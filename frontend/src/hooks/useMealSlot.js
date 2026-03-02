import { useEffect, useMemo, useState } from 'react';

export function getMealSlot(date) {
  const hour = date.getHours();

  if (hour >= 5 && hour < 10) {
    return { key: 'fruehstueck', hourForIdeas: 6, label: 'Frühstück' };
  }
  if (hour >= 10 && hour < 14) {
    return { key: 'mittag', hourForIdeas: 12, label: 'Mittag' };
  }
  if (hour >= 14 && hour < 17) {
    return { key: 'snack', hourForIdeas: 15, label: 'Snack' };
  }
  if (hour >= 17) {
    return { key: 'abend', hourForIdeas: 18, label: 'Abendessen' };
  }

  return { key: 'nachts', hourForIdeas: 8, label: 'Morgenvorschau' };
}

export default function useMealSlot() {
  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const timerId = window.setInterval(() => setNow(new Date()), 1000);
    return () => window.clearInterval(timerId);
  }, []);

  const dateText = useMemo(
    () =>
      now.toLocaleDateString('de-DE', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
      }),
    [now]
  );

  const timeText = useMemo(
    () =>
      now.toLocaleTimeString('de-DE', {
        hour: '2-digit',
        minute: '2-digit',
      }),
    [now]
  );

  const currentMealSlot = useMemo(() => getMealSlot(now), [now]);

  return { now, dateText, timeText, currentMealSlot };
}
