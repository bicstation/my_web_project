<?php
// my-custom-theme/header.php

/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#header-php
 *
 * @package WordPress
 * @subpackage YourThemeName
 * @since YourThemeName 1.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title( '|', true, 'right' ); bloginfo( 'name' ); ?></title>
    <link rel="icon" href="<?php echo esc_url( get_template_directory_uri() ); ?>/img/logo.webp">
    <!-- Bootstrap CSS - CDNから読み込み -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome - CDNから読み込み -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- style_v2.css (テーマ固有のCSS) -->
    <link href="<?php echo esc_url( get_template_directory_uri() ); ?>/css/style_v2.css" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <header class="py-3 bg-primary text-white grid-header">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#myCustomSidebar" aria-controls="myCustomSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <button class="btn btn-outline-light me-3 d-none d-md-inline-flex" id="myCustomSidebarToggleBtn" type="button">
                <i class="fas fa-bars"></i>
            </button>
            <h3 class="my-0 me-auto"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="text-white text-decoration-none"><?php bloginfo( 'name' ); ?></a></h3>
            <nav class="navbar navbar-expand-md navbar-dark p-0">
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="<?php echo esc_url( home_url( '/' ) ); ?>"><i class="fas fa-home me-1"></i>ホーム</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-box me-1"></i>サービス</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cube me-1"></i>製品
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="#">製品A</a></li>
                                <li><a class="dropdown-item" href="#">製品B</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#">その他</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-question-circle me-1"></i>よくある質問</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#"><i class="fas fa-envelope me-1"></i>お問い合わせ</a>
                        </li>
                    </ul>
                    <form class="d-none d-md-inline-flex ms-3" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <div class="input-group">
                            <input class="form-control" type="search" placeholder="サイト内検索..." aria-label="Search" name="s">
                            <button class="btn btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                    <a href="#" class="btn btn-outline-light ms-3"><i class="fas fa-sign-in-alt me-1"></i>ログイン</a>
                </div>
            </nav>
        </div>
    </header>
    <aside class="my-custom-sidebar offcanvas offcanvas-start bg-light grid-sidebar" tabindex="-1" id="myCustomSidebar" aria-labelledby="myCustomSidebarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="myCustomSidebarLabel">サイドメニュー</h5>
            <button type="button" class="btn-close text-reset d-md-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="accordion" id="sidebarAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                            <i class="fas fa-folder me-2"></i>カテゴリ 1
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#sidebarAccordion">
                        <div class="accordion-body">
                            <ul class="list-unstyled ps-3">
                                <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-1</a></li>
                                <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-2</a></li>
                                <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-3</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingTwo">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                            <i class="fas fa-box-open me-2"></i>カテゴリ 2
                        </button>
                    </h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#sidebarAccordion">
                        <div class="accordion-body">
                            <ul class="list-unstyled ps-3">
                                <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-cube me-2"></i>サブメニュー 2-1</a></li>
                                <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-cube me-2"></i>サブメニュー 2-2</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingThree">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                            <i class="fas fa-chart-line me-2"></i>データ分析
                        </button>
                    </h2>
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#sidebarAccordion">
                        <div class="accordion-body">
                            <ul class="list-unstyled ps-3">
                                <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-chart-pie me-2"></i>レポート</a></li>
                                <li><a href="#" class="text-decoration-none text-dark py-1 d-block"><i class="fas fa-globe me-2"></i>地域別データ</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
    <div class="p-3 grid-main">
        <main id="main-content-area">