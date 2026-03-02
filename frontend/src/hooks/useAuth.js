import { useEffect, useState } from 'react';
import { fetchMe, loginUser, registerUser } from '../api';

export default function useAuth() {
  const [user, setUser] = useState(null);
  const [authMode, setAuthMode] = useState('login');
  const [authForm, setAuthForm] = useState({
    party_name: '',
    display_name: '',
    email: '',
    password: '',
  });
  const [authError, setAuthError] = useState('');

  useEffect(() => {
    const token = window.localStorage.getItem('auth_token');
    if (!token) return;
    fetchMe()
      .then((data) => setUser(data.user))
      .catch(() => window.localStorage.removeItem('auth_token'));
  }, []);

  function onAuthInput(event) {
    const { name, value } = event.target;
    setAuthForm((prev) => ({ ...prev, [name]: value }));
  }

  async function onAuthSubmit(event) {
    event.preventDefault();
    setAuthError('');
    try {
      const payload =
        authMode === 'register'
          ? {
              party_name: authForm.party_name,
              display_name: authForm.display_name,
              email: authForm.email,
              password: authForm.password,
            }
          : {
              email: authForm.email,
              password: authForm.password,
            };
      const data = authMode === 'register' ? await registerUser(payload) : await loginUser(payload);
      window.localStorage.setItem('auth_token', data.token);
      setUser(data.user);
      return true;
    } catch (error) {
      setAuthError(error.message);
      return false;
    }
  }

  function logout() {
    window.localStorage.removeItem('auth_token');
    setUser(null);
  }

  return { user, authMode, setAuthMode, authForm, authError, onAuthInput, onAuthSubmit, logout };
}
