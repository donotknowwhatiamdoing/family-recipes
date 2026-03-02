import { useEffect, useState } from 'react';
import { fetchFavorites, toggleFavorite } from '../api';
import RecipeCard from './RecipeCard';

export default function FavoritesRow({ onSelectRecipe }) {
  const [favorites, setFavorites] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchFavorites()
      .then(setFavorites)
      .catch(() => setFavorites([]))
      .finally(() => setLoading(false));
  }, []);

  async function handleToggle(recipeId, add) {
    try {
      await toggleFavorite(recipeId, add);
      if (!add) {
        setFavorites((prev) => prev.filter((r) => r.id !== recipeId));
      }
    } catch { /* ignore */ }
  }

  if (loading) return null;
  if (favorites.length === 0) return null;

  return (
    <section className="dashboard-section">
      <h3 className="section-title">Deine Favoriten</h3>
      <div className="recipe-row">
        {favorites.map((recipe) => (
          <RecipeCard
            key={recipe.id}
            recipe={recipe}
            isFavorite={true}
            onToggleFavorite={handleToggle}
            onSelect={onSelectRecipe}
          />
        ))}
      </div>
    </section>
  );
}
