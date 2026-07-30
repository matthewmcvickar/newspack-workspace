/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { TextControl } from '@wordpress/components';
import type { TokenItem } from '@wordpress/components/build-types/form-token-field/types.d.ts';

/**
 * Internal dependencies
 */
import { FormTokenField } from '../../../../../../packages/components/src';
import { WIZARD_STORE_NAMESPACE } from '../../../../../../packages/components/src/wizard/store';
import {
	formatAccessRuleOptionLabel,
	getAccessRuleOptionTokens,
	getMissingOptionLabel,
	isAccessRuleOptionInput,
	resolveAccessRuleOptionTokens,
	MAX_OPTION_SUGGESTIONS,
	type AccessRuleOption as RuleOption,
} from '../../../../../content-gate/access-rule-options';

interface DynamicRuleConfig< T > {
	path: string;
	mapItem: ( item: T ) => RuleOption;
}

function dynamicRule< T >( config: DynamicRuleConfig< T > ): DynamicRuleConfig< T > {
	return config;
}

/**
 * Rules whose options should be fetched dynamically via the REST API.
 *
 * `per_page=-1` is apiFetch's unbounded form — fetchAllMiddleware walks the `Link:
 * rel="next"` headers and resolves to every page merged. A fixed page size silently
 * truncated the list, leaving the institutions past it unselectable. The value is
 * apiFetch's, not the REST API's: the posts controller caps `per_page` at 100 and would
 * reject -1 outright, so this only works through apiFetch. `orderby`/`status` match
 * `Institution::get_options()`, which seeds these options before the fetch lands.
 */
const DYNAMIC_OPTION_RULES: Record< string, DynamicRuleConfig< any > > = {
	institution: dynamicRule< Institution >( {
		path: '/wp/v2/np_institution?per_page=-1&context=edit&status=publish&orderby=title&order=asc&_fields=id,title',
		mapItem: item => ( { value: item.id, label: item.title.raw } ),
	} ),
};

/**
 * Return options for a rule, fetching dynamically when configured.
 */
function useRuleOptions( slug: string ) {
	const rule = window.newspackAudienceContentGates.available_access_rules[ slug ];
	const [ options, setOptions ] = useState< RuleOption[] >( rule?.options ?? [] );
	const { addNotice } = useDispatch( WIZARD_STORE_NAMESPACE );

	useEffect( () => {
		const config = DYNAMIC_OPTION_RULES[ slug ];
		if ( ! config ) {
			return;
		}
		let cancelled = false;
		apiFetch< any[] >( { path: config.path } ) // eslint-disable-line @typescript-eslint/no-explicit-any
			.then( items => {
				if ( ! cancelled ) {
					setOptions( items.map( config.mapItem ) );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					addNotice( {
						message: __( 'Failed to load options. The list may be outdated.', 'newspack-plugin' ),
						type: 'error',
						id: `rule-options-error-${ slug }`,
					} );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ slug, addNotice ] );

	return options;
}

export default function AccessRuleControl( { slug, value, onChange }: GateRuleControlProps ) {
	const rule = window.newspackAudienceContentGates.available_access_rules[ slug ];
	const options = useRuleOptions( slug );

	if ( ! rule || rule.is_boolean ) {
		return null;
	}
	if ( options && options.length > 0 ) {
		return (
			<FormTokenField
				hideLabelFromVision
				label={ rule.name }
				description={ __( 'Search by name or ID.', 'newspack-plugin' ) }
				value={ getAccessRuleOptionTokens( options, value, getMissingOptionLabel( slug ) ) }
				onChange={ ( tokens: ( string | TokenItem )[] ) => onChange( resolveAccessRuleOptionTokens( tokens, options, value ) ) }
				suggestions={ options.map( formatAccessRuleOptionLabel ) }
				maxSuggestions={ MAX_OPTION_SUGGESTIONS }
				__experimentalValidateInput={ ( input: string ) => isAccessRuleOptionInput( input, options ) }
				__experimentalExpandOnFocus
				__next40pxDefaultSize
			/>
		);
	}
	return (
		<TextControl
			hideLabelFromVision
			label={ rule.name }
			help={ __( 'Separate with commas.', 'newspack-plugin' ) }
			value={ value as string }
			onChange={ onChange }
			__next40pxDefaultSize
		/>
	);
}
