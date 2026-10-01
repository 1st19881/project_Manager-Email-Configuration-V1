import type { Component } from 'vue'
import type { IconName } from '@/components/AppIcon.vue'
import MailNotificationManagerList from '@/components/MailNotificationManagerList.vue'
import MailTemplateWording from '@/components/MailTemplateWording.vue'
import MailTestForm from '@/components/MailTestForm.vue'
import NotificationLogDashboard from '@/components/NotificationLogDashboard.vue'

export interface MenuItem {
  key: string
  label: string
  icon: IconName
  path: string
  view: Component
}

const managersItem: MenuItem = {
  key: 'managers',
  label: 'ผู้รับอีเมล',
  icon: 'users',
  path: '/managers',
  view: MailNotificationManagerList,
}

export const menuItems: readonly MenuItem[] = [
  managersItem,
  {
    key: 'wording',
    label: 'ข้อความอีเมล',
    icon: 'file-text',
    path: '/wording',
    view: MailTemplateWording,
  },
  {
    key: 'test-mail',
    label: 'ทดสอบส่งอีเมล',
    icon: 'send',
    path: '/test-mail',
    view: MailTestForm,
  },
  {
    key: 'logs',
    label: 'Log การส่งอีเมล',
    icon: 'bar-chart',
    path: '/logs',
    view: NotificationLogDashboard,
  },
]

/** The menu item a "#/path" hash points at, falling back to the landing page. */
export function resolveMenuItem(hash: string): MenuItem {
  const path = hash.replace(/^#/, '') || managersItem.path

  return menuItems.find((item) => item.path === path) ?? managersItem
}
