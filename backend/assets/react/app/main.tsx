import '../../styles/app.css';
import {StrictMode} from 'react';
import {createRoot} from 'react-dom/client';
import {RouterProvider} from 'react-router';
import {AppProviders} from './providers/AppProviders';
import {router} from './router';

// After a deploy the old page's lazy chunks are gone; reload once to get the new build instead of a blank screen.
window.addEventListener('vite:preloadError', () => {
  window.location.reload();
});

const root = document.getElementById('root');
if (root) {
  createRoot(root).render(
    <StrictMode>
      <AppProviders>
        <RouterProvider router={router} />
      </AppProviders>
    </StrictMode>,
  );
}
