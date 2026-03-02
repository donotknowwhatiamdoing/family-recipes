import { useRef, useState } from 'react';
import { importFromUrl, importFromFile } from '../api';

export default function ImportDropZone({ onImported }) {
  const [urlInput, setUrlInput] = useState('');
  const [importing, setImporting] = useState(false);
  const [importError, setImportError] = useState('');
  const [dragOver, setDragOver] = useState(false);
  const fileRef = useRef(null);

  async function handleUrlImport() {
    const url = urlInput.trim();
    if (!url) return;
    setImporting(true);
    setImportError('');
    try {
      const data = await importFromUrl(url);
      if (data.recipe) {
        onImported(data.recipe);
        setUrlInput('');
      }
    } catch (err) {
      setImportError(err.message || 'Import fehlgeschlagen');
    } finally {
      setImporting(false);
    }
  }

  async function handleFileImport(file) {
    if (!file) return;
    setImporting(true);
    setImportError('');
    try {
      const data = await importFromFile(file);
      if (data.recipe) {
        onImported(data.recipe);
      }
    } catch (err) {
      setImportError(err.message || 'Import fehlgeschlagen');
    } finally {
      setImporting(false);
    }
  }

  function onDrop(e) {
    e.preventDefault();
    setDragOver(false);

    const text = e.dataTransfer.getData('text/plain') || e.dataTransfer.getData('text/uri-list');
    if (text && (text.startsWith('http://') || text.startsWith('https://'))) {
      setUrlInput(text);
      return;
    }

    const file = e.dataTransfer.files?.[0];
    if (file) {
      handleFileImport(file);
    }
  }

  function onDragOver(e) {
    e.preventDefault();
    setDragOver(true);
  }

  function onDragLeave() {
    setDragOver(false);
  }

  function onFileChange(e) {
    const file = e.target.files?.[0];
    if (file) handleFileImport(file);
  }

  return (
    <div
      className={`import-zone ${dragOver ? 'drag-over' : ''}`}
      onDrop={onDrop}
      onDragOver={onDragOver}
      onDragLeave={onDragLeave}
    >
      <p className="import-zone-label">
        Rezept importieren: URL einfügen oder Datei hierher ziehen
      </p>
      <div className="import-row">
        <input
          type="url"
          placeholder="https://www.chefkoch.de/rezepte/..."
          value={urlInput}
          onChange={(e) => setUrlInput(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleUrlImport(); } }}
          disabled={importing}
        />
        <button
          type="button"
          className="primary-btn"
          onClick={handleUrlImport}
          disabled={importing || !urlInput.trim()}
        >
          {importing ? '...' : 'Importieren'}
        </button>
      </div>
      <p className="import-or">oder</p>
      <button
        type="button"
        className="import-file-btn"
        onClick={() => fileRef.current?.click()}
        disabled={importing}
      >
        Datei auswählen (PDF, TXT, MD, DOCX, Bild)
      </button>
      <input
        ref={fileRef}
        type="file"
        accept=".pdf,.txt,.md,.docx,.jpg,.jpeg,.png,.webp"
        style={{ display: 'none' }}
        onChange={onFileChange}
      />
      {importing ? <p className="import-status">Rezept wird extrahiert ...</p> : null}
      {importError ? <p className="info-note">{importError}</p> : null}
    </div>
  );
}
