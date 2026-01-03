<?php
/**
 * Front-End Login Page
 *
 * @package ChromaQAReports
 */

if ( is_user_logged_in() ) {
    wp_redirect( home_url( '/qa-reports/' ) );
    exit;
}
?>

<div class="cqa-login-container">
    <div class="cqa-login-card">
        <div class="cqa-login-header">
            <div class="cqa-login-logo">📋</div>
            <h1>QA Reports</h1>
            <p>Sign in to continue</p>
        </div>

        <form id="cqa-login-form" class="cqa-login-form">
            <?php wp_nonce_field( 'cqa_frontend_login', 'nonce' ); ?>
            
            <div class="cqa-form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>

            <div class="cqa-form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <div class="cqa-form-group cqa-checkbox-group">
                <label>
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>
            </div>

            <div id="cqa-login-error" class="cqa-error" style="display:none;"></div>

            <button type="submit" class="cqa-btn cqa-btn-primary cqa-btn-block">
                <span class="cqa-btn-text">Sign In</span>
                <span class="cqa-btn-loading" style="display:none;">Signing in...</span>
            </button>
        </form>

        <div class="cqa-login-footer">
            <p>Need help? Contact your administrator.</p>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    $('#cqa-login-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $error = $('#cqa-login-error');
        
        $btn.prop('disabled', true);
        $btn.find('.cqa-btn-text').hide();
        $btn.find('.cqa-btn-loading').show();
        $error.hide();
        
        $.ajax({
            url: cqaFrontend.ajaxUrl,
            method: 'POST',
            data: {
                action: 'cqa_frontend_login',
                username: $('#username').val(),
                password: $('#password').val(),
                remember: $('input[name="remember"]').is(':checked') ? 1 : 0,
                nonce: $('input[name="nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    $error.text(response.data.message).show();
                    $btn.prop('disabled', false);
                    $btn.find('.cqa-btn-text').show();
                    $btn.find('.cqa-btn-loading').hide();
                }
            },
            error: function() {
                $error.text(cqaFrontend.strings.error).show();
                $btn.prop('disabled', false);
                $btn.find('.cqa-btn-text').show();
                $btn.find('.cqa-btn-loading').hide();
            }
        });
    });
});
</script>
