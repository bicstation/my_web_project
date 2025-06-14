<?php
// C:\project\my_web_project\app\public\duga_product_detail.php
// Duga商品の個別詳細ページ (index.phpにインクルードされることを想定)

// LoggerとDatabaseクラスは親スクリプト (index.php) でuseされている、
// もしくはオートロードされることを前提とする
use App\Core\Logger;
use App\Core\Database;

global $pdo, $logger; // index.php で設定された$pdoと$loggerを利用

// VS Codeの型推論を助けるためのPHPDocを追加
/** @var \App\Core\Logger $logger */
/** @var \PDO $pdo */

$product = null;
$errorMessage = '';
$productId = $_GET['product_id'] ?? ''; // URLからproduct_idを取得
$relatedProducts = []; // 関連商品データを格納する配列
$prevProductLink = null; // 前の商品へのリンク
$nextProductLink = null; // 次の商品へのリンク

// 受信したproduct_idをログに出力
if ($logger) {
    $logger->log("Duga商品詳細ページ: 受信した product_id: '{$productId}'");
}

if (empty($productId)) {
    $errorMessage = "商品IDが指定されていません。";
    if ($logger) {
        $logger->warning("Duga商品詳細ページ: 商品IDが指定されていません。");
    }
} else {
    try {
        if (!$pdo) {
            // $pdoがまだセットされていない場合は、ここでデータベース接続を確立
            $dbConfig = [
                'host'    => $_ENV['DB_HOST'] ?? 'localhost',
                'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
                'user'    => $_ENV['DB_USER'] ?? 'root',
                'pass'    => $_ENV['DB_PASS'] ?? 'password',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            ];
            $database = new Database($dbConfig, $logger);
            $pdo = $database->getConnection();
            if ($logger) {
                $logger->log("Duga商品詳細ページ: PDOが未設定だったため、再接続を試みました。");
            }
        }

        // 指定されたproduct_idの商品データを取得
        $stmt = $pdo->prepare("SELECT product_id, title, release_date, maker_name, genre, url, image_url FROM products WHERE source_api = 'Duga' AND product_id = :product_id LIMIT 1");
        $stmt->execute([':product_id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errorMessage = "指定された商品が見つかりませんでした。";
            if ($logger) {
                $logger->warning("Duga商品詳細ページ: 商品ID '{$productId}' の商品が見つかりませんでした。DBクエリの結果: " . json_encode($stmt->errorInfo()));
            }
        } else {
            if ($logger) {
                $logger->log("Duga商品詳細ページ: 商品ID '{$productId}' の詳細データを取得しました。タイトル: '{$product['title']}'");
                $logger->log("Duga商品詳細ページ: 取得した商品のジャンル: '{$product['genre']}'");
            }

            // 前後の商品のIDを取得するロジック
            // release_dateとproduct_idで順序を決定
            if (!empty($product['genre']) && !empty($product['release_date'])) {
                $current_release_date = $product['release_date'];
                $current_product_id = $product['product_id'];
                $current_genre = $product['genre'];

                // 前の商品 (現在のジャンル内で、リリース日が古い、またはリリース日が同じでproduct_idがより小さいもの)
                $sql_prev = "SELECT product_id FROM products WHERE source_api = 'Duga' AND genre = ? AND (release_date < ? OR (release_date = ? AND product_id < ?)) ORDER BY release_date DESC, product_id DESC LIMIT 1";
                $params_prev = [
                    $current_genre,
                    $current_release_date,
                    $current_release_date,
                    $current_product_id
                ];
                if ($logger) {
                    $logger->log("Executing SQL_PREV: " . $sql_prev . " with params: " . json_encode($params_prev));
                }
                $stmt_prev = $pdo->prepare($sql_prev);
                $stmt_prev->execute($params_prev);
                $prevProduct = $stmt_prev->fetch(PDO::FETCH_ASSOC);
                if ($prevProduct) {
                    $prevProductLink = 'http://' . $_SERVER['HTTP_HOST'] . '/index.php?page=duga_product_detail&product_id=' . urlencode($prevProduct['product_id']);
                    $logger->log("Duga商品詳細ページ: 前の商品ID: {$prevProduct['product_id']}");
                } else {
                    $logger->log("Duga商品詳細ページ: 前の商品が見つかりませんでした。");
                }

                // 次の商品 (現在のジャンル内で、リリース日が新しい、またはリリース日が同じでproduct_idがより大きいもの)
                $sql_next = "SELECT product_id FROM products WHERE source_api = 'Duga' AND genre = ? AND (release_date > ? OR (release_date = ? AND product_id > ?)) ORDER BY release_date ASC, product_id ASC LIMIT 1";
                $params_next = [
                    $current_genre,
                    $current_release_date,
                    $current_release_date,
                    $current_product_id
                ];
                if ($logger) {
                    $logger->log("Executing SQL_NEXT: " . $sql_next . " with params: " . json_encode($params_next));
                }
                $stmt_next = $pdo->prepare($sql_next);
                $stmt_next->execute($params_next);
                $nextProduct = $stmt_next->fetch(PDO::FETCH_ASSOC);
                if ($nextProduct) {
                    $nextProductLink = 'http://' . $_SERVER['HTTP_HOST'] . '/index.php?page=duga_product_detail&product_id=' . urlencode($nextProduct['product_id']);
                    $logger->log("Duga商品詳細ページ: 次の商品ID: {$nextProduct['product_id']}");
                } else {
                    $logger->log("Duga商品詳細ページ: 次の商品が見つかりませんでした。");
                }
            } else {
                $logger->log("Duga商品詳細ページ: 現在の商品のジャンルまたはリリース日が空のため、前後の商品を取得できません。");
            }


            // 関連商品の取得
            if (!empty($product['genre'])) {
                $related_products_display_limit = 6; // 表示する関連商品の数

                // 同じジャンルの他のDuga商品を取得（現在の商品を除く）
                // 関連商品もrelease_dateの降順で並べます。
                $sql_related = "SELECT product_id, title, image_url, maker_name FROM products WHERE source_api = 'Duga' AND genre = :genre_related AND product_id != :current_product_id_related ORDER BY release_date DESC LIMIT :limit_val_related";
                $stmt_related = $pdo->prepare($sql_related);
                $params_related = [ // bindValueの代わりにexecuteの配列形式を使用
                    ':genre_related' => $product['genre'],
                    ':current_product_id_related' => $productId,
                    ':limit_val_related' => (int)$related_products_display_limit // int型であることを保証
                ];
                if ($logger) {
                    $logger->log("Executing SQL_RELATED: " . $sql_related . " with params: " . json_encode($params_related));
                }
                $stmt_related->execute($params_related); // ここでパラメータを渡す
                $relatedProducts = $stmt_related->fetchAll(PDO::FETCH_ASSOC);

                if ($logger) {
                    $logger->log("Duga商品詳細ページ: ジャンル '{$product['genre']}' の関連商品を " . count($relatedProducts) . " 件取得しました。");
                    if (empty($relatedProducts)) {
                        $logger->log("Duga商品詳細ページ: 関連商品が見つかりませんでした。考えられる原因: 同じジャンルの他の商品がない、またはジャンル名がデータベースと一致しない。");
                    }
                }
            } else {
                if ($logger) {
                    $logger->log("Duga商品詳細ページ: 現在の商品のジャンルが空のため、関連商品を取得できません。");
                }
            }
        }

    } catch (PDOException $e) {
        $errorMessage = "データベースエラーが発生しました: " . htmlspecialchars($e->getMessage());
        error_log("Duga Product Detail DB error: " . $e->getMessage());
        if ($logger) {
            $logger->error("Duga Product Detail DB error: " . $e->getMessage());
        }
    } catch (Exception $e) {
        $errorMessage = "アプリケーションエラーが発生しました: " . htmlspecialchars($e->getMessage());
        error_log("Duga Product Detail application error: " . $e->getMessage());
        if ($logger) {
            $logger->error("Duga Product Detail application error: " . $e->getMessage());
        }
    }
}
?>

<style>
    /* 個別ページ用のスタイル */
    .product-detail-card {
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        padding: 30px;
        margin-top: 30px;
        background-color: #ffffff;
    }
    .product-image {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .product-info h2 {
        color: #007bff;
        font-weight: bold;
        margin-bottom: 20px;
    }
    .product-info p {
        font-size: 1.05rem;
        line-height: 1.8;
        color: #343a40;
    }
    .product-info strong {
        color: #000;
    }
    .btn-affiliate-detail {
        background-color: #28a745;
        border-color: #28a745;
        color: #ffffff;
        font-weight: bold;
        border-radius: 8px;
        padding: 15px 30px;
        font-size: 1.2rem;
        transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        width: 100%;
        margin-top: 30px;
    }
    .btn-affiliate-detail:hover {
        background-color: #218838;
        border-color: #1e7e34;
        transform: translateY(-3px);
    }
    .back-link {
        margin-top: 20px;
        display: block;
        text-align: center;
        font-size: 1.1rem;
    }
    .back-link a {
        color: #007bff;
        text-decoration: none;
        font-weight: 500;
    }
    .back-link a:hover {
        text-decoration: underline;
    }

    /* 前後ページナビゲーションのスタイル */
    .product-navigation {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        margin-bottom: 20px;
    }
    .product-navigation .btn-prev {
        background-color: #6c757d; /* Gray for previous */
        border-color: #6c757d;
        color: white;
    }
    .product-navigation .btn-prev:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }
    .product-navigation .btn-next {
        background-color: #007bff; /* Blue for next */
        border-color: #007bff;
        color: white;
    }
    .product-navigation .btn-next:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }
</style>

<div class="container-fluid bg-white p-4 rounded shadow-sm">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
            <li class="breadcrumb-item"><a href="/"><i class="fas fa-home me-1"></i>ホーム</a></li>
            <li class="breadcrumb-item"><a href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/index.php?page=duga_products_page"><i class="fas fa-video me-1"></i>Duga商品一覧</a></li>
            <?php if (!empty($product['genre'])): ?>
            <li class="breadcrumb-item"><a href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/index.php?page=duga_products_page&genre=<?= urlencode($product['genre']) ?>"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($product['genre']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-info-circle me-1"></i>商品詳細</li>
        </ol>
    </nav>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger mt-3" role="alert">
            <?= $errorMessage ?>
            <a href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/index.php?page=duga_products_page" class="btn btn-sm btn-primary ms-3">一覧に戻る</a>
        </div>
    <?php elseif ($product): ?>
        <!-- 前後ページナビゲーション (上部) -->
        <?php if ($prevProductLink || $nextProductLink): // どちらかのリンクがある場合のみナビゲーションを表示 ?>
            <div class="product-navigation">
                <?php if ($prevProductLink): ?>
                    <a href="<?= $prevProductLink ?>" class="btn btn-prev"><i class="fas fa-chevron-left me-2"></i>前の商品</a>
                <?php endif; ?>
                <?php if ($nextProductLink): ?>
                    <a href="<?= $nextProductLink ?>" class="btn btn-next">次の商品<i class="fas fa-chevron-right ms-2"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="product-detail-card row">
            <div class="col-md-5 text-center">
                <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://placehold.co/500x370/e9ecef/6c757d?text=No Image') ?>" class="product-image" alt="<?= htmlspecialchars($product['title'] ?? 'No Title') ?>" onerror="this.onerror=null;this.src='https://placehold.co/500x370/e9ecef/6c757d?text=No Image';">
                <?php if (!empty($product['url'])): ?>
                    <a href="<?= htmlspecialchars($product['url']) ?>" class="btn btn-affiliate-detail track-purchase-link" data-product-id="<?= htmlspecialchars($product['product_id']) ?>" data-affiliate-url="<?= htmlspecialchars($product['url']) ?>" target="_blank" rel="noopener noreferrer">Dugaで購入 <i class="fas fa-external-link-alt ms-1"></i></a>
                <?php else: ?>
                    <button class="btn btn-secondary btn-affiliate-detail" disabled>Dugaで購入 (リンクなし)</button>
                <?php endif; ?>
            </div>
            <div class="col-md-7 product-info">
                <h2><?= htmlspecialchars($product['title'] ?? 'タイトル不明') ?></h2>
                <p><strong>製品ID:</strong> <?= htmlspecialchars($product['product_id'] ?? '不明') ?></p>
                <p><strong>メーカー:</strong> <?= htmlspecialchars($product['maker_name'] ?? '不明') ?></p>
                <p><strong>ジャンル:</strong> <?= htmlspecialchars($product['genre'] ?? '不明') ?></p>
                <p><strong>発売日:</strong> <?= htmlspecialchars($product['release_date'] ?? '不明') ?></p>
                <p>ここに詳細な商品説明が入ります。例えば、キャスト、あらすじ、特典情報など。
                現在のデータベーススキーマではこれらの詳細情報が直接 products テーブルに保存されていないため、
                必要であれば raw_api_data からパースするか、products テーブルにカラムを追加してください。</p>
            </div>
        </div>

        <!-- 前後ページナビゲーション (下部) -->
        <?php if ($prevProductLink || $nextProductLink): // どちらかのリンクがある場合のみナビゲーションを表示 ?>
            <div class="product-navigation">
                <?php if ($prevProductLink): ?>
                    <a href="<?= $prevProductLink ?>" class="btn btn-prev"><i class="fas fa-chevron-left me-2"></i>前の商品</a>
                <?php endif; ?>
                <?php if ($nextProductLink): ?>
                    <a href="<?= $nextProductLink ?>" class="btn btn-next">次の商品<i class="fas fa-chevron-right ms-2"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 関連商品の表示セクション -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="card mt-5 border-0 shadow-sm">
            <div class="card-header bg-primary text-white rounded-top-lg py-3">
                <h4 class="mb-0"><i class="fas fa-puzzle-piece me-2"></i>このジャンルの関連商品</h4>
            </div>
            <div class="card-body">
                <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
                    <?php foreach ($relatedProducts as $relatedItem): ?>
                    <div class="col">
                        <div class="card h-100 border rounded-lg overflow-hidden shadow-sm">
                            <img src="<?= htmlspecialchars($relatedItem['image_url'] ?? 'https://placehold.co/200x150/e9ecef/6c757d?text=No Image') ?>" class="card-img-top" alt="<?= htmlspecialchars($relatedItem['title'] ?? 'タイトル不明') ?>" onerror="this.onerror=null;this.src='https://placehold.co/200x150/e9ecef/6c757d?text=No Image';">
                            <div class="card-body p-2 d-flex flex-column">
                                <h6 class="card-title mb-1 text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($relatedItem['title'] ?? 'タイトル不明') ?></h6>
                                <p class="card-text text-muted mb-2" style="font-size: 0.8rem;">
                                    <i class="fas fa-industry me-1"></i><?= htmlspecialchars($relatedItem['maker_name'] ?? '不明') ?>
                                </p>
                                <div class="mt-auto">
                                    <?php
                                    $relatedDetailPageLink = 'http://' . $_SERVER['HTTP_HOST'] . '/index.php?page=duga_product_detail&product_id=' . urlencode($relatedItem['product_id']);
                                    ?>
                                    <a href="<?= $relatedDetailPageLink ?>" class="btn btn-sm btn-outline-primary w-100 rounded-pill" target="_self">詳細 <i class="fas fa-chevron-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/index.php?page=duga_products_page"><i class="fas fa-arrow-left me-2"></i>Duga商品一覧に戻る</a>
        </div>
    <?php else: ?>
        <div class="alert alert-info mt-3" role="alert">
            詳細を表示する商品が見つかりませんでした。
            <a href="http://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/index.php?page=duga_products_page" class="btn btn-sm btn-primary ms-3">一覧に戻る</a>
        </div>
    <?php endif; ?>
</div>

<!-- Bootstrap JS Bundle (Popper.jsを含む) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 全ての「Dugaで購入」リンク（トラック対象）を取得
    const purchaseLinks = document.querySelectorAll('.track-purchase-link');

    purchaseLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            // デフォルトのリンク遷移を一時的に阻止
            event.preventDefault();

            // データ属性からproduct_idとアフィリエイトURLを取得
            const productId = this.dataset.productId;
            const affiliateUrl = this.dataset.affiliateUrl;

            // トラッキングデータを準備
            const trackingData = {
                product_id: productId,
                click_type: 'affiliate_purchase',
                referrer: window.location.href, // 現在のページのURL
                user_agent: navigator.userAgent, // ユーザーエージェント
                // IPアドレスはサーバーサイドで取得するため、ここでは送信しない
                // session_id はPHP側で管理している場合は、PHPからJSに渡す必要がある
            };

            // トラッキングエンドポイントにデータを送信
            fetch('/track_click.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(trackingData),
            })
            .then(response => {
                if (!response.ok) {
                    console.error('トラッキングデータ送信に失敗しました:', response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    console.log('トラッキングデータが正常に送信されました:', data.message);
                } else {
                    console.error('トラッキングデータ送信エラー:', data.message);
                }
            })
            .catch(error => {
                console.error('トラッキングデータ送信中にエラーが発生しました:', error);
            })
            .finally(() => {
                // トラッキングの成否に関わらず、最終的にアフィリエイトURLへ遷移
                window.open(affiliateUrl, '_blank');
            });
        });
    });
});
</script>
