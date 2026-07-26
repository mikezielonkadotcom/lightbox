import {
	Button,
	ButtonGroup,
	ExternalLink,
	Notice,
	RadioControl,
	SelectControl,
	TabPanel,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
// Babel's classic JSX transform consumes createElement and Fragment after linting.
// eslint-disable-next-line no-unused-vars
import { createElement, Fragment, render, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './style.css';

const booleanValue = ( value ) => ( value ? '1' : '0' );

function ProductHeader( { config } ) {
	const welcomeUrl = config.welcome.settingsUrl + '&view=welcome';
	const settingsUrl = config.welcome.settingsUrl;

	return (
		<header className="llb-admin__header">
			<div>
				<p className="llb-admin__kicker">
					{ __( 'Little Lightbox', 'little-lightbox' ) }
				</p>
				<h1>{ config.plugin.name }</h1>
				<p className="llb-admin__version">
					{ __( 'Version', 'little-lightbox' ) }{ ' ' }
					{ config.plugin.version }
				</p>
			</div>
			<ButtonGroup
				className="llb-admin__view-switcher"
				aria-label={ __( 'Plugin views', 'little-lightbox' ) }
			>
				<Button
					href={ settingsUrl }
					variant={
						config.view === 'settings' ? 'primary' : 'secondary'
					}
				>
					{ __( 'Settings', 'little-lightbox' ) }
				</Button>
				<Button
					href={ welcomeUrl }
					variant={
						config.view === 'welcome' ? 'primary' : 'secondary'
					}
				>
					{ __( 'Welcome setup', 'little-lightbox' ) }
				</Button>
			</ButtonGroup>
		</header>
	);
}

function WizardProgress( { step } ) {
	return (
		<ol
			className="llb-wizard-progress"
			aria-label={ __( 'Setup progress', 'little-lightbox' ) }
		>
			<li
				className={ step >= 1 ? 'is-active' : '' }
				aria-current={ step === 1 ? 'step' : undefined }
			>
				<span>1</span>
				{ __( 'Privacy', 'little-lightbox' ) }
			</li>
			<li
				className={ step >= 2 ? 'is-active' : '' }
				aria-current={ step === 2 ? 'step' : undefined }
			>
				<span>2</span>
				{ __( 'Lightbox setup', 'little-lightbox' ) }
			</li>
		</ol>
	);
}

function WizardFormFields( { welcome } ) {
	return (
		<>
			<input
				type="hidden"
				name={ welcome.nonceName }
				value={ welcome.nonce }
			/>
		</>
	);
}

function WelcomeWizard( { config } ) {
	const { welcome } = config;
	const [ sharing, setSharing ] = useState( welcome.sharingEnabled );
	const [ mode, setMode ] = useState(
		welcome.options.lightbox_mode || 'enhanced'
	);
	const [ gallery, setGallery ] = useState(
		welcome.options.gallery_enabled !== false
	);
	const [ animations, setAnimations ] = useState(
		welcome.options.animations_enabled !== false
	);
	const finished = [ 'completed', 'skipped' ].includes(
		welcome.state.status
	);

	if ( finished ) {
		return (
			<main className="llb-admin__main">
				<section className="llb-admin__section llb-admin__completion">
					<p className="llb-admin__eyebrow">
						{ __( 'Welcome setup', 'little-lightbox' ) }
					</p>
					<h2>
						{ welcome.state.status === 'skipped'
							? __( 'Setup skipped', 'little-lightbox' )
							: __( 'Setup complete', 'little-lightbox' ) }
					</h2>
					<p>
						{ __(
							'Your telemetry choice and Little Lightbox settings remain available here at any time.',
							'little-lightbox'
						) }
					</p>
					<div className="llb-admin__actions">
						<form method="post" action={ welcome.actionUrl }>
							<WizardFormFields welcome={ welcome } />
							<Button
								type="submit"
								name="llb_onboarding_action"
								value="restart"
								variant="secondary"
							>
								{ __( 'Review setup', 'little-lightbox' ) }
							</Button>
						</form>
						<Button href={ welcome.settingsUrl } variant="primary">
							{ __( 'Open settings', 'little-lightbox' ) }
						</Button>
					</div>
				</section>
			</main>
		);
	}

	const step = welcome.state.step === 2 ? 2 : 1;

	return (
		<main className="llb-admin__main">
			<WizardProgress step={ step } />
			{ step === 1 ? (
				<section className="llb-admin__section">
					<p className="llb-admin__eyebrow">
						{ __( 'Step 1 of 2', 'little-lightbox' ) }
					</p>
					<h2>
						{ __(
							'Choose what this installation shares',
							'little-lightbox'
						) }
					</h2>
					<form method="post" action={ welcome.actionUrl }>
						<WizardFormFields welcome={ welcome } />
						<input
							type="hidden"
							name={ welcome.sharingFieldName }
							value={ booleanValue( sharing ) }
						/>
						<ToggleControl
							label={ __(
								'Share optional update and feature telemetry',
								'little-lightbox'
							) }
							checked={ sharing }
							onChange={ setSharing }
						/>
						<p className="llb-admin__details">
							{ welcome.telemetryDetails }{ ' ' }
							<ExternalLink href={ welcome.privacyUrl }>
								{ __( 'Privacy policy', 'little-lightbox' ) }
							</ExternalLink>
						</p>
						<div className="llb-admin__actions">
							<Button
								type="submit"
								name="llb_onboarding_action"
								value="save_telemetry"
								variant="primary"
							>
								{ __( 'Continue', 'little-lightbox' ) }
							</Button>
							<Button
								type="submit"
								name="llb_onboarding_action"
								value="skip"
								variant="secondary"
							>
								{ __( 'Skip setup', 'little-lightbox' ) }
							</Button>
						</div>
					</form>
				</section>
			) : (
				<section className="llb-admin__section">
					<p className="llb-admin__eyebrow">
						{ __( 'Step 2 of 2', 'little-lightbox' ) }
					</p>
					<h2>
						{ __(
							'Choose the starting behavior',
							'little-lightbox'
						) }
					</h2>
					<form method="post" action={ welcome.actionUrl }>
						<WizardFormFields welcome={ welcome } />
						{ welcome.network ? (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Little Lightbox is network active. Configure lightbox behavior separately from each site settings screen.',
									'little-lightbox'
								) }
							</Notice>
						) : (
							<div className="llb-admin__controls">
								<input
									type="hidden"
									name="lightbox_mode"
									value={ mode }
								/>
								<input
									type="hidden"
									name="gallery_enabled"
									value={ booleanValue( gallery ) }
								/>
								<input
									type="hidden"
									name="animations_enabled"
									value={ booleanValue( animations ) }
								/>
								<RadioControl
									label={ __( 'Mode', 'little-lightbox' ) }
									selected={ mode }
									options={ [
										{
											label: __(
												'Enhanced: gallery, captions, animation, keyboard and swipe controls',
												'little-lightbox'
											),
											value: 'enhanced',
										},
										{
											label: __(
												'CSS-only: open and close without JavaScript',
												'little-lightbox'
											),
											value: 'css',
										},
									] }
									onChange={ setMode }
								/>
								<ToggleControl
									label={ __(
										'Enable gallery browsing',
										'little-lightbox'
									) }
									checked={ gallery }
									disabled={ mode !== 'enhanced' }
									onChange={ setGallery }
								/>
								<ToggleControl
									label={ __(
										'Enable lightbox animations',
										'little-lightbox'
									) }
									checked={ animations }
									disabled={ mode !== 'enhanced' }
									onChange={ setAnimations }
								/>
							</div>
						) }
						<div className="llb-admin__actions">
							<Button
								type="submit"
								name="llb_onboarding_action"
								value="back"
								variant="secondary"
							>
								{ __( 'Back', 'little-lightbox' ) }
							</Button>
							<Button
								type="submit"
								name="llb_onboarding_action"
								value="save_setup"
								variant="primary"
							>
								{ __( 'Finish setup', 'little-lightbox' ) }
							</Button>
							<Button
								type="submit"
								name="llb_onboarding_action"
								value="skip"
								variant="secondary"
							>
								{ __( 'Skip setup', 'little-lightbox' ) }
							</Button>
						</div>
					</form>
				</section>
			) }
		</main>
	);
}

function TelemetryControl( { config, telemetry, setTelemetry } ) {
	if ( telemetry.network && ! config.network ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __(
					'Telemetry is shared once for this network-active plugin.',
					'little-lightbox'
				) }{ ' ' }
				<a href={ telemetry.networkUrl }>
					{ __( 'Manage in Network Admin', 'little-lightbox' ) }
				</a>
			</Notice>
		);
	}

	return (
		<div className="llb-admin__controls">
			<ToggleControl
				label={ __(
					'Share optional update and feature telemetry',
					'little-lightbox'
				) }
				checked={ telemetry.enabled }
				onChange={ setTelemetry }
			/>
			<p className="llb-admin__details">
				{ telemetry.details }{ ' ' }
				<ExternalLink href={ telemetry.privacyUrl }>
					{ __( 'Privacy policy', 'little-lightbox' ) }
				</ExternalLink>
			</p>
		</div>
	);
}

function SettingsHiddenFields( { config, values, telemetryEnabled } ) {
	const name = config.settings.optionName;
	const fields = {
		...values,
		allow_ads_above_lightbox: booleanValue(
			values.allow_ads_above_lightbox
		),
		animations_enabled: booleanValue( values.animations_enabled ),
		desktop_icon_always_visible: booleanValue(
			values.desktop_icon_always_visible
		),
		gallery_enabled: booleanValue( values.gallery_enabled ),
		recipe_card_lightbox: booleanValue( values.recipe_card_lightbox ),
		wprm_jump_enabled: booleanValue( values.wprm_jump_enabled ),
	};

	return (
		<>
			<input
				type="hidden"
				name="option_page"
				value={ config.settings.optionPage }
			/>
			<input type="hidden" name="action" value="update" />
			<input
				type="hidden"
				name={ config.settings.nonceName }
				value={ config.settings.nonce }
			/>
			{ Object.entries( fields ).map( ( [ key, value ] ) => (
				<input
					key={ key }
					type="hidden"
					name={ `${ name }[${ key }]` }
					value={ String( value ?? '' ) }
				/>
			) ) }
			{ ! config.telemetry.network && (
				<>
					<input
						type="hidden"
						name={ config.telemetry.nonceName }
						value={ config.telemetry.nonce }
					/>
					<input
						type="hidden"
						name={ config.telemetry.fieldName }
						value={ booleanValue( telemetryEnabled ) }
					/>
				</>
			) }
		</>
	);
}

function BehaviorSettings( { values, update } ) {
	const enhanced = values.lightbox_mode === 'enhanced';
	return (
		<div className="llb-admin__section-grid">
			<section className="llb-admin__section">
				<h2>{ __( 'Lightbox behavior', 'little-lightbox' ) }</h2>
				<RadioControl
					label={ __( 'Mode', 'little-lightbox' ) }
					selected={ values.lightbox_mode }
					options={ [
						{
							label: __( 'Enhanced', 'little-lightbox' ),
							value: 'enhanced',
						},
						{
							label: __( 'CSS-only', 'little-lightbox' ),
							value: 'css',
						},
					] }
					onChange={ ( value ) => update( 'lightbox_mode', value ) }
				/>
				<ToggleControl
					label={ __( 'Gallery browsing', 'little-lightbox' ) }
					checked={ values.gallery_enabled }
					disabled={ ! enhanced }
					onChange={ ( value ) => update( 'gallery_enabled', value ) }
				/>
				<ToggleControl
					label={ __(
						'Open and close animations',
						'little-lightbox'
					) }
					checked={ values.animations_enabled }
					disabled={ ! enhanced }
					onChange={ ( value ) =>
						update( 'animations_enabled', value )
					}
				/>
				<TextControl
					label={ __( 'Animation duration (ms)', 'little-lightbox' ) }
					type="number"
					min="50"
					max="1000"
					step="10"
					value={ values.animation_duration_ms }
					disabled={ ! enhanced || ! values.animations_enabled }
					onChange={ ( value ) =>
						update( 'animation_duration_ms', value )
					}
				/>
			</section>
			<section className="llb-admin__section">
				<h2>{ __( 'Captions and controls', 'little-lightbox' ) }</h2>
				<SelectControl
					label={ __( 'Caption source', 'little-lightbox' ) }
					value={ values.caption_source }
					disabled={ ! enhanced }
					options={ [
						{
							label: __( 'Alt text', 'little-lightbox' ),
							value: 'alt',
						},
						{
							label: __( 'Title attribute', 'little-lightbox' ),
							value: 'title',
						},
						{
							label: __(
								'Attachment description',
								'little-lightbox'
							),
							value: 'description',
						},
						{
							label: __( 'No caption', 'little-lightbox' ),
							value: 'none',
						},
					] }
					onChange={ ( value ) => update( 'caption_source', value ) }
				/>
				<SelectControl
					label={ __( 'Corner icon size', 'little-lightbox' ) }
					value={ values.trigger_icon_size }
					options={ [
						{
							label: __( 'Normal', 'little-lightbox' ),
							value: 'normal',
						},
						{
							label: __( 'Jumbo (2x)', 'little-lightbox' ),
							value: 'jumbo',
						},
						{
							label: __( 'Super size (3x)', 'little-lightbox' ),
							value: 'super',
						},
					] }
					onChange={ ( value ) =>
						update( 'trigger_icon_size', value )
					}
				/>
				<ToggleControl
					label={ __(
						'Always show the corner icon on desktop',
						'little-lightbox'
					) }
					checked={ values.desktop_icon_always_visible }
					onChange={ ( value ) =>
						update( 'desktop_icon_always_visible', value )
					}
				/>
			</section>
		</div>
	);
}

function VisibilitySettings( { values, update } ) {
	return (
		<div className="llb-admin__section-grid">
			<section className="llb-admin__section">
				<h2>{ __( 'Image eligibility', 'little-lightbox' ) }</h2>
				<TextControl
					label={ __(
						'Minimum image width (px)',
						'little-lightbox'
					) }
					type="number"
					min="0"
					step="1"
					value={ values.min_image_width }
					onChange={ ( value ) => update( 'min_image_width', value ) }
				/>
				<TextControl
					label={ __( 'Excluded CSS classes', 'little-lightbox' ) }
					value={ values.excluded_classes }
					placeholder="alignright, sponsor-logo"
					onChange={ ( value ) =>
						update( 'excluded_classes', value )
					}
				/>
				<ToggleControl
					label={ __(
						'Enable recipe-card images',
						'little-lightbox'
					) }
					checked={ values.recipe_card_lightbox }
					onChange={ ( value ) =>
						update( 'recipe_card_lightbox', value )
					}
				/>
			</section>
			<section className="llb-admin__section">
				<h2>{ __( 'Ad layering', 'little-lightbox' ) }</h2>
				<ToggleControl
					label={ __(
						'Allow selected ads above the lightbox',
						'little-lightbox'
					) }
					checked={ values.allow_ads_above_lightbox }
					disabled={ values.lightbox_mode !== 'enhanced' }
					onChange={ ( value ) =>
						update( 'allow_ads_above_lightbox', value )
					}
				/>
				<TextareaControl
					label={ __( 'Ad container selectors', 'little-lightbox' ) }
					rows="4"
					value={ values.ad_layer_selectors }
					disabled={
						values.lightbox_mode !== 'enhanced' ||
						! values.allow_ads_above_lightbox
					}
					onChange={ ( value ) =>
						update( 'ad_layer_selectors', value )
					}
				/>
			</section>
		</div>
	);
}

function IntegrationSettings( { config, values, update } ) {
	return (
		<section className="llb-admin__section">
			<h2>{ __( 'WP Recipe Maker', 'little-lightbox' ) }</h2>
			{ ! config.settings.wprmActive && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'WP Recipe Maker is not active on this site.',
						'little-lightbox'
					) }
				</Notice>
			) }
			<ToggleControl
				label={ __(
					'Show Jump to Recipe in the lightbox',
					'little-lightbox'
				) }
				checked={ values.wprm_jump_enabled }
				onChange={ ( value ) => update( 'wprm_jump_enabled', value ) }
			/>
		</section>
	);
}

function SettingsInterface( { config } ) {
	const [ values, setValues ] = useState( config.settings.options );
	const [ telemetryEnabled, setTelemetryEnabled ] = useState(
		config.telemetry.enabled
	);
	const update = ( key, value ) =>
		setValues( ( current ) => ( { ...current, [ key ]: value } ) );

	if ( config.network ) {
		return (
			<main className="llb-admin__main">
				<section className="llb-admin__section">
					<h2>{ __( 'Network privacy', 'little-lightbox' ) }</h2>
					<form method="post" action={ config.welcome.settingsUrl }>
						<input
							type="hidden"
							name={ config.welcome.nonceName }
							value={ config.welcome.nonce }
						/>
						<input
							type="hidden"
							name={ config.telemetry.fieldName }
							value={ booleanValue( telemetryEnabled ) }
						/>
						<TelemetryControl
							config={ config }
							telemetry={ {
								...config.telemetry,
								enabled: telemetryEnabled,
							} }
							setTelemetry={ setTelemetryEnabled }
						/>
						<div className="llb-admin__actions">
							<Button
								type="submit"
								name="llb_onboarding_action"
								value="save_preference"
								variant="primary"
							>
								{ __(
									'Save privacy preference',
									'little-lightbox'
								) }
							</Button>
						</div>
					</form>
				</section>
			</main>
		);
	}

	const tabs = [
		{
			name: 'behavior',
			title: __( 'Behavior', 'little-lightbox' ),
			className: 'llb-tab',
		},
		{
			name: 'visibility',
			title: __( 'Visibility', 'little-lightbox' ),
			className: 'llb-tab',
		},
		{
			name: 'integrations',
			title: __( 'Integrations', 'little-lightbox' ),
			className: 'llb-tab',
		},
		{
			name: 'privacy',
			title: __( 'Privacy', 'little-lightbox' ),
			className: 'llb-tab',
		},
	];

	return (
		<main className="llb-admin__main">
			<form method="post" action={ config.settings.actionUrl }>
				<SettingsHiddenFields
					config={ config }
					values={ values }
					telemetryEnabled={ telemetryEnabled }
				/>
				<TabPanel className="llb-admin__tabs" tabs={ tabs }>
					{ ( tab ) => {
						if ( tab.name === 'visibility' ) {
							return (
								<VisibilitySettings
									values={ values }
									update={ update }
								/>
							);
						}
						if ( tab.name === 'integrations' ) {
							return (
								<IntegrationSettings
									config={ config }
									values={ values }
									update={ update }
								/>
							);
						}
						if ( tab.name === 'privacy' ) {
							return (
								<section className="llb-admin__section">
									<h2>
										{ __(
											'Update Machine telemetry',
											'little-lightbox'
										) }
									</h2>
									<TelemetryControl
										config={ config }
										telemetry={ {
											...config.telemetry,
											enabled: telemetryEnabled,
										} }
										setTelemetry={ setTelemetryEnabled }
									/>
								</section>
							);
						}
						return (
							<BehaviorSettings
								values={ values }
								update={ update }
							/>
						);
					} }
				</TabPanel>
				<div className="llb-admin__save-bar">
					<Button type="submit" variant="primary">
						{ __( 'Save settings', 'little-lightbox' ) }
					</Button>
				</div>
			</form>
		</main>
	);
}

function AdminApp( { config } ) {
	return (
		<div className="llb-admin__shell">
			<ProductHeader config={ config } />
			{ config.view === 'welcome' ? (
				<WelcomeWizard config={ config } />
			) : (
				<SettingsInterface config={ config } />
			) }
		</div>
	);
}

const root = document.getElementById( 'little-lightbox-admin-root' );
if ( root && window.MZVLittleLightboxAdmin ) {
	render( <AdminApp config={ window.MZVLittleLightboxAdmin } />, root );
}

export { AdminApp, SettingsInterface, WelcomeWizard };
