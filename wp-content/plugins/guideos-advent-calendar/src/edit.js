import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	return (
		<div { ...useBlockProps() }>
			{ __( 'GuideOS Adventskalender Block – Editor UI folgt', 'guideos-advent' ) }
		</div>
	);
}
