import { TextControl, Flex, FlexBlock, PanelBody } from '@wordpress/components';
import type { Options } from '../types';

interface TargetCredentialsProps {
	label: string;
	options: Options;
	setOptions: ( options: Options ) => void;
}

export default function TargetCredentials( {
	label,
	options,
	setOptions,
}: TargetCredentialsProps ) {
	return (
		<PanelBody title={ label } initialOpen={ true }>
			<Flex direction="column" gap={ 4 }>
				<FlexBlock>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label="Username"
						value={ options.username || '' }
						onChange={ ( val ) =>
							setOptions( { ...options, username: val } )
						}
					/>
				</FlexBlock>
				<FlexBlock>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label="Application Password"
						value={ options.app_password || '' }
						onChange={ ( val ) =>
							setOptions( { ...options, app_password: val } )
						}
					/>
				</FlexBlock>
			</Flex>
		</PanelBody>
	);
}
