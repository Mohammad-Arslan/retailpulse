import PageHeader from '@/Components/common/PageHeader';
import { withAdminLayout } from '@/HOCs/withAdminLayout';
import { Head, Link } from '@inertiajs/react';

function Index({ grns, filters }) {
    return (
        <>
            <Head title="Goods receiving notes" />
            <PageHeader title="Goods receiving notes" description="GRN history and pending receipts" />

            <div className="overflow-hidden rounded-lg border bg-card">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-muted/40 text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3">Reference</th>
                            <th className="px-4 py-3">Supplier</th>
                            <th className="px-4 py-3">PO</th>
                            <th className="px-4 py-3">Warehouse</th>
                            <th className="px-4 py-3">Received</th>
                            <th className="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {grns.data?.length ? (
                            grns.data.map((grn) => (
                                <tr key={grn.id} className="border-b">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={route('admin.goods-receiving-notes.show', grn.id)}
                                            className="text-teal-600 hover:underline"
                                        >
                                            {grn.reference_no}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{grn.supplier}</td>
                                    <td className="px-4 py-3">{grn.purchase_order}</td>
                                    <td className="px-4 py-3">{grn.warehouse}</td>
                                    <td className="px-4 py-3">{grn.received_at?.slice(0, 10)}</td>
                                    <td className="px-4 py-3">{grn.status}</td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                                    No GRNs found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}

export default withAdminLayout(Index);
