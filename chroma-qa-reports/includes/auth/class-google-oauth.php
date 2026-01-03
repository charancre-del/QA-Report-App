<?php
/**
 * Google OAuth Handler
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Auth;

/**
 * Handles Google OAuth 2.0 authentication.
 */
class Google_OAuth {

    /**
     * OAuth endpoints.
     */
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /**
     * Get Google OAuth client ID.
     *
     * @return string
     */
    public static function get_client_id() {
        return get_option( 'cqa_google_client_id', '' );
    }

    /**
     * Get Google OAuth client secret.
     *
     * @return string
     */
    public static function get_client_secret() {
        return get_option( 'cqa_google_client_secret', '' );
    }

    /**
     * Get the OAuth callback URL.
     *
     * @return string
     */
    public static function get_redirect_uri() {
        return admin_url( 'admin-ajax.php?action=cqa_oauth_callback' );
    }

    /**
     * Check if OAuth is configured.
     *
     * @return bool
     */
    public static function is_configured() {
        return ! empty( self::get_client_id() ) && ! empty( self::get_client_secret() );
    }

    /**
     * Get the authorization URL.
     *
     * @param string $state State parameter for CSRF protection.
     * @return string
     */
    public static function get_auth_url( $state = '' ) {
        if ( empty( $state ) ) {
            $state = wp_create_nonce( 'cqa_oauth_state' );
        }

        $params = [
            'client_id'     => self::get_client_id(),
            'redirect_uri'  => self::get_redirect_uri(),
            'response_type' => 'code',
            'scope'         => implode( ' ', [
                'email',
                'profile',
                'https://www.googleapis.com/auth/drive.file',
            ] ),
            'state'         => $state,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ];

        return self::AUTH_URL . '?' . http_build_query( $params );
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param string $code Authorization code.
     * @return array|WP_Error
     */
    public static function exchange_code( $code ) {
        $response = wp_remote_post( self::TOKEN_URL, [
            'body' => [
                'client_id'     => self::get_client_id(),
                'client_secret' => self::get_client_secret(),
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => self::get_redirect_uri(),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'oauth_error', $body['error_description'] ?? $body['error'] );
        }

        return $body;
    }

    /**
     * Refresh an access token.
     *
     * @param string $refresh_token Refresh token.
     * @return array|WP_Error
     */
    public static function refresh_token( $refresh_token ) {
        $response = wp_remote_post( self::TOKEN_URL, [
            'body' => [
                'client_id'     => self::get_client_id(),
                'client_secret' => self::get_client_secret(),
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'oauth_error', $body['error_description'] ?? $body['error'] );
        }

        return $body;
    }

    /**
     * Get user info from Google.
     *
     * @param string $access_token Access token.
     * @return array|WP_Error
     */
    public static function get_user_info( $access_token ) {
        $response = wp_remote_get( self::USERINFO_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'oauth_error', $body['error']['message'] ?? 'Unknown error' );
        }

        return $body;
    }

    /**
     * Store tokens for a user.
     *
     * @param int   $user_id User ID.
     * @param array $tokens Token data.
     */
    public static function store_tokens( $user_id, $tokens ) {
        update_user_meta( $user_id, 'cqa_google_access_token', $tokens['access_token'] );
        update_user_meta( $user_id, 'cqa_google_token_expires', time() + $tokens['expires_in'] );
        
        if ( isset( $tokens['refresh_token'] ) ) {
            update_user_meta( $user_id, 'cqa_google_refresh_token', $tokens['refresh_token'] );
        }
    }

    /**
     * Get a valid access token for a user.
     *
     * @param int $user_id User ID.
     * @return string|WP_Error
     */
    public static function get_access_token( $user_id ) {
        $access_token = get_user_meta( $user_id, 'cqa_google_access_token', true );
        $expires = get_user_meta( $user_id, 'cqa_google_token_expires', true );

        // Check if token is expired
        if ( empty( $access_token ) || $expires < time() + 300 ) {
            $refresh_token = get_user_meta( $user_id, 'cqa_google_refresh_token', true );
            
            if ( empty( $refresh_token ) ) {
                return new \WP_Error( 'no_refresh_token', __( 'No refresh token available. Please re-authenticate.', 'chroma-qa-reports' ) );
            }

            $tokens = self::refresh_token( $refresh_token );
            
            if ( is_wp_error( $tokens ) ) {
                return $tokens;
            }

            self::store_tokens( $user_id, $tokens );
            $access_token = $tokens['access_token'];
        }

        return $access_token;
    }

    /**
     * Check if user has connected Google account.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function is_user_connected( $user_id ) {
        return ! empty( get_user_meta( $user_id, 'cqa_google_refresh_token', true ) );
    }

    /**
     * Disconnect user's Google account.
     *
     * @param int $user_id User ID.
     */
    public static function disconnect_user( $user_id ) {
        delete_user_meta( $user_id, 'cqa_google_access_token' );
        delete_user_meta( $user_id, 'cqa_google_refresh_token' );
        delete_user_meta( $user_id, 'cqa_google_token_expires' );
    }
}
