import { Head, useForm } from '@inertiajs/react';
import AdminLayout, { AdminTopBar } from '@/Layouts/AdminLayout';
import { AdminErrors } from '@/Components/AdminUi';
import { Icon } from '@/Components/Icons';

const PLACEHOLDER = {
    monero: '4… (95 characters, starts with 4 or 8)',
    bitcoin: 'bc1… or 1… or 3…',
    ethereum: '0x… (42 characters)',
    solana: 'base58, 32–44 characters',
};

/** Rough shape check so an obvious typo is caught before it goes live. */
function looksWrong(network, value) {
    const v = (value || '').trim();
    if (v === '') return null;
    if (network === 'monero' && !/^[48][0-9A-Za-z]{94}$/.test(v)) return 'A Monero address is 95 characters and starts with 4 or 8.';
    if (network === 'bitcoin' && !/^(bc1[a-z0-9]{25,62}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/.test(v)) return 'That does not look like a Bitcoin address.';
    if (network === 'ethereum' && !/^0x[0-9a-fA-F]{40}$/.test(v)) return 'An Ethereum address is 0x followed by 40 hex characters.';
    if (network === 'solana' && !/^[1-9A-HJ-NP-Za-km-z]{32,44}$/.test(v)) return 'That does not look like a Solana address.';

    return null;
}

export default function AdminTips({ networks = [], receivesInto, totals = {} }) {
    const form = useForm({
        addresses: Object.fromEntries(networks.map((n) => [n.network, n.address ?? ''])),
    });

    const warnings = Object.fromEntries(
        networks.map((n) => [n.network, looksWrong(n.network, form.data.addresses[n.network])]).filter(([, w]) => w),
    );

    const save = (e) => {
        e.preventDefault();
        form.patch(route('admin.tips.update'), { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="Tip wallets" />
            <AdminTopBar title="Tip wallets" subtitle={`${totals.configured ?? 0} of ${totals.available ?? 4} configured`} />

            <div className="mx-auto max-w-[760px] space-y-3 p-3.5 md:p-5">
                <div className="panel space-y-2">
                    <p className="text-sm text-ink-muted">
                        These are the addresses shown on the public <a href={route('tip')} className="text-ink-brand hover:underline">Tip page</a>.
                        {receivesInto && <> They belong to <b className="text-ink-text">{receivesInto.name}</b> (@{receivesInto.username}).</>}
                    </p>
                    <p className="text-[12.5px] text-ink-faint">
                        Visitors see the network, a shortened address and a copy button that puts the full address on
                        their clipboard.
                    </p>
                </div>

                <form onSubmit={save} className="space-y-3">
                    {networks.map((network) => {
                        const value = form.data.addresses[network.network] ?? '';
                        const warning = warnings[network.network];
                        return (
                            <div key={network.network} className="panel space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-bold">{network.label}</span>
                                    <span className="rounded bg-ink-raised px-1.5 py-0.5 text-[11px] font-bold text-ink-muted">{network.symbol}</span>
                                    {network.hint && <span className="text-[11.5px] text-ink-faint">{network.hint}</span>}
                                    <span className={`ml-auto inline-flex items-center gap-1 text-[11.5px] font-semibold ${value.trim() ? 'text-ink-sfw' : 'text-ink-faint'}`}>
                                        <span className={`h-1.5 w-1.5 rounded-full ${value.trim() ? 'bg-ink-sfw' : 'bg-ink-faint'}`} />
                                        {value.trim() ? 'Set' : 'Not set'}
                                    </span>
                                </div>

                                <input
                                    value={value}
                                    onChange={(e) => form.setData('addresses', { ...form.data.addresses, [network.network]: e.target.value })}
                                    placeholder={PLACEHOLDER[network.network] ?? 'Address'}
                                    aria-label={`${network.label} address`}
                                    spellCheck={false}
                                    autoComplete="off"
                                    className="field-input min-h-[42px] w-full font-mono text-[12.5px]"
                                />
                                {warning && <p className="text-[12px] text-ink-expl">{warning}</p>}
                                <AdminErrors errors={form.errors} />
                            </div>
                        );
                    })}

                    <div className="flex flex-wrap items-center gap-2">
                        <button type="submit" disabled={form.processing} className="btn-primary min-h-11 px-5">
                            {form.processing ? 'Saving…' : 'Save wallets'}
                        </button>
                        {form.recentlySuccessful && <span role="status" className="text-[13px] text-ink-sfw">Saved.</span>}
                        {Object.keys(warnings).length > 0 && (
                            <span className="text-[12.5px] text-ink-expl">Fix the warnings above before saving.</span>
                        )}
                    </div>
                </form>

                <div className="panel flex items-start gap-2.5 text-[12.5px] text-ink-muted">
                    <Icon name="flag" className="mt-0.5 h-4 w-4 shrink-0 text-ink-faint" />
                    <p>
                        Sending to a wrong address is unrecoverable. Clearing a field removes that network from the tip
                        page entirely.
                    </p>
                </div>
            </div>
        </AdminLayout>
    );
}
