<?php
/**
 * Partial: formularz uzupełnienia brakujących pól profilu.
 *
 * Wymagane zmienne w kontekście include:
 *   array  $missing_fields  [ 'logical_key' => 'Label', ... ]
 *   string $context         'survey' | 'poll'
 *   string $nonce           wp_rest nonce
 */
defined( 'ABSPATH' ) || exit;

$context_label = ( 'survey' === $context )
    ? __( 'ankiety', 'openvote' )
    : __( 'głosowania', 'openvote' );
?>
<div class="openvote-profile-complete"
     data-context="<?php echo esc_attr( $context ); ?>"
     data-nonce="<?php echo esc_attr( $nonce ); ?>"
     data-rest-url="<?php echo esc_url( rest_url( 'openvote/v1/profile/complete' ) ); ?>">

    <div class="openvote-profile-complete__notice">
        <p>
            <?php
            printf(
                /* translators: 1: ankiety / głosowania, 2: lista brakujących pól */
                esc_html__( 'Aby wziąć udział w %1$s, uzupełnij swój profil. Brakujące pola: %2$s', 'openvote' ),
                esc_html( $context_label ),
                '<strong>' . esc_html( implode( ', ', array_values( $missing_fields ) ) ) . '</strong>'
            );
            ?>
        </p>
    </div>

    <p class="openvote-profile-complete__desc">
        <?php esc_html_e( 'Proszę o wprowadzenie brakujących danych. Dane zostaną dodane do Twojego profilu użytkownika na tej stronie i będą dostępne do późniejszego wykorzystywania w następnych ankietach lub głosowaniach.', 'openvote' ); ?>
    </p>

    <div class="openvote-profile-complete__fields">
        <?php foreach ( $missing_fields as $logical => $label ) :
            $input_type  = 'text';
            if ( 'email' === $logical )   $input_type = 'email';
            if ( 'phone' === $logical )   $input_type = 'tel';
            ?>
            <div class="openvote-profile-field">
                <label class="openvote-profile-field__label"
                       for="openvote-pf-<?php echo esc_attr( $logical ); ?>">
                    <?php echo esc_html( $label ); ?> <span class="openvote-profile-field__required">*</span>
                </label>
                <input
                    type="<?php echo esc_attr( $input_type ); ?>"
                    id="openvote-pf-<?php echo esc_attr( $logical ); ?>"
                    class="openvote-profile-field__input"
                    data-field="<?php echo esc_attr( $logical ); ?>"
                    placeholder="<?php echo esc_attr( $label ); ?>"
                    autocomplete="<?php echo esc_attr( 'email' === $logical ? 'email' : ( 'phone' === $logical ? 'tel' : 'on' ) ); ?>"
                    required>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="openvote-profile-complete__footer">
        <button type="button"
                class="openvote-profile-complete__btn"
                disabled>
            <?php esc_html_e( 'Dodaj', 'openvote' ); ?>
        </button>
        <div class="openvote-profile-complete__message" aria-live="polite"></div>
    </div>

</div><!-- .openvote-profile-complete -->
