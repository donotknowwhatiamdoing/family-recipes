import { useEffect, useState } from 'react';
import { fetchRecentRecipes, fetchFavorites, toggleFavorite } from '../api';
import RecipeCard from './RecipeCard';

export default function RecentRecipesRow({ partyId, user, onSelectRecipe }) {
  const [recipes, setRecipes] = useState([]);
  const [favoriteIds, setFavoriteIds] = useState(new Set());
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const promises = [fetchRecentRecipes(partyId)];
    if (user) promises.push(fetchFavorites());

    Promise.all(promises)
      .then(([recent, favs]) => {
        setRecipes(recent);
        if (favs) setFavoriteIds(new Set(favs.map((f) => f.id)));
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [partyId, user]);

  async function handleToggle(recipeId, add) {
    try {
      await toggleFavorite(recipeId, add);
      setFavoriteIds((prev) => {
        const next = new Set(prev);
        if (add) next.add(recipeId);
        else next.delete(recipeId);
        return next;
      });
    } catch { /* ignore */ }
  }

  if (loading) return null;
  if (recipes.length === 0) return null;

  return (
    <section className="dashboard-section">
      <h3 className="section-title">Zuletzt hinzugefügt</h3>
      <div className="recipe-row">
        {recipes.map((recipe) => (
          <RecipeCard
            key={recipe.id}
            recipe={recipe}
            isFavorite={favoriteIds.has(recipe.id)}
            onToggleFavorite={user ? handleToggle : null}
            onSelect={onSelectRecipe}
          />
        ))}
      </div>
    </section>
  );
}
