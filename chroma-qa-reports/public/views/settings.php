<?php
/**
 * Front-End Settings Panel
 *
 * @package ChromaQAReports
 */

if ( ! current_user_can( 'cqa_create_reports' ) ) {
    wp_die( __( 'You do not have permission to access settings.', 'chroma-qa-reports' ) );
}

// Pre-fill values
$google_client_id = get_option( 'cqa_google_client_id' );
$google_client_secret = get_option( 'cqa_google_client_secret' );
$google_developer_key = get_option( 'cqa_google_developer_key' );
$gemini_api_key = get_option( 'cqa_gemini_api_key' );
$enable_ai = get_option( 'cqa_enable_ai', 'yes' );
?>

<div class="cqa-dashboard">
    <div class="cqa-dashboard-header">
        <div>
            <h1>⚙️ Settings</h1>
            <p class="cqa-subtitle">Configure application settings and API keys.</p>
        </div>
        <a href="<?php echo home_url( '/qa-reports/' ); ?>" class="cqa-btn cqa-btn-secondary">
            Back to Dashboard
        </a>
    </div>

    <div class="cqa-settings-container">
        <form id="cqa-settings-form">
            
            <!-- Google Integration -->
            <div class="cqa-section">
                <h2>Googe Drive & OAuth</h2>
                <p class="cqa-section-desc">Required for logging in and saving reports/photos to Google Drive.</p>
                
                <div class="cqa-form-group">
                    <label for="google_client_id">OAuth Client ID</label>
                    <input type="text" id="google_client_id" name="google_client_id" value="<?php echo esc_attr( $google_client_id ); ?>" class="cqa-input">
                </div>

                <div class="cqa-form-group">
                    <label for="google_client_secret">OAuth Client Secret</label>
                    <input type="password" id="google_client_secret" name="google_client_secret" value="<?php echo esc_attr( $google_client_secret ); ?>" class="cqa-input">
                </div>

                <div class="cqa-form-group">
                    <label for="google_developer_key">Developer API Key (Picker API)</label>
                    <input type="text" id="google_developer_key" name="google_developer_key" value="<?php echo esc_attr( $google_developer_key ); ?>" class="cqa-input">
                </div>
            </div>

            <!-- AI Configuration -->
            <div class="cqa-section">
                <h2>🤖 AI Features</h2>
                <p class="cqa-section-desc">Configure Gemini AI for executive summaries and document parsing.</p>
                
                <div class="cqa-form-group">
                    <label for="enable_ai">Enable AI Features</label>
                    <select id="enable_ai" name="enable_ai" class="cqa-input">
                        <option value="yes" <?php selected( $enable_ai, 'yes' ); ?>>Enabled</option>
                        <option value="no" <?php selected( $enable_ai, 'no' ); ?>>Disabled</option>
                    </select>
                </div>

                <div class="cqa-form-group">
                    <label for="gemini_api_key">Gemini API Key</label>
                    <input type="password" id="gemini_api_key" name="gemini_api_key" value="<?php echo esc_attr( $gemini_api_key ); ?>" class="cqa-input">
                </div>
            </div>

            <div class="cqa-form-actions">
                <button type="submit" class="cqa-btn cqa-btn-primary" id="save-settings-btn">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.cqa-settings-container {
    max-width: 800px;
    margin: 0 auto;
}

.cqa-section {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 24px;
}

.cqa-section h2 {
    margin-top: 0;
    font-size: 18px;
    color: #111827;
}

.cqa-section-desc {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 20px;
}

.cqa-form-group {
    margin-bottom: 16px;
}

.cqa-form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 14px;
    color: #374151;
}

.cqa-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.cqa-input:focus {
    border-color: #4f46e5;
    outline: none;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.cqa-form-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 40px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#cqa-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const $btn = $('#save-settings-btn');
        const originalText = $btn.text();
        const formData = {
            google_client_id: $('#google_client_id').val(),
            google_client_secret: $('#google_client_secret').val(),
            google_developer_key: $('#google_developer_key').val(),
            enable_ai: $('#enable_ai').val(),
            gemini_api_key: $('#gemini_api_key').val()
        };

        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: cqaFrontend.restUrl + 'settings',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
            },
            data: formData
        }).done(function(response) {
            alert('Settings saved successfully!');
        }).fail(function(xhr) {
            alert('Failed to save settings.');
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });
});
</script>
