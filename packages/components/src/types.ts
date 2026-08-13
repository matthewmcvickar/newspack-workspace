/**
 * WordPress dependencies
 */
import type { Badge } from '@wordpress/ui';

// @wordpress/ui does not export BadgeProps, so the intent union is derived from
// the component itself. Declared once here so the components that expose it as a
// public prop cannot drift apart when the library adds an intent.
export type BadgeIntent = NonNullable< React.ComponentProps< typeof Badge >[ 'intent' ] >;
