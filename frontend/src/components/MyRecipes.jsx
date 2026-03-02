import { useEffect, useState } from 'react';
import { fetchMyRecipes, deleteRecipe } from '../api';

const DAY_TIME_LABELS = {
  fruehstueck: 'Frühstück',
  mittag: 'Mittag',
  abend: 'Abend',
  snack: 'Snack',
  suesses: 'Süßes',
  gebaeck: 'Gebäck',
  getraenk: 'Getränk',
  beilage: 'Beilage',
};

export default function MyRecipes({ onBack, onSelectRecipe }) {
  const [recipes, setRecipes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [deleting, setDeleting] = useState(null);

  useEffect(() => {
    fetchMyRecipes()
      .then(setRecipes)
      .catch((err) => setError(err.message || 'Rezepte konnten nicht geladen werden.'))
      .finally(() => setLoading(false));
  }, []);

  async function handleDelete(recipe) {
    const favInfo = recipe.favorite_count > 0
      ? `\n\nAchtung: ${recipe.favorite_count} Person(en) haben dieses Rezept als Favorit gespeichert!`
      : '';
    if (!window.confirm(`"${recipe.title}" wirklich löschen?${favInfo}`)) return;

    setDeleting(recipe.id);
    try {
      await deleteRecipe(recipe.id);
      setRecipes((prev) => prev.filter((r) => r.id !== recipe.id));
    } catch (err) {
      alert(err.message || 'Löschen fehlgeschlagen.');
    } finally {
      setDeleting(null);
    }
  }

  return (
    <section className="my-recipes">
      <div className="my-recipes-header">
        <button type="button" className="secondary-btn" onClick={onBack}>&larr; Zurück</button>
        <h2>Meine Rezepte</h2>
      </div>

      {loading ? <p className="loading-note">Wird geladen ...</p> : null}
      {error ? <p className="info-note">{error}</p> : null}

      {!loading && !error && recipes.length === 0 ? (
        <p className="info-note">Du hast noch keine Rezepte erstellt.</p>
      ) : null}

      {!loading && recipes.length > 0 ? (
        <div className="my-recipes-list">
          {recipes.map((recipe) => (
            <div key={recipe.id} className="my-recipe-item">
              <div
                className="my-recipe-info"
                role="button"
                tabIndex={0}
                onClick={() => onSelectRecipe(recipe.id)}
                onKeyDown={(e) => { if (e.key === 'Enter') onSelectRecipe(recipe.id); }}
              >
                <span className="my-recipe-title">{recipe.title}</span>
                <div className="my-recipe-meta">
                  {recipe.day_time ? <span className="recipe-badge recipe-badge-sm">{DAY_TIME_LABELS[recipe.day_time] || recipe.day_time}</span> : null}
                  {recipe.favorite_count > 0 ? <span className="my-recipe-favs">{'\u2764'} {recipe.favorite_count}</span> : null}
                </div>
              </div>
              <button
                type="button"
                className="danger-btn"
                onClick={() => handleDelete(recipe)}
                disabled={deleting === recipe.id}
              >
                {deleting === recipe.id ? '...' : 'Löschen'}
              </button>
            </div>
          ))}
        </div>
      ) : null}
    </section>
  );
}
