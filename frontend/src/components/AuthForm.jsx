export default function AuthForm({ authMode, setAuthMode, authForm, authError, onAuthInput, onAuthSubmit, isModal, onClose, onSuccess }) {
  async function handleSubmit(e) {
    e.preventDefault();
    const success = await onAuthSubmit(e);
    if (success && onSuccess) onSuccess();
  }

  const formContent = (
    <section className="idea-card">
      <h3>{authMode === 'register' ? 'Registrieren' : 'Anmelden'}</h3>
      <form onSubmit={handleSubmit} className="form-grid">
        {authMode === 'register' ? (
          <>
            <input name="party_name" value={authForm.party_name} onChange={onAuthInput} placeholder="Partei/Familie" />
            <input name="display_name" value={authForm.display_name} onChange={onAuthInput} placeholder="Anzeigename" />
          </>
        ) : null}
        <input name="email" type="email" value={authForm.email} onChange={onAuthInput} placeholder="E-Mail" />
        <input name="password" type="password" value={authForm.password} onChange={onAuthInput} placeholder="Passwort" />
        <div className="card-actions">
          <button type="submit">{authMode === 'register' ? 'Registrieren' : 'Anmelden'}</button>
          <button type="button" onClick={() => setAuthMode((m) => (m === 'login' ? 'register' : 'login'))}>
            {authMode === 'login' ? 'Zu Registrierung' : 'Zu Login'}
          </button>
        </div>
      </form>
      {authError ? <p className="info-note">{authError}</p> : null}
    </section>
  );

  if (isModal) {
    return (
      <div className="modal-backdrop" onClick={onClose}>
        <div className="modal-card auth-modal" onClick={(e) => e.stopPropagation()}>
          <div className="modal-head">
            <h3>{authMode === 'register' ? 'Registrieren' : 'Anmelden'}</h3>
            <button type="button" className="chat-minimize" onClick={onClose}>Schließen</button>
          </div>
          {formContent}
        </div>
      </div>
    );
  }

  return (
    <div className="app-shell">
      <header className="topbar">
        <div className="topbar-main">
          <h1>Familien-Rezepte</h1>
        </div>
      </header>
      <main className="content">
        {formContent}
      </main>
    </div>
  );
}
