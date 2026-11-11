import { Route, Routes } from 'react-router-dom'
import AdminLayout from './AdminLayout'
import AdminLogin from './AdminLogin'
import { ADMIN_ROUTES } from './adminRoutes'
import { AdminUserDetail } from './AdminPages'
import { TicketDetailPage, NotificationsPage } from '@/user-panel/pages/Account'
import { AuthLayout } from '@/public-site/auth/AuthLayout'
import { NotFoundPage } from '@/public-site/pages/Catalog'

// Admin Panel — chunk terpisah, tidak pernah dimuat oleh pengguna biasa (§20.3).
export default function AdminApp() {
  return (
    <Routes>
      <Route element={<AuthLayout />}>
        <Route path="login" element={<AdminLogin />} />
      </Route>
      <Route element={<AdminLayout />}>
        {ADMIN_ROUTES.map((r) => {
          const p = r.path.replace(/^\/admin\/?/, '')
          return p ? <Route key={r.path} path={p} element={r.el()} /> : <Route key={r.path} index element={r.el()} />
        })}
        <Route path="users/:id" element={<AdminUserDetail />} />
        <Route path="support/tickets/:id" element={<TicketDetailPage admin />} />
        <Route path="notifications" element={<NotificationsPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}

