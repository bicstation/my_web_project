<?php
// my-custom-theme/footer.php

/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#footer-php
 *
 * @package WordPress
 * @subpackage YourThemeName
 * @since YourThemeName 1.0
 */

?>
        </main><!-- #main-content-area -->
        <footer class="bg-dark text-white py-4 mt-auto grid-footer">
            <div class="container">
                <div class="row">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <h5><i class="fas fa-link me-2"></i>関連ドメイン</h5>
                        <ul class="list-unstyled">
                            <li><a href="http://tipers.live/" class="text-white text-decoration-none"><i class="fas fa-globe me-2"></i>tipers.live (メインサイト)</a></li>
                            <li><a href="http://admin.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-user-cog me-2"></i>admin.tipers.live (管理パネル)</a></li>
                            <li><a href="http://dti.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>dti.tipers.live (DTI環境)</a></li>
                            <li><a href="http://duga.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>duga.tipers.live (DUGA環境)</a></li>
                            <li><a href="http://fanza.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>fanza.tipers.live (FANZA環境)</a></li>
                            <li><a href="http://dmm.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>dmm.tipers.live (DMM.com環境)</a></li>
                            <li><a href="http://okashi.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>okashi.tipers.live (お菓子環境)</a></li>
                            <li><a href="http://lemon.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>lemon.tipers.live (レモン環境)</a></li>
                            <li><a href="http://b10f.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>b10f.tipers.live (地下10階環境)</a></li>
                            <li><a href="http://sokmil.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>sokmil.tipers.live (ソクミル環境)</a></li>
                            <li><a href="http://mgs.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-film me-2"></i>mgs.tipers.live (MGS環境)</a></li>
                            <li><a href="http://blog.tipers.live/" class="text-white text-decoration-none"><i class="fas fa-blog me-2"></i>blog.tipers.live (ブログ)</a></li>
                            <li><a href="http://tipers.live:8080/" class="text-white text-decoration-none"><i class="fas fa-database me-2"></i>phpMyAdmin (開発用)</a></li>
                        </ul>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <h5><i class="fas fa-info-circle me-2"></i>一般リンク</h5>
                        <ul class="list-unstyled">
                            <li><a href="#" class="text-white text-decoration-none"><i class="fas fa-sitemap me-2"></i>サイトマップ</a></li>
                            <li><a href="#" class="text-white text-decoration-none"><i class="fas fa-shield-alt me-2"></i>プライバシーポリシー</a></li>
                            <li><a href="#" class="text-white text-decoration-none"><i class="fas fa-file-contract me-2"></i>利用規約</a></li>
                            <li><a href="#" class="text-white text-decoration-none"><i class="fas fa-users me-2"></i>会社概要</a></li>
                            <li><a href="#" class="text-white text-decoration-none"><i class="fas fa-rss-square me-2"></i>RSS</a></li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>お問い合わせ</h5>
                        <address class="mb-0">
                            〒306-0615 茨城県坂東市<br>
                            <i class="fas fa-phone-alt me-2"></i>XXX-XXX-XXXX<br>
                            <i class="fas fa-envelope me-2"></i><a href="mailto:info@example.com" class="text-white text-decoration-none">info@example.com</a>
                        </address>
                        <div class="mt-3">
                            <a href="#" class="text-white me-2"><i class="fab fa-twitter-square fa-2x"></i></a>
                            <a href="#" class="text-white me-2"><i class="fab fa-facebook-square fa-2x"></i></a>
                            <a href="#" class="text-white"><i class="fab fa-instagram-square fa-2x"></i></a>
                        </div>
                    </div>
                </div>
                <hr class="my-3 border-secondary">
                <div class="text-center">
                    <p class="mb-0">&copy; 2025 My Awesome Site. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
    <!-- Bootstrap JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS (テーマ固有のJS) -->
    <script src="<?php echo esc_url( get_template_directory_uri() ); ?>/js/script.js"></script>
</body>
</html>