import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit() {
    const blockProps = useBlockProps();

    return (
        <div { ...blockProps }>
            <Placeholder icon="yes-alt" label={ __( 'Open Vote', 'openvote' ) }>
                <p className="openvote-block-preview__note">
                    { __( 'Na stronie zalogowani użytkownicy zobaczą tylko te aktywne głosowania, do których należą (według grupy). Goście zobaczą zachętę do logowania.', 'openvote' ) }
                </p>
            </Placeholder>
        </div>
    );
}
