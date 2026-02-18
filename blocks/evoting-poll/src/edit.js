import { useEffect, useState } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function Edit( { attributes, setAttributes } ) {
    const { pollId } = attributes;
    const [ polls, setPolls ] = useState( [] );
    const [ loading, setLoading ] = useState( true );
    const [ selectedPoll, setSelectedPoll ] = useState( null );

    const blockProps = useBlockProps();

    useEffect( () => {
        apiFetch( { path: '/evoting/v1/polls' } )
            .then( ( data ) => {
                setPolls( data );
                setLoading( false );
            } )
            .catch( () => setLoading( false ) );
    }, [] );

    useEffect( () => {
        if ( pollId ) {
            apiFetch( { path: `/evoting/v1/polls/${ pollId }` } )
                .then( ( data ) => setSelectedPoll( data ) )
                .catch( () => setSelectedPoll( null ) );
        } else {
            setSelectedPoll( null );
        }
    }, [ pollId ] );

    const options = [
        { label: __( '— Wybierz głosowanie —', 'evoting' ), value: 0 },
        ...polls.map( ( p ) => ( { label: p.title, value: p.id } ) ),
    ];

    if ( loading ) {
        return (
            <div { ...blockProps }>
                <Placeholder icon="yes-alt" label={ __( 'E-Voting Poll', 'evoting' ) }>
                    <Spinner />
                </Placeholder>
            </div>
        );
    }

    return (
        <div { ...blockProps }>
            <InspectorControls>
                <PanelBody title={ __( 'Ustawienia głosowania', 'evoting' ) }>
                    <SelectControl
                        label={ __( 'Wybierz głosowanie', 'evoting' ) }
                        value={ pollId }
                        options={ options }
                        onChange={ ( val ) => setAttributes( { pollId: Number( val ) } ) }
                    />
                </PanelBody>
            </InspectorControls>

            { ! pollId ? (
                <Placeholder icon="yes-alt" label={ __( 'E-Voting Poll', 'evoting' ) }>
                    <SelectControl
                        label={ __( 'Wybierz głosowanie', 'evoting' ) }
                        value={ pollId }
                        options={ options }
                        onChange={ ( val ) => setAttributes( { pollId: Number( val ) } ) }
                    />
                </Placeholder>
            ) : (
                <div className="evoting-block-preview">
                    <h3>{ selectedPoll ? selectedPoll.title : __( 'Ładowanie…', 'evoting' ) }</h3>
                    { selectedPoll && selectedPoll.description && (
                        <p className="evoting-block-preview__desc">{ selectedPoll.description }</p>
                    ) }
                    { selectedPoll && selectedPoll.questions && (
                        <ul className="evoting-block-preview__questions">
                            { selectedPoll.questions.map( ( q, i ) => (
                                <li key={ q.id }>{ i + 1 }. { q.text }</li>
                            ) ) }
                        </ul>
                    ) }
                    <p className="evoting-block-preview__note">
                        { __( 'Formularz głosowania wyświetli się na froncie.', 'evoting' ) }
                    </p>
                </div>
            ) }
        </div>
    );
}
