import { useEffect, useMemo, useRef, useState } from 'react';
import { fetchHomeIdeas, listRecipes } from '../api';
import IdeaCard from './IdeaCard';
import RecipeCard from './RecipeCard';

const FALLBACK_IDEAS = [
  {
    title: 'Warum Frühstück wichtig ist',
    text: 'Ein gutes Frühstück hilft beim konzentrierten Start in den Tag und kann Heißhunger später reduzieren.',
    action: 'Schnelle Frühstücksideen ansehen',
  },
];

export default function IdeasSection({ partyId, user, currentMealSlot, onSelectRecipe }) {
  const [ideaQuery, setIdeaQuery] = useState('');
  const [ideas, setIdeas] = useState([]);
  const [ideasLoading, setIdeasLoading] = useState(true);
  const [matchingRecipes, setMatchingRecipes] = useState([]);
  const [recipesLoading, setRecipesLoading] = useState(false);
  const [searchError, setSearchError] = useState('');
  const [searchDone, setSearchDone] = useState(false);
  const debounceRef = useRef(null);

  useEffect(() => {
    setIdeasLoading(true);
    fetchHomeIdeas({ hour: currentMealSlot.hourForIdeas, locale: 'de-DE' })
      .then((items) => setIdeas(items))
      .catch(() => setIdeas(FALLBACK_IDEAS))
      .finally(() => setIdeasLoading(false));
  }, [currentMealSlot.key]);

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    const q = ideaQuery.trim();
    if (q.length < 3) {
      setMatchingRecipes([]);
      setRecipesLoading(false);
      setSearchError('');
      setSearchDone(false);
      return;
    }
    setRecipesLoading(true);
    setSearchError('');
    setSearchDone(false);
    debounceRef.current = setTimeout(() => {
      listRecipes({ q, party_id: partyId })
        .then((data) => {
          const items = Array.isArray(data?.items) ? data.items : [];
          setMatchingRecipes(items);
          setSearchDone(true);
        })
        .catch((err) => {
          setMatchingRecipes([]);
          setSearchError(err.message || 'Suche fehlgeschlagen');
          setSearchDone(true);
        })
        .finally(() => setRecipesLoading(false));
    }, 400);
    return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
  }, [ideaQuery, partyId]);

  const filteredIdeas = useMemo(() => {
    const q = ideaQuery.trim().toLocaleLowerCase('de-DE');
    if (!q) return ideas;
    return ideas.filter((idea) =>
      [idea.title, idea.text, idea.action ?? ''].join(' ').toLocaleLowerCase('de-DE').includes(q)
    );
  }, [ideas, ideaQuery]);

  const queryActive = ideaQuery.trim().length >= 3;

  return (
    <>
      <section className="insights-head">
        <div>
          <h2>Inspiration & Suche</h2>
          <p>KI-Tipps für: {currentMealSlot.label} · oder durchsuche deine Rezepte</p>
        </div>
      </section>

      <section className="search-panel">
        <input
          type="search"
          placeholder="Ideen & Rezepte suchen (mind. 3 Zeichen) ..."
          value={ideaQuery}
          onChange={(event) => setIdeaQuery(event.target.value)}
        />
      </section>

      {recipesLoading && queryActive ? (
        <p className="loading-note">Rezepte werden gesucht ...</p>
      ) : null}

      {searchError ? (
        <p className="info-note">{searchError}</p>
      ) : null}

      {matchingRecipes.length > 0 ? (
        <section className="dashboard-section">
          <h3 className="section-title">Deine Rezepte</h3>
          <div className="recipe-row">
            {matchingRecipes.map((recipe) => (
              <RecipeCard key={recipe.id} recipe={recipe} onSelect={onSelectRecipe} />
            ))}
          </div>
        </section>
      ) : null}

      {queryActive && searchDone && !recipesLoading && matchingRecipes.length === 0 && !searchError ? (
        <p className="loading-note">Keine Rezepte gefunden für &bdquo;{ideaQuery.trim()}&ldquo;</p>
      ) : null}

      <section className="idea-grid">
        {queryActive && filteredIdeas.length > 0 ? (
          <h3 className="section-title" style={{ gridColumn: '1 / -1' }}>KI-Vorschläge</h3>
        ) : null}
        {ideasLoading ? <p className="loading-note">Ideen werden geladen ...</p> : null}
        {!ideasLoading &&
          filteredIdeas.map((idea, index) => (
            <IdeaCard key={`${idea.title}-${index}`} idea={idea} />
          ))}
      </section>
    </>
  );
}
