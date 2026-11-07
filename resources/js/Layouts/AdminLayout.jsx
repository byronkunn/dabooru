import AppLayout, { TopBar } from '@/Layouts/AppLayout';

export default function AdminLayout({ children }) {
    return <AppLayout admin rightRail={false}>{children}</AppLayout>;
}

export function AdminTopBar(props) {
    return <TopBar {...props} siteActions={false} />;
}
