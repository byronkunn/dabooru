export const categoryLabel = (category) => ({
    genre: 'Genre', characters: 'Characters', franchise: 'Franchise', anatomies: 'Anatomies',
    poses: 'Poses', activity: 'Activity', substance: 'Substance', settings: 'Settings',
    language: 'Language', entity: 'Entity', artist: 'Artist',
})[category] ?? category.charAt(0).toUpperCase() + category.slice(1);

export const categoryColor = (category) => ({
    genre: '!border-rose-400/50 !bg-rose-400/10 !text-rose-200',
    characters: '!border-fuchsia-400/50 !bg-fuchsia-400/10 !text-fuchsia-200',
    franchise: '!border-purple-400/50 !bg-purple-400/10 !text-purple-200',
    anatomies: '!border-orange-400/50 !bg-orange-400/10 !text-orange-200',
    poses: '!border-sky-400/50 !bg-sky-400/10 !text-sky-200',
    activity: '!border-red-400/50 !bg-red-400/10 !text-red-200',
    substance: '!border-amber-400/50 !bg-amber-400/10 !text-amber-200',
    settings: '!border-emerald-400/50 !bg-emerald-400/10 !text-emerald-200',
    language: '!border-cyan-400/50 !bg-cyan-400/10 !text-cyan-200',
    entity: '!border-lime-400/50 !bg-lime-400/10 !text-lime-200',
    artist: '!border-pink-400/50 !bg-pink-400/10 !text-pink-200',
})[category] ?? '!border-slate-400/50 !bg-slate-400/10 !text-slate-200';
