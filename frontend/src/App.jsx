import { useEffect, useState } from 'react';
import { askChatbot, fetchHomeIdeas } from './api';

const MOCK_CURRENT_USER = {
  name: 'Terry',
  party: 'Familie Terry',
};
const FALLBACK_IDEAS = [
  {
    title: 'Warum Frühstück wichtig ist',
    text: 'Ein gutes Frühstück hilft beim konzentrierten Start in den Tag und kann Heißhunger später reduzieren.',
    action: 'Schnelle Frühstücksideen ansehen',
  },
  {
    title: 'Wusstest du schon? Haferflocken',
    text: 'Hafer liefert Ballaststoffe, passt zu Obst, Joghurt und Nüssen und ist ideal für eine einfache Familienküche.',
    action: '3 Rezepte mit Hafer entdecken',
  },
  {
    title: 'Koch-Tipp für heute',
    text: 'Plane eine Basiszutat doppelt ein, damit aus dem Abendessen morgen ein schnelles Mittagessen wird.',
    action: 'Meal-Prep Mini-Plan starten',
  },
];

export default function App() {
  const [now, setNow] = useState(() => new Date());
  const [ideaQuery, setIdeaQuery] = useState('');
  const [ideas, setIdeas] = useState([]);
  const [ideasLoading, setIdeasLoading] = useState(true);
  const [ideasError, setIdeasError] = useState('');
  const [messages, setMessages] = useState([
    { role: 'assistant', text: 'Ich helfe dir beim Suchen und Kochen. Frag mich nach Rezepten.' },
  ]);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    const timerId = window.setInterval(() => setNow(new Date()), 1000);
    return () => window.clearInterval(timerId);
  }, []);

  const currentHour = now.getHours();

  useEffect(() => {
    loadIdeas(false, currentHour);
  }, [currentHour]);

  const dateText = now.toLocaleDateString('de-DE', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
  const timeText = now.toLocaleTimeString('de-DE', {
    hour: '2-digit',
    minute: '2-digit',
  });
  const normalizedIdeaQuery = ideaQuery.trim().toLocaleLowerCase('de-DE');
  const filteredIdeas =
    normalizedIdeaQuery === ''
      ? ideas
      : ideas.filter((idea) =>
          [idea.title, idea.text, idea.action ?? '']
            .join(' ')
            .toLocaleLowerCase('de-DE')
            .includes(normalizedIdeaQuery)
        );

  async function loadIdeas(force, hour) {
    const cacheKey = `home-ideas:${hour}`;

    if (!force) {
      const cached = window.sessionStorage.getItem(cacheKey);
      if (cached) {
        try {
          const parsed = JSON.parse(cached);
          if (Array.isArray(parsed) && parsed.length > 0) {
            setIdeas(parsed);
            setIdeasLoading(false);
            setIdeasError('');
            return;
          }
        } catch {
          // Ignore invalid cache entries
        }
      }
    }

    setIdeasLoading(true);
    setIdeasError('');

    try {
      const items = await fetchHomeIdeas({ hour, locale: 'de-DE' });
      setIdeas(items);
      window.sessionStorage.setItem(cacheKey, JSON.stringify(items));
    } catch {
      setIdeas(FALLBACK_IDEAS);
      setIdeasError('Die KI-Ideen konnten gerade nicht geladen werden. Es werden Standard-Ideen angezeigt.');
    } finally {
      setIdeasLoading(false);
    }
  }

  async function handleSend(messageText) {
    const message = messageText.trim();
    if (!message || pending) return;

    setMessages((prev) => [...prev, { role: 'user', text: message }]);
    setPending(true);

    try {
      const reply = await askChatbot(message);
      setMessages((prev) => [...prev, { role: 'assistant', text: reply }]);
    } catch (error) {
      setMessages((prev) => [
        ...prev,
        { role: 'assistant', text: 'Der Chatbot ist gerade nicht erreichbar.' },
      ]);
    } finally {
      setPending(false);
    }
  }

  function onChatSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const input = form.elements.namedItem('chatInput');
    if (!input) return;
    handleSend(input.value);
    input.value = '';
  }

  return (
    <div className="app-shell">
      <header className="topbar">
        <div className="topbar-main">
          <h1>Familien-Rezepte</h1>
          <p className="topbar-meta">
            {dateText} · {timeText} Uhr
          </p>
        </div>
        <div className="topbar-side">
          <div className="user-badge">
            <span className="user-label">Angemeldet</span>
            <strong>{MOCK_CURRENT_USER.name}</strong>
            <span className="user-party">{MOCK_CURRENT_USER.party}</span>
          </div>
          <button className="primary-btn" type="button">
            Neues Rezept
          </button>
        </div>
      </header>

      <main className="content">
        <section className="insights-head">
          <div>
            <h2>Inspiration für diese Tageszeit</h2>
            <p>KI-generierte Tipps und kurze Wissenshappen passend zur Uhrzeit.</p>
          </div>
          <button type="button" className="secondary-btn" onClick={() => loadIdeas(true, currentHour)}>
            Neu laden
          </button>
        </section>

        <section className="search-panel">
          <input
            type="search"
            placeholder="Ideen suchen, z. B. Frühstück, Hafer, Abendessen ..."
            value={ideaQuery}
            onChange={(event) => setIdeaQuery(event.target.value)}
          />
        </section>

        {ideasError ? <p className="info-note">{ideasError}</p> : null}

        <section className="idea-grid" aria-label="Ideen">
          {ideasLoading ? <p className="loading-note">Ideen werden geladen ...</p> : null}
          {!ideasLoading &&
            filteredIdeas.map((idea, index) => (
              <article key={`${idea.title}-${index}`} className="idea-card">
                <h3>{idea.title}</h3>
                <p>{idea.text}</p>
                {idea.action ? <span className="idea-action">{idea.action}</span> : null}
              </article>
            ))}
          {!ideasLoading && filteredIdeas.length === 0 ? (
            <p className="loading-note">Keine Treffer für deine Suche.</p>
          ) : null}
        </section>
      </main>

      <aside className="chat-panel" aria-label="Chatbot">
        <div className="chat-head">Chatbot</div>
        <div className="chat-messages">
          {messages.map((message, idx) => (
            <div key={idx} className={`msg ${message.role}`}>
              {message.text}
            </div>
          ))}
        </div>
        <form onSubmit={onChatSubmit} className="chat-form">
          <input name="chatInput" type="text" placeholder="Frage eingeben..." />
          <button type="submit" disabled={pending}>
            {pending ? '...' : 'Senden'}
          </button>
        </form>
      </aside>
    </div>
  );
}
