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

export default function RecipeCard({ recipe, isFavorite, onToggleFavorite, onSelect }) {
  const totalMinutes =
    (recipe.prep_minutes || 0) + (recipe.cook_minutes || 0);

  return (
    <article
      className={`recipe-card ${onSelect ? 'recipe-card-clickable' : ''}`}
      onClick={onSelect ? () => onSelect(recipe.id) : undefined}
      role={onSelect ? 'button' : undefined}
      tabIndex={onSelect ? 0 : undefined}
      onKeyDown={onSelect ? (e) => { if (e.key === 'Enter') onSelect(recipe.id); } : undefined}
    >
      <div className="recipe-card-body">
        <div className="recipe-card-header">
          <h3 className="recipe-card-title">{recipe.title}</h3>
          {recipe.day_time ? <span className="recipe-badge">{DAY_TIME_LABELS[recipe.day_time] || recipe.day_time}</span> : null}
        </div>
        {recipe.description ? (
          <p className="recipe-card-desc">{recipe.description.length > 80 ? recipe.description.slice(0, 80) + '...' : recipe.description}</p>
        ) : null}
        <div className="recipe-card-meta">
          {totalMinutes > 0 ? <span className="recipe-meta-item">{totalMinutes} Min.</span> : null}
          {recipe.kcal_per_serving ? <span className="recipe-meta-item">{Math.round(recipe.kcal_per_serving)} kcal</span> : null}
        </div>
      </div>
      {onToggleFavorite ? (
        <button
          type="button"
          className={`fav-btn ${isFavorite ? 'fav-active' : ''}`}
          onClick={(e) => { e.stopPropagation(); onToggleFavorite(recipe.id, !isFavorite); }}
          title={isFavorite ? 'Favorit entfernen' : 'Als Favorit merken'}
        >
          {isFavorite ? '\u2764' : '\u2661'}
        </button>
      ) : null}
    </article>
  );
}
