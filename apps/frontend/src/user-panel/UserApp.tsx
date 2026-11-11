import { Route, Routes } from 'react-router-dom'
import UserLayout from './UserLayout'
import Dashboard from './pages/Dashboard'
import Studio from './pages/Studio'
import { WalletPage, TopupPage, WithdrawalsPage } from './pages/Wallet'
import { SubscriptionPage, AddonsUserPage, CustomPackagesPage, AffiliateUserPage } from './pages/Billing'
import { UsagePage, NotificationsPage, SupportPage, TicketDetailPage, SettingsPage } from './pages/Account'
import { UserTemplatesPage, ProjectsPage, ProjectDetailPage, AssetsPage, AssistantPage, WebsitesPage, WebsiteEditorPage, SeoPage, SocialPage } from './pages/Workspace'
import { NotFoundPage } from '@/public-site/pages/Catalog'

// User Panel (PRD 2.1-B) — dimuat sebagai chunk terpisah.
export default function UserApp() {
  return (
    <Routes>
      <Route element={<UserLayout />}>
        <Route index element={<Dashboard />} />
        <Route path="create/:kind" element={<Studio />} />
        <Route path="templates" element={<UserTemplatesPage />} />
        <Route path="projects" element={<ProjectsPage />} />
        <Route path="projects/:id" element={<ProjectDetailPage />} />
        <Route path="assets" element={<AssetsPage />} />
        <Route path="assistant" element={<AssistantPage />} />
        <Route path="websites" element={<WebsitesPage />} />
        <Route path="websites/:id" element={<WebsiteEditorPage />} />
        <Route path="seo" element={<SeoPage />} />
        <Route path="social" element={<SocialPage />} />
        <Route path="subscription" element={<SubscriptionPage />} />
        <Route path="addons" element={<AddonsUserPage />} />
        <Route path="custom-packages" element={<CustomPackagesPage />} />
        <Route path="wallet" element={<WalletPage />} />
        <Route path="wallet/topup" element={<TopupPage />} />
        <Route path="wallet/withdrawals" element={<WithdrawalsPage />} />
        <Route path="affiliate" element={<AffiliateUserPage />} />
        <Route path="usage" element={<UsagePage />} />
        <Route path="notifications" element={<NotificationsPage />} />
        <Route path="support" element={<SupportPage />} />
        <Route path="support/:id" element={<TicketDetailPage />} />
        <Route path="settings" element={<SettingsPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}
