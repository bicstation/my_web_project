    <?php
    // C:\project\my_web_project\includes\sidebar.php

    // init.php または index.php で $pdo と $logger がグローバルスコープに設定されていることを前提とする
    global $pdo, $logger;

    // 現在のホスト名が 'duga.tipers.live' かどうかをチェック
    $isDugaDomain = ($_SERVER['HTTP_HOST'] === 'duga.tipers.live');

    $dugaGenres = [];
    if ($isDugaDomain && $pdo) {
        try {
            // products テーブルから Duga のユニークなジャンルを取得
            $stmt = $pdo->prepare("SELECT DISTINCT genre FROM products WHERE source_api = 'Duga' AND genre IS NOT NULL AND genre != '' ORDER BY genre ASC");
            $stmt->execute();
            $dugaGenres = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $logger->log("サイドバーにDugaジャンルをロードしました: " . implode(', ', $dugaGenres));
        } catch (PDOException $e) {
            $logger->error("サイドバーでのDugaジャンル取得エラー: " . $e->getMessage());
            // エラーをユーザーに表示しないが、ログには記録
        }
    }
    ?>

    <div class="sidebar">
        <div class="sidebar-header">
            <h5 class="text-white">サイドメニュー</h5>
            <button type="button" class="btn-close btn-close-white d-lg-none" aria-label="Close" id="sidebarToggle"></button>
        </div>
        <ul class="nav flex-column">
            <?php if ($isDugaDomain): ?>
                <!-- Dugaドメインの場合のサイドバー -->
                <li class="nav-item">
                    <a class="nav-link active" href="http://duga.tipers.live"><i class="fas fa-home me-2"></i>Dugaホーム</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle" href="#dugaGenresSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="dugaGenresSubmenu">
                        <i class="fas fa-video me-2"></i>Dugaジャンル
                    </a>
                    <div class="collapse" id="dugaGenresSubmenu">
                        <ul class="nav flex-column ps-3">
                            <?php if (!empty($dugaGenres)): ?>
                                <?php foreach ($dugaGenres as $genre): ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="http://duga.tipers.live?genre=<?= urlencode($genre) ?>">
                                            <i class="fas fa-tag me-2"></i><?= htmlspecialchars($genre) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="nav-item">
                                    <span class="nav-link text-muted">ジャンルなし</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </li>
                <!-- 必要に応じてDuga関連の他のリンクを追加 -->
            <?php else: ?>
                <!-- 通常ドメインの場合のサイドバー -->
                <li class="nav-item">
                    <a class="nav-link active" href="/"><i class="fas fa-home me-2"></i>ホーム</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#category1Submenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="category1Submenu">
                        <i class="fas fa-folder me-2"></i>カテゴリ 1
                    </a>
                    <div class="collapse show" id="category1Submenu">
                        <ul class="nav flex-column ps-3">
                            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-1</a></li>
                            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-2</a></li>
                            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-file-alt me-2"></i>サブメニュー 1-3</a></li>
                            <li class="nav-item"><a class="nav-link" href="/add_admin_user.php"><i class="fas fa-user-plus me-2"></i>ユーザー登録</a></li>
                            <li class="nav-item"><a class="nav-link" href="/index.php?page=products_admin"><i class="fas fa-cube me-2"></i>商品登録</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#category2Submenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="category2Submenu">
                        <i class="fas fa-folder me-2"></i>カテゴリ 2
                    </a>
                    <div class="collapse" id="category2Submenu">
                        <ul class="nav flex-column ps-3">
                            <li class="nav-item"><a class="nav-link" href="#"><i class="fas fa-chart-bar me-2"></i>データ分析</a></li>
                            <!-- その他のサブメニュー -->
                        </ul>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#authSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="authSubmenu">
                        <i class="fas fa-lock me-2"></i>認証
                    </a>
                    <div class="collapse" id="authSubmenu">
                        <ul class="nav flex-column ps-3">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <li class="nav-item"><a class="nav-link" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>ログアウト</a></li>
                                <li class="nav-item"><a class="nav-link" href="/index.php?page=users_admin"><i class="fas fa-users-cog me-2"></i>ユーザー管理</a></li>
                            <?php else: ?>
                                <li class="nav-item"><a class="nav-link" href="/login.php"><i class="fas fa-sign-in-alt me-2"></i>ログイン</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- サイドバー切り替えトグルボタン (モバイル用) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.grid-main'); // メインコンテンツの親要素

            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('sidebar-active'); // メインコンテンツのシフト用クラス
                });
            }
        });
    </script>
    