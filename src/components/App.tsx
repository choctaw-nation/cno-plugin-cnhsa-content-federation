import apiFetch from '@wordpress/api-fetch';
import {
	Notice,
	SelectControl,
	Panel,
	Button,
	Flex,
	TextControl,
	PanelBody,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import useOptions from '../hooks/useOptions';
import TargetCredentials from './TargetCredentials';

type Props = {
	restUrl: string;
	nonce: string;
};

export default function App( { restUrl, nonce }: Props ) {
	const {
		options,
		setOptions,
		saving,
		setSaving,
		message,
		setMessage,
		errors,
		setErrors,
	} = useOptions( restUrl );
	const [ credentialsPanelTitle, setCredentialsPanelTitle ] = useState(
		options?.environments && options.environments.length > 0
			? `${
					options.environments[ 0 ].charAt( 0 ).toUpperCase() +
					options.environments[ 0 ].slice( 1 )
			  } Federation Credentials`
			: 'Federation Credentials'
	);

	if ( errors ) {
		return <Notice status="error">{ errors }</Notice>;
	}

	if ( ! options ) {
		return <p>Loading…</p>;
	}
	const selectOptions = [
		{
			label: 'Choose a target environment',
			value: '',
		},
		{
			label: 'Production',
			value: 'production',
		},
		{ label: 'Staging', value: 'staging' },
		{
			label: 'Development',
			value: 'development',
		},
		{ label: 'Local', value: 'local' },
	];

	const onSubmit = async ( e: any ) => {
		e.preventDefault();
		setSaving( true );
		setMessage( null );
		setErrors( null );
		try {
			const res = await apiFetch( {
				path: restUrl,
				method: 'POST',
				data: options,
				headers: {
					'X-WP-Nonce': nonce,
				},
			} );
			setOptions( res );
			setMessage( 'Settings saved.' );
		} catch ( err ) {
			setErrors( String( err ) );
		} finally {
			setSaving( false );
		}
	};

	return (
		<form onSubmit={ onSubmit }>
			<Flex
				direction="column"
				gap={ 4 }
				style={ { marginBottom: '1rem' } }
			>
				{ message && <Notice status="success">{ message }</Notice> }
				{ errors && <Notice status="error">{ errors }</Notice> }
				<Panel header="Federation Settings">
					<PanelBody title="Target Environments" initialOpen={ true }>
						<p
							style={ {
								marginBottom:
									cnhsaFederationSettings.environment !==
									'production'
										? '.5rem'
										: undefined,
							} }
						>
							Select which CNHSA environments you want to push
							content to.{ ' ' }
						</p>
						{ cnhsaFederationSettings.environment !==
							'production' && (
							<p style={ { marginTop: 0 } }>
								<i>
									The production target environment is only
									available on production environments.
								</i>
							</p>
						) }
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label="Target Environment"
							value={ ( options.environments || [] )[ 0 ] || '' }
							options={ selectOptions.filter( ( option ) =>
								cnhsaFederationSettings.environment ===
								'production'
									? true
									: option.value !== 'production'
							) }
							onChange={ ( val ) => {
								setOptions( {
									...options,
									environments: val ? [ val ] : [],
								} );
								setCredentialsPanelTitle(
									val
										? `${
												val.charAt( 0 ).toUpperCase() +
												val.slice( 1 )
										  } Federation Credentials`
										: 'Federation Credentials'
								);
							} }
						/>
						{ ( options.environments || [] ).includes(
							'local'
						) && (
							<div style={ { marginTop: '1rem' } }>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									type="text"
									label="Local URL"
									help="This value expires after 30 days."
									placeholder="https://cnhsa.local"
									value={ options.localUrl || '' }
									onChange={ ( val ) =>
										setOptions( {
											...options,
											localUrl: val,
										} )
									}
								/>
							</div>
						) }
					</PanelBody>
					{ options.environments &&
						options.environments.length > 0 && (
							<PanelBody
								title={ credentialsPanelTitle }
								initialOpen={ true }
							>
								{ options.environments &&
									options.environments.length > 0 &&
									options.environments.map( ( env ) => (
										<TargetCredentials
											key={ env }
											env={ env }
											label={
												env.charAt( 0 ).toUpperCase() +
												env.slice( 1 )
											}
											options={ options }
											setOptions={ setOptions }
										/>
									) ) }
							</PanelBody>
						) }
				</Panel>
			</Flex>
			<Button
				loading={ saving ? 'true' : 'false' }
				type="submit"
				variant="primary"
			>
				{ saving ? 'Saving…' : 'Save Settings' }
			</Button>
		</form>
	);
}
