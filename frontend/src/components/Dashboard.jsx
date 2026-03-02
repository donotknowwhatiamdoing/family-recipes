import GreetingBanner from './GreetingBanner';
import FavoritesRow from './FavoritesRow';
import RecentRecipesRow from './RecentRecipesRow';
import IdeasSection from './IdeasSection';

export default function Dashboard({ mealSlot, user, partyId, partyName, onSelectRecipe }) {
  return (
    <>
      <GreetingBanner user={user} partyName={partyName} mealSlot={mealSlot} dateText={mealSlot.dateText} />
      {user ? <FavoritesRow onSelectRecipe={onSelectRecipe} /> : null}
      <RecentRecipesRow partyId={partyId} user={user} onSelectRecipe={onSelectRecipe} />
      <IdeasSection partyId={partyId} user={user} currentMealSlot={mealSlot.currentMealSlot} onSelectRecipe={onSelectRecipe} />
    </>
  );
}
