<?php
/**
 * Front-End 404 Page
 *
 * @package ChromaQAReports
 */
?>

<div class="cqa-error-page">
    <div class="cqa-error-content">
        <span class="cqa-error-icon">🔍</span>
        <h1>Page Not Found</h1>
        <p>The page you're looking for doesn't exist.</p>
        <a href="<?php echo home_url( '/qa-reports/' ); ?>" class="cqa-btn cqa-btn-primary">
            Go to Dashboard
        </a>
    </div>
</div>
