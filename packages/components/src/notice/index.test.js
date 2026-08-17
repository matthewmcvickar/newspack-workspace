/**
 * External dependencies.
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { speak } from '@wordpress/a11y';

/**
 * Internal dependencies.
 */
import Notice from './';

jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ) );

describe( 'Notice', () => {
	beforeEach( () => speak.mockClear() );

	it( 'carries the Newspack class alongside the core one', () => {
		const { container } = render( <Notice>Saved</Notice> );
		const notice = container.querySelector( '.newspack-notice' );
		expect( notice ).toBeInTheDocument();
		expect( notice ).toHaveClass( 'components-notice' );
	} );

	it( 'keeps a caller class', () => {
		const { container } = render( <Notice className="newspack-notice--flush">Saved</Notice> );
		expect( container.querySelector( '.newspack-notice' ) ).toHaveClass( 'newspack-notice--flush' );
	} );

	it( 'maps status to the core modifier', () => {
		const { container } = render( <Notice status="error">Failed</Notice> );
		expect( container.querySelector( '.newspack-notice' ) ).toHaveClass( 'is-error' );
	} );

	it( 'is not dismissible by default', () => {
		render( <Notice>Saved</Notice> );
		expect( screen.queryByRole( 'button', { name: 'Close' } ) ).not.toBeInTheDocument();
	} );

	it( 'renders a close button when asked', () => {
		const onRemove = jest.fn();
		render(
			<Notice isDismissible onRemove={ onRemove }>
				Saved
			</Notice>
		);
		fireEvent.click( screen.getByRole( 'button', { name: 'Close' } ) );
		expect( onRemove ).toHaveBeenCalled();
	} );

	it( 'announces string content', () => {
		render( <Notice status="error">Something failed</Notice> );
		expect( speak ).toHaveBeenCalledWith( 'Something failed', 'assertive' );
	} );

	it( 'announces text nested in markup', () => {
		render(
			<Notice status="success">
				<span>Nested text</span>
			</Notice>
		);
		expect( speak ).toHaveBeenCalledWith( 'Nested text', 'polite' );
	} );

	it( 'announces raw-HTML string content without the tags', () => {
		render(
			<Notice status="error" __unstableHTML>
				{ 'Failed: <a href="https://example.com">details</a>' }
			</Notice>
		);
		expect( speak ).toHaveBeenCalledWith( 'Failed: details', 'assertive' );
	} );

	it( 'does not announce contextual statuses by default', () => {
		render(
			<>
				<Notice status="warning">Heads up</Notice>
				<Notice status="info">For reference</Notice>
				<Notice>Default status</Notice>
			</>
		);
		expect( speak ).not.toHaveBeenCalled();
	} );

	it( 'silences an announced status when given an empty spokenMessage', () => {
		render(
			<Notice status="error" spokenMessage="">
				Something failed
			</Notice>
		);
		expect( speak ).not.toHaveBeenCalled();
	} );

	// Core serialises non-string children mid-render to build the announcement, which
	// corrupts the hook order of whatever it walks and throws on the next render.
	it( 'survives re-rendering with a component child that uses hooks', () => {
		const Stateful = () => {
			const [ count ] = useState( 1 );
			return <span>{ `Issue ${ count }` }</span>;
		};
		const Harness = () => {
			const [ tick, setTick ] = useState( 0 );
			return (
				<>
					<button onClick={ () => setTick( tick + 1 ) }>Re-render</button>
					<Notice status="error">
						<Stateful />
					</Notice>
				</>
			);
		};
		render( <Harness /> );
		expect( screen.getByText( 'Issue 1' ) ).toBeInTheDocument();
		expect( () => fireEvent.click( screen.getByRole( 'button', { name: 'Re-render' } ) ) ).not.toThrow();
		// A component child has no text the flattener can reach, so nothing is announced.
		expect( speak ).not.toHaveBeenCalled();
	} );

	it( 'prefers an explicit spokenMessage', () => {
		render( <Notice spokenMessage="Short form">A much longer written message</Notice> );
		expect( speak ).toHaveBeenCalledWith( 'Short form', 'polite' );
	} );
} );
