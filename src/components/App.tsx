import apiFetch from '@wordpress/api-fetch';
import {
	Notice,
	ToggleControl,
	Panel,
	Button,
	Flex,
} from '@wordpress/components';
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
	console.log( options );
	if ( errors ) {
		return <Notice status="error">{ errors }</Notice>;
	}

	if ( ! options ) {
		return <p>Loading…</p>;
	}

	const toggleEnv = ( key: string ) => {
		const list = new Set( options.environments || [] );
		if ( list.has( key ) ) list.delete( key );
		else list.add( key );
		setOptions( { ...options, environments: Array.from( list ) } );
	};

	const validate = (): boolean => {
		// basic validation: no fields required but app_password recommended
		return true;
	};

	const onSubmit = async ( e: any ) => {
		e.preventDefault();
		if ( ! validate() ) {
			setMessage( null );
			setErrors( 'Validation failed' );
			return;
		}
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
			{ message && <Notice status="success">{ message }</Notice> }
			{ errors && <Notice status="error">{ errors }</Notice> }
			<Flex
				direction="column"
				gap={ 4 }
				style={ { marginBottom: '1rem' } }
			>
				<Panel header="Federation Targets">
					<Flex
						gap={ 2 }
						direction="column"
						style={ { padding: '1rem' } }
					>
						<p>
							Select which CNHSA environments you want to push
							content to.
						</p>
						{ [
							'production',
							'staging',
							'development',
							'local',
						].map( ( env ) => (
							<ToggleControl
								__nextHasNoMarginBottom
								key={ env }
								label={
									env.charAt( 0 ).toUpperCase() +
									env.slice( 1 )
								}
								checked={ (
									options.environments || []
								).includes( env ) }
								onChange={ () => toggleEnv( env ) }
							/>
						) ) }
						{ ( options.environments || [] ).includes(
							'local'
						) && (
							<div style={ { marginTop: '1rem' } }>
								<label>
									Local URL
									<input
										type="text"
										value={ options.localUrl || '' }
										onChange={ ( e ) =>
											setOptions( {
												...options,
												localUrl: e.target.value,
											} )
										}
										className="regular-text"
										style={ {
											display: 'block',
											marginTop: '0.5rem',
										} }
									/>
								</label>
							</div>
						) }
					</Flex>
				</Panel>
				{ options.environments && options.environments.length > 0 && (
					<Panel header="Federation Credentials">
						{ options.environments &&
							options.environments.length > 0 &&
							options.environments.map( ( env ) => (
								<TargetCredentials
									key={ env }
									label={
										env.charAt( 0 ).toUpperCase() +
										env.slice( 1 )
									}
									options={ options }
									setOptions={ setOptions }
								/>
							) ) }
					</Panel>
				) }
			</Flex>
			<Button loading={ saving } type="submit" variant="primary">
				{ saving ? 'Saving…' : 'Save Settings' }
			</Button>
		</form>
	);
}
