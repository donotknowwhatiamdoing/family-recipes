import { useEffect, useState } from 'react';
import { fetchParties } from '../api';

export default function PartyPicker({ onSelectParty }) {
  const [parties, setParties] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchParties()
      .then(setParties)
      .catch((err) => setError(err.message || 'Parteien konnten nicht geladen werden.'))
      .finally(() => setLoading(false));
  }, []);

  return (
    <section className="party-picker">
      <div className="party-picker-header">
        <h2>Willkommen bei den Familien-Rezepten</h2>
        <p>Wähle eine Familie, um deren Rezepte zu sehen:</p>
      </div>

      {loading ? <p className="loading-note">Wird geladen ...</p> : null}
      {error ? <p className="info-note">{error}</p> : null}

      {!loading && !error && parties.length === 0 ? (
        <p className="info-note">Noch keine Familien registriert. Melde dich an, um die erste zu erstellen!</p>
      ) : null}

      <div className="party-grid">
        {parties.map((party) => (
          <button
            key={party.id}
            type="button"
            className="party-card"
            onClick={() => onSelectParty(party)}
          >
            <span className="party-card-name">{party.name}</span>
            <span className="party-card-count">{party.recipe_count} {party.recipe_count === 1 ? 'Rezept' : 'Rezepte'}</span>
          </button>
        ))}
      </div>
    </section>
  );
}
