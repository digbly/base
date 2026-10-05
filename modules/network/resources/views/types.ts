export type WebsiteStatus = 'active' | 'inactive' | 'suspended';

export type TrashedFilter = 'only' | 'with';

export interface AdminUser {
    id: string;
    name: string;
    email: string;
    roles: string[];
    is_super_admin: boolean;
    permissions: string[];
    email_verified_at: string | null;
    deleted_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface Role {
    id: number;
    name: string;
    guard_name?: string;
    permissions?: string[];
}

export interface Website {
    id: string;
    title: string;
    description?: string | null;
    subdomain: string;
    domain?: string | null;
    status: WebsiteStatus;
    status_label?: string;
    setup?: boolean;
    is_demo?: boolean;
    language?: string | null;
    theme?: string | null;
    database?: string | null;
    url?: string | null;
    user_id: string;
    owner?: AdminUser | null;
    users_count?: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface NetworkDashboard {
    stats: {
        websites: { total: number; active: number; inactive: number; suspended: number };
        users: { total: number; verified: number; unverified: number; trashed: number };
    };
    recent_websites: Website[];
    recent_users: AdminUser[];
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface Paginated<T> {
    data: T[];
    meta: PaginationMeta;
}

export const WEBSITE_STATUSES: WebsiteStatus[] = ['active', 'inactive', 'suspended'];

export const websiteStatusVariant = (status: WebsiteStatus): 'emerald' | 'slate' | 'amber' => {
    switch (status) {
        case 'active':
            return 'emerald';
        case 'suspended':
            return 'amber';
        default:
            return 'slate';
    }
};
