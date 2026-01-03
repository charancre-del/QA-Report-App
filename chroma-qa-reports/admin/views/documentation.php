<?php
/**
 * Documentation View
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Admin;

$frontend_url = home_url( '/qa-reports/' );
$login_url = home_url( '/qa-reports/login/' );
$callback_url = home_url( '/qa-reports/auth/callback/' );
?>

<div class="wrap cqa-wrap">
    <div class="cqa-header">
        <h1 class="cqa-title">
            <span class="dashicons dashicons-book"></span>
            <?php esc_html_e( 'Help & Documentation', 'chroma-qa-reports' ); ?>
        </h1>
    </div>

    <div class="cqa-doc-grid">
        <!-- Navigation -->
        <div class="cqa-doc-nav">
            <ul class="cqa-nav-list">
                <li><a href="#quick-start" class="active">Quick Start</a></li>
                <li><a href="#configuration">Configuration</a></li>
                <li><a href="#sso-setup">Google SSO Setup</a></li>
                <li><a href="#creating-reports">Creating Reports</a></li>
                <li><a href="#photo-comparison">Photo Comparison</a></li>
                <li><a href="#frontend">Frontend Access</a></li>
            </ul>
        </div>

        <!-- Content -->
        <div class="cqa-doc-content">
            
            <!-- Quick Start -->
            <section id="quick-start" class="cqa-doc-section">
                <h2>🚀 Quick Start</h2>
                <p>Welcome to the Chroma QA Reports plugin! This tool helps you create, manage, and analyze quality assurance reports for your schools.</p>
                
                <div class="cqa-alert cqa-alert-info">
                    <strong>Frontend Dashboard:</strong> Use the clean, non-admin interface for day-to-day work.<br>
                    <a href="<?php echo esc_url( $frontend_url ); ?>" target="_blank" class="button button-primary"><?php esc_html_e( 'Open Dashboard', 'chroma-qa-reports' ); ?></a>
                </div>
            </section>

            <!-- Configuration -->
            <section id="configuration" class="cqa-doc-section">
                <h2>⚙️ Configuration</h2>
                <p>Before you begin, ensure the following API keys are set up in <strong>Settings</strong>:</p>
                <ul class="cqa-checklist">
                    <li>
                        <strong>Google Maps API Key:</strong> Required for GPS verification and the School Heat Map.
                    </li>
                    <li>
                        <strong>Gemini API Key:</strong> Powers AI features like photo analysis and persistent note suggestions.
                    </li>
                    <li>
                        <strong>Google Drive Root Folder ID:</strong> The central folder where all report photos and PDFs are stored.
                    </li>
                </ul>
            </section>

            <!-- SSO Setup -->
            <section id="sso-setup" class="cqa-doc-section">
                <h2>🔐 Google SSO Setup</h2>
                <p>Enable "Sign in with Google" for your team to streamline access.</p>
                
                <h3>1. Create Credentials</h3>
                <ol>
                    <li>Go to the <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>.</li>
                    <li>Create an <strong>OAuth Client ID</strong> (Web Application).</li>
                    <li>Add this URL to <strong>Authorized Redirect URIs</strong>:
                        <code class="cqa-code-block"><?php echo esc_html( $callback_url ); ?></code>
                    </li>
                </ol>

                <h3>2. Configure Plugin</h3>
                <ol>
                    <li>Go to <strong>QA Reports > Settings</strong>.</li>
                    <li>Paste your <strong>Client ID</strong> and <strong>Client Secret</strong>.</li>
                    <li>Set <strong>Allowed Domain</strong> (e.g., <code>chromaela.com</code>) to restrict access.</li>
                </ol>
            </section>

            <!-- Creating Reports -->
            <section id="creating-reports" class="cqa-doc-section">
                <h2>📝 Creating Reports</h2>
                <p>Reports can be created via the Admin Dashboard or the Frontend Interface.</p>
                
                <h3>Steps:</h3>
                <ol>
                    <li>Click <strong>Create Report</strong>.</li>
                    <li>Select a <strong>School</strong>.</li>
                    <li>(Optional) Choose a <strong>Template</strong> to pre-fill data.</li>
                    <li>Go through the <strong>Classroom Checklist</strong>.</li>
                    <li>Take photos! The AI will auto-tag them based on what you're checking.</li>
                    <li><strong>Submit</strong> the report for review.</li>
                </ol>
                
                <p><em>Tip: Use the mobile view on your phone/tablet for easy photo taking while walking around.</em></p>
            </section>

            <!-- Photo Comparison -->
            <section id="photo-comparison" class="cqa-doc-section">
                <h2>📸 Photo Comparison</h2>
                <p>Track progress over time with the Before/After view.</p>
                <ul>
                    <li>When viewing a report, look for the <strong>Compare</strong> tab.</li>
                    <li>Photos are matched by their <strong>Location Tag</strong> (e.g., "Main Entrance").</li>
                    <li>You'll see the current photo side-by-side with the previous report's photo of the same area.</li>
                </ul>
            </section>

            <!-- Frontend Access -->
            <section id="frontend" class="cqa-doc-section">
                <h2>🖥️ Frontend Access</h2>
                <p>Share these links with your team. They do NOT need WordPress Admin access!</p>
                
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>URL</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Dashboard</strong></td>
                            <td><code><a href="<?php echo esc_url( $frontend_url ); ?>" target="_blank"><?php echo esc_html( $frontend_url ); ?></a></code></td>
                            <td>Main hub for viewing and creating reports.</td>
                        </tr>
                        <tr>
                            <td><strong>Login</strong></td>
                            <td><code><a href="<?php echo esc_url( $login_url ); ?>" target="_blank"><?php echo esc_html( $login_url ); ?></a></code></td>
                            <td>Direct login page with SSO button.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

        </div>
    </div>
</div>

<style>
.cqa-doc-grid {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 30px;
    margin-top: 20px;
}

.cqa-nav-list {
    position: sticky;
    top: 50px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 10px 0;
}

.cqa-nav-list li {
    margin: 0;
}

.cqa-nav-list a {
    display: block;
    padding: 10px 20px;
    text-decoration: none;
    color: #444;
    border-left: 3px solid transparent;
    transition: all 0.2s;
}

.cqa-nav-list a:hover,
.cqa-nav-list a.active {
    background: #f8f9fa;
    color: #2271b1;
    border-left-color: #2271b1;
}

.cqa-doc-section {
    background: white;
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    scroll-margin-top: 100px;
}

.cqa-doc-section h2 {
    margin-top: 0;
    font-size: 24px;
    border-bottom: 2px solid #f0f0f1;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.cqa-code-block {
    display: block;
    background: #f0f0f1;
    padding: 15px;
    border-radius: 4px;
    margin: 10px 0;
    word-break: break-all;
}

.cqa-alert {
    padding: 15px;
    border-left: 4px solid #2271b1;
    background: #f0f6fc;
    margin: 20px 0;
}

.cqa-checklist li {
    margin-bottom: 10px;
    padding-left: 20px;
    position: relative;
}

.cqa-checklist li:before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #2271b1;
    font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Smooth scroll
    $('.cqa-nav-list a').on('click', function(e) {
        e.preventDefault();
        var target = $(this.hash);
        $('html, body').animate({
            scrollTop: target.offset().top - 60
        }, 500);
        
        // Update active state
        $('.cqa-nav-list a').removeClass('active');
        $(this).addClass('active');
    });
});
</script>
