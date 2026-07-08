import PageHeader from '@/Components/common/PageHeader';
import GuideCard from '@/Components/help-support/GuideCard';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Building2,
    Calculator,
    ChartNoAxesCombined,
    Monitor,
    Package,
    Settings2,
    Truck,
    UsersRound,
} from 'lucide-react';

function ComingSoonBadge() {
    return (
        <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:text-amber-300">
            Coming Soon
        </span>
    );
}

export default function Index() {
    return (
        <AdminLayout>
            <Head title="Help & Support" />

            <PageHeader
                title="Help & Support"
                description="Browse product guides and learn how to use RetailPulse effectively."
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <GuideCard
                    title="Accounting & Financial Management"
                    description="Learn chart of accounts, journals, posting rules, fiscal years, tax, reports, and automatic accounting entries."
                    icon={Calculator}
                    status="available"
                    href="/help-support/guides/accounting"
                />

                <GuideCard
                    title="Point of Sale"
                    description="Learn checkout, cash registers, shifts, receipts, returns, and payment handling."
                    icon={Monitor}
                    status="comingSoon"
                    badge={<ComingSoonBadge />}
                />

                <GuideCard
                    title="Sales & Customers"
                    description="Learn sales orders, invoices, customer profiles, credit, loyalty, and returns."
                    icon={UsersRound}
                    status="available"
                    href="/help-support/guides/customers-loyalty"
                />

                <GuideCard
                    title="Inventory Management"
                    description="Learn products, stock levels, adjustments, transfers, warehouses, and stock counts."
                    icon={Package}
                    status="available"
                    href="/help-support/guides/inventory-catalogue"
                />

                <GuideCard
                    title="Purchasing & Suppliers"
                    description="Learn suppliers, purchase orders, goods receiving, supplier invoices, and payments."
                    icon={Truck}
                    status="comingSoon"
                    badge={<ComingSoonBadge />}
                />

                <GuideCard
                    title="Branches & Organization"
                    description="Learn branches, warehouses, users, roles, permissions, and business configuration."
                    icon={Building2}
                    status="comingSoon"
                    badge={<ComingSoonBadge />}
                />

                <GuideCard
                    title="Reports & Analytics"
                    description="Learn operational reports, financial reports, exports, and dashboards."
                    icon={ChartNoAxesCombined}
                    status="comingSoon"
                    badge={<ComingSoonBadge />}
                />

                <GuideCard
                    title="Settings & Integrations"
                    description="Learn company settings, taxes, payment settings, FBR / IRIS integration, and imports."
                    icon={Settings2}
                    status="comingSoon"
                    badge={<ComingSoonBadge />}
                />

                <GuideCard
                    title="HR & Payroll"
                    description="Learn employees, attendance, payroll, leave, and salary processing."
                    icon={BriefcaseBusiness}
                    status="comingSoon"
                    badge={<ComingSoonBadge />}
                />
            </div>

            <div className="mt-6">
                <h3 className="text-sm font-semibold text-ink-900 dark:text-white">
                    Quick Guides
                </h3>
                <p className="mt-1 text-sm text-ink-500 dark:text-ink-300">
                    Short, task-focused walkthroughs.
                </p>

                <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <GuideCard
                        title="Put a Product in Stock (Any Branch)"
                        description="Receive, adjust, transfer, or import opening stock—then verify it’s sellable on POS."
                        icon={Package}
                        status="available"
                        href="/help-support/guides/put-product-in-stock"
                    />
                </div>
            </div>
        </AdminLayout>
    );
}

