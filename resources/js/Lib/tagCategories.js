export const categoryLabel = (category) => ({
    clothing: 'Clothing / wearing',
    position: 'Pose / position',
    appearance: 'Appearance',
    setting: 'Setting / location',
    composition: 'Composition / framing',
    'content warnings': 'Content warnings',
})[category] ?? category.charAt(0).toUpperCase() + category.slice(1);

export const categoryColor = (category) => ({
    artist: '!border-rose-400/50 !bg-rose-400/10 !text-rose-200',
    character: '!border-fuchsia-400/50 !bg-fuchsia-400/10 !text-fuchsia-200',
    series: '!border-purple-400/50 !bg-purple-400/10 !text-purple-200',
    medium: '!border-blue-400/50 !bg-blue-400/10 !text-blue-200',
    style: '!border-indigo-400/50 !bg-indigo-400/10 !text-indigo-200',
    subject: '!border-lime-400/50 !bg-lime-400/10 !text-lime-200',
    anatomy: '!border-orange-400/50 !bg-orange-400/10 !text-orange-200',
    clothing: '!border-pink-400/50 !bg-pink-400/10 !text-pink-200',
    position: '!border-sky-400/50 !bg-sky-400/10 !text-sky-200',
    appearance: '!border-violet-400/50 !bg-violet-400/10 !text-violet-200',
    setting: '!border-emerald-400/50 !bg-emerald-400/10 !text-emerald-200',
    composition: '!border-cyan-400/50 !bg-cyan-400/10 !text-cyan-200',
    relationships: '!border-red-400/50 !bg-red-400/10 !text-red-200',
    themes: '!border-amber-400/50 !bg-amber-400/10 !text-amber-200',
    'content warnings': '!border-yellow-400/50 !bg-yellow-400/10 !text-yellow-200',
    general: '!border-slate-400/50 !bg-slate-400/10 !text-slate-200',
})[category] ?? '!border-slate-400/50 !bg-slate-400/10 !text-slate-200';
