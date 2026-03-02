import { useState } from 'react';
import useAuth from './hooks/useAuth';
import useMealSlot from './hooks/useMealSlot';
import useChat from './hooks/useChat';
import AuthForm from './components/AuthForm';
import Topbar from './components/Topbar';
import PartyPicker from './components/PartyPicker';
import Dashboard from './components/Dashboard';
import MyRecipes from './components/MyRecipes';
import RecipeModal from './components/RecipeModal';
import RecipeDetail from './components/RecipeDetail';
import ChatPanel from './components/ChatPanel';

export default function App() {
  const auth = useAuth();
  const mealSlot = useMealSlot();
  const chat = useChat();
  const [showRecipeModal, setShowRecipeModal] = useState(false);
  const [viewRecipeId, setViewRecipeId] = useState(null);
  const [selectedParty, setSelectedParty] = useState(null);
  const [view, setView] = useState('home');
  const [showAuthModal, setShowAuthModal] = useState(false);

  function handleSelectParty(party) {
    setSelectedParty(party);
    setView('dashboard');
  }

  function handleBackToParties() {
    setSelectedParty(null);
    setView('home');
  }

  function handleMyRecipes() {
    setView('my-recipes');
  }

  function handleBackFromMyRecipes() {
    if (selectedParty) {
      setView('dashboard');
    } else {
      setView('home');
    }
  }

  function handleLogout() {
    auth.logout();
  }

  return (
    <div className="app-shell">
      <Topbar
        user={auth.user}
        logout={handleLogout}
        dateText={mealSlot.dateText}
        timeText={mealSlot.timeText}
        onNewRecipe={() => setShowRecipeModal(true)}
        onLogin={() => setShowAuthModal(true)}
        onBackToParties={selectedParty ? handleBackToParties : null}
        onMyRecipes={handleMyRecipes}
        partyName={selectedParty?.name}
      />

      <main className="content">
        {view === 'home' ? (
          <PartyPicker onSelectParty={handleSelectParty} />
        ) : null}

        {view === 'dashboard' && selectedParty ? (
          <Dashboard
            mealSlot={mealSlot}
            user={auth.user}
            partyId={selectedParty.id}
            partyName={selectedParty.name}
            onSelectRecipe={setViewRecipeId}
          />
        ) : null}

        {view === 'my-recipes' ? (
          <MyRecipes onBack={handleBackFromMyRecipes} onSelectRecipe={setViewRecipeId} />
        ) : null}
      </main>

      <ChatPanel {...chat} />

      {showAuthModal ? (
        <AuthForm
          {...auth}
          isModal={true}
          onClose={() => setShowAuthModal(false)}
          onSuccess={() => setShowAuthModal(false)}
        />
      ) : null}

      {showRecipeModal ? (
        <RecipeModal onClose={() => setShowRecipeModal(false)} />
      ) : null}

      {viewRecipeId != null ? (
        <RecipeDetail
          recipeId={viewRecipeId}
          onClose={() => setViewRecipeId(null)}
          user={auth.user}
        />
      ) : null}
    </div>
  );
}
