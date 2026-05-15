import AdminLayout from '@/Layouts/AdminLayout';

export function withAdminLayout(Page) {
    function WrappedPage(props) {
        return (
            <AdminLayout>
                <Page {...props} />
            </AdminLayout>
        );
    }

    WrappedPage.displayName = `withAdminLayout(${Page.displayName ?? Page.name ?? 'Page'})`;

    return WrappedPage;
}
