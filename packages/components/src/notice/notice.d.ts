export interface NoticeAction {
	className?: string;
	label: React.ReactNode;
	onClick?: () => void;
	url?: string;
	variant?: 'primary' | 'secondary' | 'link';
}

export interface NoticeProps {
	children?: React.ReactNode;
	className?: string;
	/**
	 * Whether to render a close button. Defaults to `false`, unlike core, because a
	 * Newspack notice reports an outcome rather than queuing for removal.
	 */
	isDismissible?: boolean;
	/**
	 * Announcement text. When omitted it is derived from `children` for `error` and
	 * `success` notices only; pass a string to announce any status, or an empty
	 * string to silence one.
	 */
	spokenMessage?: string;
	status?: 'error' | 'warning' | 'success' | 'info';
	politeness?: 'polite' | 'assertive';
	/** Without this, `isDismissible` renders a close button that does nothing. */
	onRemove?: () => void;
	/** Deprecated core alias for `onRemove`. */
	onDismiss?: () => void;
	actions?: NoticeAction[];
	/** Renders string children as raw HTML; opt in only for trusted markup. */
	__unstableHTML?: boolean;
}
