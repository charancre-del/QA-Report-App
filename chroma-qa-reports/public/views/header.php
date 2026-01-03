<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - QA Reports</title>
    <link rel="manifest" href="<?php echo CQA_PLUGIN_URL; ?>manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <?php wp_head(); ?>
</head>
<body class="cqa-frontend">
    <header class="cqa-header">
        <div class="cqa-header-inner">
            <a href="<?php echo home_url( '/qa-reports/' ); ?>" class="cqa-logo">
                <span class="cqa-logo-icon">📋</span>
                <span class="cqa-logo-text">QA Reports</span>
            </a>
            <?php if ( is_user_logged_in() ) : ?>
                <nav class="cqa-nav">
                    <a href="<?php echo home_url( '/qa-reports/' ); ?>" class="cqa-nav-link">
                        <span class="cqa-nav-icon">🏠</span> Dashboard
                    </a>
                    <a href="<?php echo home_url( '/qa-reports/new/' ); ?>" class="cqa-nav-link cqa-nav-primary">
                        <span class="cqa-nav-icon">➕</span> New Report
                    </a>
                </nav>
                <div class="cqa-user-menu">
                    <img src="<?php echo esc_url( get_avatar_url( get_current_user_id() ) ); ?>" alt="" class="cqa-avatar">
                    <span class="cqa-user-name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
                    <a href="<?php echo wp_logout_url( home_url( '/qa-reports/login/' ) ); ?>" class="cqa-logout">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <main class="cqa-main">
