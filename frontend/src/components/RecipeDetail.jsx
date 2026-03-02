import { useEffect, useState } from 'react';
import { fetchRecipe, toggleFavorite, fetchFavorites, printRecipeUrl } from '../api';

const DAY_TIME_LABELS = {
  fruehstueck: 'Frühstück',
  mittag: 'Mittag',
  abend: 'Abend',
  snack: 'Snack',
  suesses: 'Süßes',
  gebaeck: 'Gebäck & Kuchen',
  getraenk: 'Getränk',
  beilage: 'Beilage',
};

export default function RecipeDetail({ recipeId, onClose, user }) {
  const [recipe, setRecipe] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [isFavorite, setIsFavorite] = useState(false);

  useEffect(() => {
    setLoading(true);
    setError('');
    const promises = [fetchRecipe(recipeId)];
    if (user) promises.push(fetchFavorites());

    Promise.all(promises)
      .then(([r, favs]) => {
        setRecipe(r);
        if (favs) setIsFavorite(favs.some((f) => f.id === recipeId));
      })
      .catch((err) => setError(err.message || 'Rezept konnte nicht geladen werden.'))
      .finally(() => setLoading(false));
  }, [recipeId, user]);

  async function handleFavToggle() {
    const next = !isFavorite;
    try {
      await toggleFavorite(recipeId, next);
      setIsFavorite(next);
    } catch { /* ignore */ }
  }

  if (loading) {
    return (
      <div className="modal-backdrop" onClick={onClose}>
        <section className="modal-card recipe-detail" onClick={(e) => e.stopPropagation()}>
          <p className="loading-note">Rezept wird geladen ...</p>
        </section>
      </div>
    );
  }

  if (error || !recipe) {
    return (
      <div className="modal-backdrop" onClick={onClose}>
        <section className="modal-card recipe-detail" onClick={(e) => e.stopPropagation()}>
          <div className="modal-head">
            <h3>Fehler</h3>
            <button type="button" className="chat-minimize" onClick={onClose}>Schließen</button>
          </div>
          <p className="info-note">{error || 'Rezept nicht gefunden.'}</p>
        </section>
      </div>
    );
  }

  const totalMinutes = (recipe.prep_minutes || 0) + (recipe.cook_minutes || 0);

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <section className="modal-card recipe-detail" onClick={(e) => e.stopPropagation()}>
        <div className="modal-head">
          <h3>{recipe.title}</h3>
          <div className="detail-head-actions">
            {user ? (
              <button
                type="button"
                className={`fav-btn-inline ${isFavorite ? 'fav-active' : ''}`}
                onClick={handleFavToggle}
                title={isFavorite ? 'Favorit entfernen' : 'Als Favorit merken'}
              >
                {isFavorite ? '\u2764' : '\u2661'}
              </button>
            ) : null}
            <a
              href={printRecipeUrl(recipeId)}
              target="_blank"
              rel="noopener noreferrer"
              className="secondary-btn detail-print-btn"
            >
              Drucken
            </a>
            <button type="button" className="chat-minimize" onClick={onClose}>Schließen</button>
          </div>
        </div>

        {recipe.description ? <p className="detail-desc">{recipe.description}</p> : null}

        <div className="detail-meta-row">
          {recipe.day_time ? <span className="recipe-badge">{DAY_TIME_LABELS[recipe.day_time] || recipe.day_time}</span> : null}
          {recipe.servings ? <span className="detail-meta-pill">{recipe.servings} Portionen</span> : null}
          {recipe.prep_minutes ? <span className="detail-meta-pill">Vorbereitung: {recipe.prep_minutes} Min.</span> : null}
          {recipe.cook_minutes ? <span className="detail-meta-pill">Kochen: {recipe.cook_minutes} Min.</span> : null}
          {totalMinutes > 0 ? <span className="detail-meta-pill">Gesamt: {totalMinutes} Min.</span> : null}
        </div>

        {(recipe.kcal_per_serving || recipe.protein_g_per_serving || recipe.carbs_g_per_serving || recipe.fat_g_per_serving) ? (
          <div className="detail-nutrition">
            <h4>Nährwerte pro Portion</h4>
            <div className="nutrition-grid">
              {recipe.kcal_per_serving ? <div className="nutrition-item"><span className="nutrition-value">{Math.round(recipe.kcal_per_serving)}</span><span className="nutrition-label">kcal</span></div> : null}
              {recipe.protein_g_per_serving ? <div className="nutrition-item"><span className="nutrition-value">{Math.round(recipe.protein_g_per_serving)}g</span><span className="nutrition-label">Protein</span></div> : null}
              {recipe.carbs_g_per_serving ? <div className="nutrition-item"><span className="nutrition-value">{Math.round(recipe.carbs_g_per_serving)}g</span><span className="nutrition-label">Kohlenhydrate</span></div> : null}
              {recipe.fat_g_per_serving ? <div className="nutrition-item"><span className="nutrition-value">{Math.round(recipe.fat_g_per_serving)}g</span><span className="nutrition-label">Fett</span></div> : null}
            </div>
          </div>
        ) : null}

        {recipe.ingredients && recipe.ingredients.length > 0 ? (
          <div className="detail-section">
            <h4>Zutaten</h4>
            <ul className="detail-ingredients">
              {recipe.ingredients.map((ing, i) => {
                const parts = [
                  ing.quantity != null ? String(ing.quantity) : '',
                  ing.unit || '',
                  ing.ingredient_name,
                ].filter(Boolean);
                return <li key={i}>{parts.join(' ')}{ing.note ? ` (${ing.note})` : ''}</li>;
              })}
            </ul>
          </div>
        ) : null}

        {recipe.steps && recipe.steps.length > 0 ? (
          <div className="detail-section">
            <h4>Zubereitung</h4>
            <ol className="detail-steps">
              {recipe.steps.map((step, i) => (
                <li key={i}>{step.instruction}</li>
              ))}
            </ol>
          </div>
        ) : null}

        {recipe.tags && recipe.tags.length > 0 ? (
          <div className="detail-tags">
            {recipe.tags.map((tag, i) => (
              <span key={i} className="recipe-badge">{tag}</span>
            ))}
          </div>
        ) : null}

        <p className="detail-footer">
          von {recipe.owner_party_name || 'Unbekannt'} · erstellt am{' '}
          {new Date(recipe.created_at).toLocaleDateString('de-DE')}
        </p>
      </section>
    </div>
  );
}
