/**
 * Notice
 */

/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { Notice as BaseComponent } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

/**
 * Flattens a node to plain text for the screen reader announcement.
 *
 * Core derives its announcement from `children`, and reaches for `renderToString`
 * whenever they are not already a string. That runs mid-render and corrupts the
 * hook order of anything it walks, so a notice holding elements crashes on its
 * next render. Resolving the text ourselves keeps `spokenMessage` a string, which
 * is the branch core takes without rendering anything.
 *
 * @param {*} node A React node.
 * @return {string} The node's text content, empty when there is none to read.
 */
const toText = node => {
	if ( typeof node === 'string' || typeof node === 'number' ) {
		return String( node );
	}
	if ( Array.isArray( node ) ) {
		return node.map( toText ).filter( Boolean ).join( ' ' );
	}
	if ( node?.props?.children !== undefined && node?.props?.children !== null ) {
		return toText( node.props.children );
	}
	return '';
};

/**
 * Strips markup from a derived announcement. The live region filters markup on its
 * own, but stripping here (with the same tag pattern it uses) keeps the derived
 * value plain text with tidy whitespace rather than relying on that behaviour.
 *
 * @param {string} text The derived announcement.
 * @return {string} The announcement without tags, with collapsed whitespace.
 */
const stripTags = text =>
	text
		.replace( /<[^<>]+>/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();

const ANNOUNCED_STATUSES = [ 'error', 'success' ];

/**
 * Notice.
 *
 * Wraps the core `Notice` so Newspack admin screens have a single place to change
 * when the design system's own notice is ready to adopt. It supports exactly the
 * props core reads, with two house defaults documented on `NoticeProps`:
 * not dismissible, and announced only for `error`/`success` statuses, because
 * contextual content does not belong in the live region and simultaneous
 * announcements cancel each other.
 *
 * @param {import('./notice.d').NoticeProps} props Component props.
 */
const Notice = ( { children, className, isDismissible = false, spokenMessage, status = 'info', ...otherProps } ) => (
	<BaseComponent
		className={ classnames( 'newspack-notice', className ) }
		isDismissible={ isDismissible }
		spokenMessage={ spokenMessage ?? ( ANNOUNCED_STATUSES.includes( status ) ? stripTags( toText( children ) ) : '' ) }
		status={ status }
		{ ...otherProps }
	>
		{ children }
	</BaseComponent>
);

export default Notice;
