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
		if ( text.indexOf( '_error_' ) === 0 ) {
			return (
				<Notice status="error" key={ i } __unstableHTML>
					{ text.replace( '_error_', '' ) }
				</Notice>
			);
		}
		return (
			<Notice status="success" key={ i }>
				{ text }
			</Notice>
		);
	} );
};

export default GlobalNotices;
