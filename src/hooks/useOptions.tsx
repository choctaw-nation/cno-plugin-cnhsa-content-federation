import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function useOptions( restUrl: string ) {
	const [ options, setOptions ] = useState< Options | null >( null );
	const [ saving, setSaving ] = useState( false );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ errors, setErrors ] = useState< string | null >( null );

	useEffect( () => {
		apiFetch( { path: restUrl } )
			.then( ( data ) => {
				setOptions( data );
			} )
			.catch( ( err ) => {
				setErrors( String( err ) );
			} );
	}, [ restUrl ] );
	return {
		options,
		setOptions,
		saving,
		setSaving,
		message,
		setMessage,
		errors,
		setErrors,
	};
}
