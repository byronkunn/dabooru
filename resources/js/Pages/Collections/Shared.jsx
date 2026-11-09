import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import CollectionView from '@/Components/CollectionView';
import useLibraryRefresh from '@/Lib/useLibraryRefresh';

export default function Shared({ name, ...view }) {
    useLibraryRefresh();
    return <AppLayout rightRail={false}>
        <Head title={name} />
        <CollectionView {...view} readOnly />
    </AppLayout>;
}
