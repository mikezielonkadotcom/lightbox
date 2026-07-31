import {
	fireEvent,
	render as renderTest,
	screen,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
// Jest uses the same classic JSX transform as the production WordPress build.
// eslint-disable-next-line no-unused-vars
import { createElement, Fragment } from '@wordpress/element';

jest.mock( '@wordpress/i18n', () => ( { __: ( text ) => text } ) );

jest.mock( '@wordpress/components', () => {
	function Button( { href, children, ...props } ) {
		if ( href ) {
			return (
				<a href={ href } { ...props }>
					{ children }
				</a>
			);
		}
		return <button { ...props }>{ children }</button>;
	}
	const ToggleControl = ( { label, checked, onChange, disabled } ) => (
		<label htmlFor={ `toggle-${ label }` }>
			{ label }
			<input
				id={ `toggle-${ label }` }
				type="checkbox"
				aria-label={ label }
				checked={ checked }
				disabled={ disabled }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
		</label>
	);
	const RadioControl = ( {
		label,
		selected,
		options,
		onChange,
		disabled,
	} ) => (
		<fieldset>
			<legend>{ label }</legend>
			{ options.map( ( option ) => (
				<label
					key={ option.value }
					htmlFor={ `${ label }-${ option.value }` }
				>
					{ option.label }
					<input
						id={ `${ label }-${ option.value }` }
						type="radio"
						name={ label }
						value={ option.value }
						checked={ selected === option.value }
						disabled={ disabled }
						onChange={ () => onChange( option.value ) }
					/>
				</label>
			) ) }
		</fieldset>
	);
	const Field = ( { label, value, onChange, type = 'text', disabled } ) => (
		<label htmlFor={ `field-${ label }` }>
			{ label }
			<input
				id={ `field-${ label }` }
				aria-label={ label }
				type={ type }
				value={ value }
				disabled={ disabled }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</label>
	);
	return {
		Button,
		ButtonGroup: ( { children } ) => <div>{ children }</div>,
		ExternalLink: ( { href, children } ) => (
			<a href={ href }>{ children }</a>
		),
		Notice: ( { children } ) => <div role="status">{ children }</div>,
		RadioControl,
		SelectControl: ( { label, value, options, onChange, disabled } ) => (
			<label htmlFor={ `select-${ label }` }>
				{ label }
				<select
					id={ `select-${ label }` }
					aria-label={ label }
					value={ value }
					disabled={ disabled }
					onChange={ ( event ) => onChange( event.target.value ) }
				>
					{ options.map( ( option ) => (
						<option key={ option.value } value={ option.value }>
							{ option.label }
						</option>
					) ) }
				</select>
			</label>
		),
		TabPanel: ( { tabs, children } ) => (
			<div>{ children( tabs[ 0 ] ) }</div>
		),
		TextControl: Field,
		TextareaControl: ( { label, value, onChange, disabled } ) => (
			<label htmlFor={ `textarea-${ label }` }>
				{ label }
				<textarea
					id={ `textarea-${ label }` }
					aria-label={ label }
					value={ value }
					disabled={ disabled }
					onChange={ ( event ) => onChange( event.target.value ) }
				/>
			</label>
		),
		ToggleControl,
	};
} );

import { AdminApp, SettingsInterface, WelcomeWizard } from './index';

const baseOptions = {
	ad_layer_selectors: '.sticky-ad',
	allow_ads_above_lightbox: false,
	animation_duration_ms: 200,
	animations_enabled: true,
	caption_source: 'alt',
	desktop_icon_always_visible: true,
	excluded_classes: '',
	gallery_enabled: true,
	lightbox_mode: 'enhanced',
	min_image_width: 0,
	recipe_card_lightbox: true,
	trigger_icon_size: 'normal',
	wprm_conflict_dismissed: false,
	wprm_jump_enabled: true,
};

const makeConfig = ( overrides = {} ) => ( {
	network: false,
	plugin: { name: 'This Little Lightbox of Mine', version: '2.7.3' },
	settings: {
		actionUrl: '/wp-admin/options.php',
		nonce: 'settings-nonce',
		nonceName: '_wpnonce',
		optionName: 'mzv_lightbox_options',
		optionPage: 'mzv_lightbox_options',
		options: baseOptions,
		wprmActive: false,
	},
	telemetry: {
		details: 'Bounded settings only.',
		enabled: true,
		fieldName: 'um_telemetry_consent_little-lightbox',
		network: false,
		networkUrl: '',
		nonce: 'telemetry-nonce',
		nonceName: '_um_telemetry_nonce_little-lightbox',
		privacyUrl: 'https://updatemachine.com/privacy',
	},
	view: 'welcome',
	welcome: {
		actionUrl:
			'/wp-admin/options-general.php?page=little-lightbox&view=welcome',
		network: false,
		nonce: 'welcome-nonce',
		nonceName: '_mzv_lb_onboarding_nonce',
		options: baseOptions,
		privacyUrl: 'https://updatemachine.com/privacy',
		settingsUrl: '/wp-admin/options-general.php?page=little-lightbox',
		sharingEnabled: true,
		sharingFieldName: 'um_telemetry_consent_little-lightbox',
		state: { status: 'in_progress', step: 1, version: '2.7.3' },
		telemetryDetails: 'Bounded settings only.',
	},
	...overrides,
} );

describe( 'Little Lightbox admin interface', () => {
	test( 'puts the reversible privacy choice first', async () => {
		const user = userEvent.setup();
		const config = makeConfig();
		const { container } = renderTest( <WelcomeWizard config={ config } /> );
		const sharing = screen.getByLabelText(
			'Share optional update and feature telemetry'
		);

		expect( screen.getByText( 'Step 1 of 2' ) ).toBeTruthy();
		expect( sharing.checked ).toBe( true );
		expect(
			screen.getByText( 'Privacy policy' ).getAttribute( 'href' )
		).toBe( 'https://updatemachine.com/privacy' );
		expect( screen.queryByText( 'Mode' ) ).toBeNull();

		await user.click( sharing );
		expect(
			container.querySelector(
				'input[name="um_telemetry_consent_little-lightbox"]'
			).value
		).toBe( '0' );
		expect(
			container.querySelector( 'button[value="skip"]' )
		).toBeTruthy();
	} );

	test( 'keeps setup choices in the server-submitted form', () => {
		const config = makeConfig( {
			welcome: {
				...makeConfig().welcome,
				state: { status: 'in_progress', step: 2, version: '2.7.3' },
			},
		} );
		const { container } = renderTest( <WelcomeWizard config={ config } /> );
		const cssMode = screen.getByLabelText( /CSS-only:/ );

		fireEvent.click( cssMode );
		expect(
			container.querySelector( 'input[name="lightbox_mode"]' ).value
		).toBe( 'css' );
		expect(
			screen.getByLabelText( 'Enable gallery browsing' ).disabled
		).toBe( true );
		expect(
			screen.getByLabelText( 'Enable lightbox animations' ).disabled
		).toBe( true );
		expect(
			container.querySelector( 'button[value="back"]' )
		).toBeTruthy();
		expect(
			container.querySelector( 'button[value="save_setup"]' )
		).toBeTruthy();
	} );

	test( 'renders one settings interface with WordPress-style views', () => {
		const config = makeConfig( { view: 'settings' } );
		renderTest( <AdminApp config={ config } /> );

		expect(
			screen.getByRole( 'heading', {
				name: 'This Little Lightbox of Mine',
			} )
		).toBeTruthy();
		expect( screen.getByText( 'Settings' ) ).toBeTruthy();
		expect( screen.getByText( 'Welcome setup' ) ).toBeTruthy();
		expect(
			screen.getByRole( 'heading', { name: 'Lightbox behavior' } )
		).toBeTruthy();
	} );

	test( 'uses the network-scoped save action in Network Admin', () => {
		const config = makeConfig( {
			network: true,
			telemetry: { ...makeConfig().telemetry, network: true },
			welcome: { ...makeConfig().welcome, network: true },
		} );
		const { container } = renderTest(
			<SettingsInterface config={ config } />
		);

		expect(
			screen.getByRole( 'heading', { name: 'Network privacy' } )
		).toBeTruthy();
		expect(
			container.querySelector( 'button[value="save_preference"]' )
		).toBeTruthy();
	} );
} );
