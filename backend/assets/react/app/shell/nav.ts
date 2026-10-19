import {
  BarChart3,
  CalendarDays,
  Car,
  Handshake,
  Inbox,
  ListTodo,
  Settings,
  UserPlus,
  Users,
  UsersRound,
  type LucideIcon,
} from 'lucide-react';
import type {Role} from '@/entities/user';

export interface NavItem {
  to: string;
  labelKey: string;
  icon: LucideIcon;
  /** Shown only to users who hold this role (after the hierarchy). */
  role?: Role;
  /** Part of the phone's bottom tab bar (DESIGN.md "Layout"). */
  tab?: boolean;
}

export const NAV: NavItem[] = [
  {to: '/today', labelKey: 'nav.today', icon: ListTodo, tab: true},
  {to: '/inbox', labelKey: 'nav.inbox', icon: Inbox, tab: true},
  {to: '/customers', labelKey: 'nav.customers', icon: Users, tab: true},
  {to: '/inventory', labelKey: 'nav.inventory', icon: Car, tab: true},
  {to: '/leads', labelKey: 'nav.leads', icon: UserPlus},
  {to: '/deals', labelKey: 'nav.deals', icon: Handshake},
  {to: '/appointments', labelKey: 'nav.appointments', icon: CalendarDays},
  {
    to: '/dashboard',
    labelKey: 'nav.dashboard',
    icon: BarChart3,
    role: 'manager',
  },
  {to: '/team', labelKey: 'nav.team', icon: UsersRound, role: 'manager'},
  {to: '/settings', labelKey: 'nav.settings', icon: Settings},
];
