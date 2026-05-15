import GuestLayout from '@/Layouts/GuestLayout';

export function withGuestLayout(Page) {
    function WrappedPage(props) {
        return (
            <GuestLayout>
                <Page {...props} />
            </GuestLayout>
        );
    }

    WrappedPage.displayName = `withGuestLayout(${Page.displayName ?? Page.name ?? 'Page'})`;

    return WrappedPage;
}
