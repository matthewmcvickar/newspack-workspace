/**
 * External dependencies
 */
import { parse } from 'qs';

/**
 * Internal dependencies
 */
import Notice from '../notice';

const GlobalNotices = () => {
	const notice = parse( window.location.search )[ 'newspack-notice' ];
	if ( ! notice ) {
		return null;
	}
	return notice.split( ',' ).map( ( text, i ) => {
		const isError = text.indexOf( '_error_' ) === 0;
		return (
			<Notice status={ isError ? 'error' : 'success' } key={ i }>
				{ isError ? text.replace( '_error_', '' ) : text }
			</Notice>
		);
	} );
};

export default GlobalNotices;
