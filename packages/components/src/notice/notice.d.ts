export interface NoticeAction {
	className?: string;
	label: React.ReactNode;
	onClick?: () => void;
	url?: string;
	variant?: 'primary' | 'secondary' | 'link';
}

export interface NoticeProps {
	/** Notice content. */
	children?: React.ReactNode;
	/** Additional class name. */
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
	/** Notice status, as core defines it. Defaults to `info`. */
	status?: 'error' | 'warning' | 'success' | 'info';
	/** Overrides the politeness core derives from `status`. */
	politeness?: 'polite' | 'assertive';
	/** Called when the close button is clicked; required for `isDismissible` to be useful. */
	onRemove?: () => void;
	/** Deprecated core alias for `onRemove`. */
	onDismiss?: () => void;
	/** Action buttons or links rendered after the content. */
	actions?: NoticeAction[];
	/** Renders string children as raw HTML; opt in only for trusted markup. */
	__unstableHTML?: boolean;
}
