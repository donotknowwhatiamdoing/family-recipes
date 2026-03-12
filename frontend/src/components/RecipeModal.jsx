import { useEffect, useState } from 'react';
import { createRecipe, updateRecipe } from '../api';
import ImportDropZone from './ImportDropZone';

const EMPTY_FORM = {
  title: '',
  description: '',
  day_time: '',
  kcal_per_serving: '',
  protein_g_per_serving: '',
  carbs_g_per_serving: '',
  fat_g_per_serving: '',
  servings: '',
  prep_minutes: '',
  cook_minutes: '',
  visibility: 'private',
  tags: '',
  ingredients: '',
  steps: '',
};

export default function RecipeModal({ onClose, onCreated, recipe = null }) {
  const [recipeForm, setRecipeForm] = useState(EMPTY_FORM);
  const [recipeError, setRecipeError] = useState('');
  const isEditMode = !!recipe?.id;

  useEffect(() => {
    if (!recipe) {
      setRecipeForm(EMPTY_FORM);
      return;
    }
    setRecipeForm({
      title: recipe.title || '',
      description: recipe.description || '',
      day_time: recipe.day_time || '',
      kcal_per_serving: recipe.kcal_per_serving != null ? String(recipe.kcal_per_serving) : '',
      protein_g_per_serving: recipe.protein_g_per_serving != null ? String(recipe.protein_g_per_serving) : '',
      carbs_g_per_serving: recipe.carbs_g_per_serving != null ? String(recipe.carbs_g_per_serving) : '',
      fat_g_per_serving: recipe.fat_g_per_serving != null ? String(recipe.fat_g_per_serving) : '',
      servings: recipe.servings != null ? String(recipe.servings) : '',
      prep_minutes: recipe.prep_minutes != null ? String(recipe.prep_minutes) : '',
      cook_minutes: recipe.cook_minutes != null ? String(recipe.cook_minutes) : '',
      visibility: recipe.visibility || 'private',
      tags: Array.isArray(recipe.tags) ? recipe.tags.join(', ') : '',
      ingredients: Array.isArray(recipe.ingredients)
        ? recipe.ingredients
            .map((i) => {
              const parts = [
                i.quantity != null ? String(i.quantity) : '',
                i.unit || '',
                i.ingredient_name || '',
              ].filter(Boolean);
              return parts.join(' ');
            })
            .join('\n')
        : '',
      steps: Array.isArray(recipe.steps) ? recipe.steps.map((s) => s.instruction || '').filter(Boolean).join('\n') : '',
    });
  }, [recipe]);

  function onRecipeInput(event) {
    const { name, value } = event.target;
    setRecipeForm((prev) => ({ ...prev, [name]: value }));
  }

  function handleImported(data) {
    setRecipeForm((prev) => ({
      ...prev,
      title: data.title || prev.title,
      description: data.description || prev.description,
      day_time: data.day_time || prev.day_time,
      kcal_per_serving: data.kcal_per_serving != null ? String(data.kcal_per_serving) : prev.kcal_per_serving,
      protein_g_per_serving: data.protein_g_per_serving != null ? String(data.protein_g_per_serving) : prev.protein_g_per_serving,
      carbs_g_per_serving: data.carbs_g_per_serving != null ? String(data.carbs_g_per_serving) : prev.carbs_g_per_serving,
      fat_g_per_serving: data.fat_g_per_serving != null ? String(data.fat_g_per_serving) : prev.fat_g_per_serving,
      servings: data.servings != null ? String(data.servings) : prev.servings,
      prep_minutes: data.prep_minutes != null ? String(data.prep_minutes) : prev.prep_minutes,
      cook_minutes: data.cook_minutes != null ? String(data.cook_minutes) : prev.cook_minutes,
      tags: (data.tags || []).length > 0 ? data.tags.join(', ') : prev.tags,
      ingredients:
        (data.ingredients || []).length > 0
          ? data.ingredients
              .map((i) => {
                const parts = [
                  i.quantity != null ? String(i.quantity) : '',
                  i.unit || '',
                  i.ingredient_name,
                ].filter(Boolean);
                return parts.join(' ');
              })
              .join('\n')
          : prev.ingredients,
      steps:
        (data.steps || []).length > 0
          ? data.steps.map((s) => s.instruction).join('\n')
          : prev.steps,
    }));
  }

  async function onRecipeSubmit(event) {
    event.preventDefault();
    setRecipeError('');
    try {
      const ingredients = recipeForm.ingredients
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => ({ ingredient_name: line }));
      const steps = recipeForm.steps
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((instruction) => ({ instruction }));
      const tags = recipeForm.tags
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);

      const payload = {
        title: recipeForm.title,
        description: recipeForm.description,
        day_time: recipeForm.day_time || null,
        kcal_per_serving: recipeForm.kcal_per_serving ? Number(recipeForm.kcal_per_serving) : null,
        protein_g_per_serving: recipeForm.protein_g_per_serving ? Number(recipeForm.protein_g_per_serving) : null,
        carbs_g_per_serving: recipeForm.carbs_g_per_serving ? Number(recipeForm.carbs_g_per_serving) : null,
        fat_g_per_serving: recipeForm.fat_g_per_serving ? Number(recipeForm.fat_g_per_serving) : null,
        servings: recipeForm.servings ? Number(recipeForm.servings) : null,
        prep_minutes: recipeForm.prep_minutes ? Number(recipeForm.prep_minutes) : null,
        cook_minutes: recipeForm.cook_minutes ? Number(recipeForm.cook_minutes) : null,
        visibility: recipeForm.visibility,
        tags,
        ingredients,
        steps,
      };

      if (isEditMode) {
        await updateRecipe(recipe.id, payload);
      } else {
        await createRecipe(payload);
      }
      setRecipeForm(EMPTY_FORM);
      if (onCreated) onCreated();
      onClose();
    } catch (error) {
      setRecipeError(error.message);
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <section className="modal-card" onClick={(event) => event.stopPropagation()}>
        <div className="modal-head">
          <h3>{isEditMode ? 'Rezept bearbeiten' : 'Neues Rezept'}</h3>
          <button type="button" className="chat-minimize" onClick={onClose}>
            Schließen
          </button>
        </div>

        {!isEditMode ? <ImportDropZone onImported={handleImported} /> : null}

        <form onSubmit={onRecipeSubmit} className="form-grid">
          <label className="form-label">
            <span>Titel *</span>
            <input name="title" value={recipeForm.title} onChange={onRecipeInput} required />
          </label>
          <label className="form-label">
            <span>Beschreibung</span>
            <textarea name="description" value={recipeForm.description} onChange={onRecipeInput} />
          </label>
          <div className="form-row">
            <label className="form-label">
              <span>Kategorie</span>
              <select name="day_time" value={recipeForm.day_time} onChange={onRecipeInput}>
                <option value="">—</option>
                <option value="fruehstueck">Frühstück</option>
                <option value="mittag">Mittag</option>
                <option value="abend">Abend</option>
                <option value="snack">Snack</option>
                <option value="suesses">Süßes</option>
                <option value="gebaeck">Gebäck & Kuchen</option>
                <option value="getraenk">Getränk</option>
                <option value="beilage">Beilage</option>
              </select>
            </label>
            <label className="form-label">
              <span>Sichtbarkeit</span>
              <select name="visibility" value={recipeForm.visibility} onChange={onRecipeInput}>
                <option value="private">Privat</option>
                <option value="internal">Intern</option>
                <option value="public_link">Public-Link</option>
              </select>
            </label>
          </div>
          <div className="form-row">
            <label className="form-label">
              <span>Portionen</span>
              <input name="servings" type="number" min="1" value={recipeForm.servings} onChange={onRecipeInput} />
            </label>
            <label className="form-label">
              <span>Vorbereitung (Min.)</span>
              <input name="prep_minutes" type="number" min="0" value={recipeForm.prep_minutes} onChange={onRecipeInput} />
            </label>
            <label className="form-label">
              <span>Kochzeit (Min.)</span>
              <input name="cook_minutes" type="number" min="0" value={recipeForm.cook_minutes} onChange={onRecipeInput} />
            </label>
          </div>
          <div className="form-row">
            <label className="form-label">
              <span>kcal / Portion</span>
              <input name="kcal_per_serving" type="number" min="0" value={recipeForm.kcal_per_serving} onChange={onRecipeInput} />
            </label>
            <label className="form-label">
              <span>Protein (g)</span>
              <input name="protein_g_per_serving" type="number" min="0" step="0.1" value={recipeForm.protein_g_per_serving} onChange={onRecipeInput} />
            </label>
            <label className="form-label">
              <span>Kohlenhydrate (g)</span>
              <input name="carbs_g_per_serving" type="number" min="0" step="0.1" value={recipeForm.carbs_g_per_serving} onChange={onRecipeInput} />
            </label>
            <label className="form-label">
              <span>Fett (g)</span>
              <input name="fat_g_per_serving" type="number" min="0" step="0.1" value={recipeForm.fat_g_per_serving} onChange={onRecipeInput} />
            </label>
          </div>
          <label className="form-label">
            <span>Tags (kommagetrennt)</span>
            <input name="tags" value={recipeForm.tags} onChange={onRecipeInput} />
          </label>
          <label className="form-label">
            <span>Zutaten (je Zeile eine)</span>
            <textarea name="ingredients" value={recipeForm.ingredients} onChange={onRecipeInput} />
          </label>
          <label className="form-label">
            <span>Schritte (je Zeile einer)</span>
            <textarea name="steps" value={recipeForm.steps} onChange={onRecipeInput} />
          </label>
          {recipeError ? <p className="info-note">{recipeError}</p> : null}
          <button className="primary-btn" type="submit">{isEditMode ? 'Änderungen speichern' : 'Speichern'}</button>
        </form>
      </section>
    </div>
  );
}
