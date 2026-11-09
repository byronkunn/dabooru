import { Link } from '@inertiajs/react';
import { Icon } from '@/Components/Icons';

/**
 * One consistent empty state.
 *
 * A view with no data should say what would appear here and offer the action
 * that puts something in it, rather than rendering an empty page.
 */
export default function EmptyState({
    icon = 'gallery',
    title,
    description = null,
    action = null,
    secondary = null,
    compact = false,
}) {
    return (
        <div className={`grid place-items-center text-center ${compact ? 'px-4 py-10' : 'px-4 py-20'}`} role="status">
            <span className={`grid place-items-center rounded-2xl bg-ink-raised text-ink-faint ${compact ? 'h-11 w-11' : 'h-14 w-14'}`}>
                <Icon name={icon} className={compact ? 'h-5 w-5' : 'h-7 w-7'} />
            </span>
            <p className={`mt-3 font-bold ${compact ? 'text-[14px]' : 'text-[16px]'}`}>{title}</p>
            {description && (
                <p className="mt-1.5 max-w-[380px] text-[13px] leading-relaxed text-ink-muted">{description}</p>
            )}
            {(action || secondary) && (
                <div className="mt-4 flex flex-wrap items-center justify-center gap-2">
                    {action && (
                        <Link href={action.href} className="btn-primary min-h-10 px-4 text-[13px]">
                            {action.label}
                        </Link>
                    )}
                    {secondary && (
                        <Link href={secondary.href} className="btn-ghost min-h-10 px-4 text-[13px]">
                            {secondary.label}
                        </Link>
                    )}
                </div>
            )}
        </div>
    );
}
