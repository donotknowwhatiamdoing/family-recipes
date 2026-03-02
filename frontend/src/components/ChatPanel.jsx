export default function ChatPanel({ chatOpen, setChatOpen, chatMessage, setChatMessage, chatPending, messages, sendChat }) {
  if (chatOpen) {
    return (
      <aside className="chat-panel">
        <div className="chat-head">
          <div className="chat-head-row">
            <span>Chatbot</span>
            <button type="button" className="chat-minimize" onClick={() => setChatOpen(false)}>
              Minimieren
            </button>
          </div>
        </div>
        <div className="chat-messages">
          {messages.map((message, idx) => (
            <div key={idx} className={`msg ${message.role}`}>{message.text}</div>
          ))}
        </div>
        <form onSubmit={sendChat} className="chat-form">
          <input value={chatMessage} onChange={(e) => setChatMessage(e.target.value)} placeholder="Frage eingeben..." />
          <button type="submit" disabled={chatPending}>{chatPending ? '...' : 'Senden'}</button>
        </form>
      </aside>
    );
  }

  return (
    <button type="button" className="chat-launcher" onClick={() => setChatOpen(true)}>
      Chat
    </button>
  );
}
