import { TextControl, Flex, FlexBlock } from '@wordpress/components';
import type { Options } from '../types';

interface TargetCredentialsProps {
	label: string;
	env: string;
	options: Options;
	setOptions: ( options: Options ) => void;
}
const envUrls = {
	production: 'https://www.cnhsa.com',
	staging: 'https://healthclinstg.wpenginepowered.com',
	development: 'https://healthclindev.wpenginepowered.com',
	local: 'https://cnhsa.local',
};
export default function TargetCredentials( {
	env,
	options,
	setOptions,
}: TargetCredentialsProps ) {
	const creds = options.credentials?.[ env as any ] || {
		username: '',
		app_password: '',
	};

	const onChange =
		( field: 'username' | 'app_password' ) => ( val: string ) => {
			const next = { ...options };
			if ( ! next.credentials ) {
				next.credentials = {};
			}
			next.credentials[ env as any ] = {
				...( next.credentials[ env as any ] || {} ),
				[ field ]: val,
			};
			setOptions( next );
		};

	return (
		<Flex direction="column" gap={ 4 }>
			<FlexBlock>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label="Username"
					value={ creds.username || '' }
					onChange={ onChange( 'username' ) }
				/>
			</FlexBlock>
			<FlexBlock>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label="Application Password"
					value={ creds.app_password || '' }
					onChange={ onChange( 'app_password' ) }
				/>
				<a
					href={ `${
						envUrls[ env as keyof typeof envUrls ]
					}/wp-admin/profile.php#application-passwords` }
					target="_blank"
					rel="noreferrer"
				>
					Create an application password
				</a>
			</FlexBlock>
		</Flex>
	);
}
