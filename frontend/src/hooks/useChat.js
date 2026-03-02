import { useState } from 'react';
import { askChatbot } from '../api';

export default function useChat() {
  const [chatOpen, setChatOpen] = useState(false);
  const [chatMessage, setChatMessage] = useState('');
  const [chatPending, setChatPending] = useState(false);
  const [messages, setMessages] = useState([
    { role: 'assistant', text: 'Ich helfe dir beim Suchen und Kochen. Frag mich nach Rezepten.' },
  ]);

  async function sendChat(event) {
    event.preventDefault();
    const message = chatMessage.trim();
    if (!message || chatPending) return;
    setMessages((prev) => [...prev, { role: 'user', text: message }]);
    setChatMessage('');
    setChatPending(true);
    try {
      const reply = await askChatbot(message);
      setMessages((prev) => [...prev, { role: 'assistant', text: reply }]);
    } catch {
      setMessages((prev) => [...prev, { role: 'assistant', text: 'Der Chatbot ist gerade nicht erreichbar.' }]);
    } finally {
      setChatPending(false);
    }
  }

  return { chatOpen, setChatOpen, chatMessage, setChatMessage, chatPending, messages, sendChat };
}
