import { usePage } from '@inertiajs/vue3'

export function useRoles() {
    const page = usePage()
    const user = page.props.auth?.user

    function hasRole(role) {
        if (!user) return false
        if (user.is_admin) return true
        return (user.roles || []).some(r => r.role === role)
    }

    function getTier() {
        return user?.acct_tier ?? 0
    }

    const canEditPrice     = page.props.canEditPrice ?? false
    const canApplyDiscount = page.props.canApplyDiscount ?? false
    const canDeleteSale    = getTier() >= 3 || hasRole('SaleDelete')
    const canProcessReturn = getTier() >= 1 || hasRole('SaleReturn')
    const canViewBuyPrice  = getTier() >= 2 || hasRole('ReportView')
    const canManageUsers   = getTier() >= 3 || hasRole('UserManage')
    const canEditSettings  = getTier() >= 3 || hasRole('SettingsEdit')

    return {
        user,
        hasRole,
        getTier,
        canEditPrice,
        canApplyDiscount,
        canDeleteSale,
        canProcessReturn,
        canViewBuyPrice,
        canManageUsers,
        canEditSettings,
    }
}
