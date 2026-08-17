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
	if ( node?.props?.children ) {
		return toText( node.props.children );
	}
	return '';
};

/**
 * Notice.
 *
 * Wraps the core `Notice` so Newspack admin screens have a single place to change
 * when the design system's own notice is ready to adopt. Every core prop is
 * supported; see the `@wordpress/components` documentation for the full list.
 *
 * @param {Object}  props
 * @param {*}       props.children      Notice content.
 * @param {string}  props.className     Additional class name.
 * @param {boolean} props.isDismissible Whether to render a close button. Defaults to
 *                                      `false`, unlike core, because a Newspack notice
 *                                      reports an outcome rather than queuing for removal.
 * @param {*}       props.spokenMessage Announcement text, derived from `children` when omitted.
 */
const Notice = ( { children, className, isDismissible = false, spokenMessage, ...otherProps } ) => (
	<BaseComponent
		className={ classnames( 'newspack-notice', className ) }
		isDismissible={ isDismissible }
		spokenMessage={ spokenMessage ?? toText( children ) }
		{ ...otherProps }
	>
		{ children }
	</BaseComponent>
);

export default Notice;
