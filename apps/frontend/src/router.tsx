import { Outlet, createBrowserRouter, useRouteError, isRouteErrorResponse } from 'react-router-dom'
import { LoaderProvider } from '@/shared/layout/PageLoader'
import { LockedFeatureProvider } from '@/shared/layout/Locked'
import { LegalDeepLink } from '@/shared/layout/Legal'
import { SessionExpiredModal } from '@/shared/layout/Banners'
import { ErrorState } from '@/shared/ui/States'
import { PublicLayout } from '@/public-site/PublicLayout'
import Home from '@/public-site/pages/Home'
import { AddonsPage, FeatureDetailPage, FeaturesPage, NotFoundPage, PricingPage, TemplatesPage } from '@/public-site/pages/Catalog'
import { AboutPage, AffiliatePage, ContactPage, FaqPage, LegalPage, StatusPage } from '@/public-site/pages/Info'
import { AuthLayout } from '@/public-site/auth/AuthLayout'
import { AccountStatusPage, ForgotPasswordPage, LoginPage, RegisterPage, ResetPasswordPage, ReviseAccountPage, VerifyPage } from '@/public-site/auth/AuthPages'

function Root() {
  return (
    <LoaderProvider>
      <LockedFeatureProvider>
        <Outlet />
        <LegalDeepLink />
        <SessionExpiredModal />
      </LockedFeatureProvider>
    </LoaderProvider>
  )
}

function RouteError() {
  const err = useRouteError()
  const status = isRouteErrorResponse(err) ? err.status : 500
  return <ErrorState status={status === 404 ? 404 : 500} onRetry={() => window.location.reload()} />
}

export const router = createBrowserRouter([
  {
    element: <Root />,
    errorElement: <RouteError />,
    children: [
      {
        element: <PublicLayout />,
        children: [
          { index: true, element: <Home /> },
          { path: 'features', element: <FeaturesPage /> },
          { path: 'features/:slug', element: <FeatureDetailPage /> },
          { path: 'pricing', element: <PricingPage /> },
          { path: 'addons', element: <AddonsPage /> },
          { path: 'templates', element: <TemplatesPage /> },
          { path: 'affiliate', element: <AffiliatePage /> },
          { path: 'faq', element: <FaqPage /> },
          { path: 'contact', element: <ContactPage /> },
          { path: 'about', element: <AboutPage /> },
          { path: 'status', element: <StatusPage /> },
          { path: 'terms', element: <LegalPage type="terms" /> },
          { path: 'privacy', element: <LegalPage type="privacy" /> },
          { path: 'refund', element: <LegalPage type="refund" /> },
          { path: 'affiliate-terms', element: <LegalPage type="affiliate_terms" /> },
          { path: 'cookies', element: <LegalPage type="cookies" /> },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
      {
        element: <AuthLayout />,
        children: [
          { path: 'login', element: <LoginPage /> },
          { path: 'register', element: <RegisterPage /> },
          { path: 'forgot-password', element: <ForgotPasswordPage /> },
          { path: 'reset-password', element: <ResetPasswordPage /> },
          { path: 'verify', element: <VerifyPage /> },
          { path: 'account-status', element: <AccountStatusPage /> },
          { path: 'account-status/revise', element: <ReviseAccountPage /> },
        ],
      },
      // Panel dimuat lazy: memicu loading screen (navigation.state) dan memisahkan bundle per panel.
      { path: 'app/*', lazy: async () => ({ Component: (await import('@/user-panel/UserApp')).default }) },
      { path: 'admin/*', lazy: async () => ({ Component: (await import('@/admin-panel/AdminApp')).default }) },
    ],
  },
])
