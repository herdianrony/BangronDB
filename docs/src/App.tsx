import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from './context/ThemeContext';
import Landing from './pages/Landing';
import Docs from './pages/Docs';

export default function App() {
  return (
    <ThemeProvider>
      <HashRouter>
        <Routes>
          <Route path="/" element={<Landing />} />
          <Route path="/docs" element={<Navigate to="/docs/getting-started" replace />} />
          <Route path="/docs/:slug" element={<Docs />} />
        </Routes>
      </HashRouter>
    </ThemeProvider>
  );
}
