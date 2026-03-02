export default function IdeaCard({ idea }) {
  return (
    <article className="idea-card">
      <h3>{idea.title}</h3>
      <p>{idea.text}</p>
      {idea.action ? <span className="idea-action">{idea.action}</span> : null}
    </article>
  );
}
