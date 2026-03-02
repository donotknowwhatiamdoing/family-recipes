import { useEffect, useRef, useState } from 'react';

export default function Topbar({ user, logout, dateText, timeText, onNewRecipe, onLogin, onBackToParties, onMyRecipes, partyName }) {
  const [profileMenuOpen, setProfileMenuOpen] = useState(false);
  const profileMenuRef = useRef(null);

  useEffect(() => {
    function onPointerDown(event) {
      if (!profileMenuRef.current) return;
      if (!profileMenuRef.current.contains(event.target)) {
        setProfileMenuOpen(false);
      }
    }
    window.addEventListener('pointerdown', onPointerDown);
    return () => window.removeEventListener('pointerdown', onPointerDown);
  }, []);

  return (
    <header className="topbar">
      <div className="topbar-main">
        <div className="topbar-title-row">
          {onBackToParties ? (
            <button type="button" className="topbar-back" onClick={onBackToParties} title="Zur Familienauswahl">&larr;</button>
          ) : null}
          <h1>Familien-Rezepte</h1>
          {partyName ? <span className="topbar-party">{partyName}</span> : null}
        </div>
        <p className="topbar-meta">{dateText} · {timeText} Uhr</p>
      </div>
      <div className="topbar-side">
        {user ? (
          <>
            <div className="profile-menu" ref={profileMenuRef}>
              <button
                className="secondary-btn profile-btn"
                type="button"
                onClick={() => setProfileMenuOpen((open) => !open)}
              >
                {user.display_name}
              </button>
              {profileMenuOpen ? (
                <div className="profile-dropdown">
                  <button type="button" className="dropdown-item" onClick={() => { onMyRecipes(); setProfileMenuOpen(false); }}>
                    Meine Rezepte
                  </button>
                  <button type="button" className="dropdown-item" onClick={() => { logout(); setProfileMenuOpen(false); }}>
                    Logout
                  </button>
                </div>
              ) : null}
            </div>
            <button className="primary-btn" type="button" onClick={onNewRecipe}>
              Neues Rezept
            </button>
          </>
        ) : (
          <button className="primary-btn" type="button" onClick={onLogin}>
            Anmelden
          </button>
        )}
      </div>
    </header>
  );
}
