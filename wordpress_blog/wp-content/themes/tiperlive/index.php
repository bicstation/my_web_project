<?php
// my-custom-theme/index.php

/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 *
 * @link [https://developer.wordpress.org/themes/basics/template-files/#index-php](https://developer.wordpress.org/themes/basics/template-files/#index-php)
 *
 * @package WordPress
 * @subpackage YourThemeName
 * @since YourThemeName 1.0
 */

get_header(); // header.php を読み込む

?>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
            <li class="breadcrumb-item"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><i class="fas fa-home me-1"></i>ホーム</a></li>
            <?php // パンくずリストを動的に生成するロジックをここに追加
                  // 例: is_category(), is_single(), is_page() などの条件分岐を使用 ?>
            <li class="breadcrumb-item"><a href="#"><i class="fas fa-list me-1"></i>カテゴリ</a></li>
            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-file me-1"></i>現在のページ</li>
        </ol>
    </nav>

    <div class="container-fluid bg-white p-4 rounded shadow-sm mt-3">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                ?>
                <h1 class="mb-4"><i class="fas fa-clipboard-list me-2"></i><?php the_title(); ?></h1>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
                <?php
            endwhile;
        else :
            // 投稿がない場合の処理
            echo '<h1 class="mb-4"><i class="fas fa-clipboard-list me-2"></i>コンテンツが見つかりません</h1>';
            echo '<p>お探しのコンテンツは見つかりませんでした。</p>';
        endif;
        ?>

        <!-- 既存のテンプレートの静的コンテンツ部分 (WordPressのループ外) -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>コンテンツブロック 1</h5>
                        <p class="card-text">簡潔な説明文。</p>
                        <a href="#" class="btn btn-primary"><i class="fas fa-arrow-right me-1"></i>詳細を見る</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-lightbulb me-2"></i>コンテンツブロック 2</h5>
                        <p class="card-text">簡潔な説明文。</p>
                        <a href="#" class="btn btn-secondary"><i class="fas fa-arrow-right me-1"></i>詳細を見る</a>
                    </div>
                </div>
            </div>
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-images me-2"></i>ギャラリー</h5>
                        <div class="row">
                            <div class="col-md-4 mb-2"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/img/photos/download.png" class="img-fluid rounded" alt="Placeholder Image"></div>
                            <div class="col-md-4 mb-2"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/img/photos/download.png" class="img-fluid rounded" alt="Placeholder Image"></div>
                            <div class="col-md-4 mb-2"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/img/photos/download.png" class="img-fluid rounded" alt="Placeholder Image"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php get_footer(); // footer.php を読み込む ?>
