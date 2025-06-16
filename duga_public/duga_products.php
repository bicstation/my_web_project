<?php
// C:\project\my_web_project\duga_content\public\duga_products.php

// index.php で既にPDO接続が確立されているため、グローバルな $pdo オブジェクトを利用します。
global $pdo; // index.php から渡されるグローバルなPDOオブジェクトを宣言

// $pdo が利用可能かチェック
if (!isset($pdo)) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mx-auto my-4 max-w-xl' role='alert'>";
    echo "<strong class='font-bold'>システムエラー:</strong> ";
    echo "<span class='block sm:inline'>データベース接続 (PDO) が利用できません。index.php の初期化処理を確認してください。</span>";
    echo "</div>";
    // エラーが発生した場合、これ以上の処理は行わない
    return;
}

try {
    // 'duga' を source_api としている商品データを取得するSQLクエリ
    // PDO::prepare を使用してSQLインジェクションを防ぐ
    $stmt = $pdo->prepare("SELECT id, product_id, title, genre, url, image_url FROM products WHERE source_api = :source_api ORDER BY title ASC");
    $stmt->execute([':source_api' => 'duga']);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // データベースクエリ実行時のエラーハンドリング
    // 開発時には詳細なエラーメッセージを表示しても良いが、本番環境では一般的なメッセージに留める
    error_log("Duga Products Database Error: " . $e->getMessage()); // ログに出力
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mx-auto my-4 max-w-xl' role='alert'>";
    echo "<strong class='font-bold'>データベースエラー:</strong> ";
    echo "<span class='block sm:inline'>商品データの取得中に問題が発生しました。</span>";
    echo "<p class='mt-2'>詳細はシステムログをご確認ください。</p>";
    echo "</div>";
    // エラーが発生した場合、これ以上の処理は行わない
    return;
}

?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    /* product-card のスタイルは必要 */
    .product-card {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease-in-out;
        display: flex; /* Flexbox を使用してコンテンツを制御 */
        flex-direction: column; /* 縦方向に並べる */
    }
    .product-card:hover {
        transform: translateY(-5px);
    }
    .product-card-image {
        width: 100%;
        height: 200px; /* 画像の高さを固定 */
        object-fit: cover; /* 画像をはみ出さないように調整 */
        border-radius: 0.5rem; /* rounded-md */
        margin-bottom: 1rem; /* mb-4 */
    }
    .product-description-flex {
        flex-grow: 1; /* 残りのスペースを埋める */
        margin-bottom: 1rem; /* mb-4 */
    }
    .product-button {
        width: 100%; /* ボタンを幅いっぱいに */
        margin-top: auto; /* ボタンを一番下に配置 */
    }
</style>

<div class="container mx-auto p-4 bg-white rounded-lg shadow-xl">
    <h1 class="text-3xl font-bold text-center text-gray-800 mb-8 sm:text-4xl rounded-md bg-gradient-to-r from-purple-500 to-indigo-600 text-white p-4">Duga 商品一覧</h1>

    <?php
    if (count($products) > 0) {
        echo "<div class='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6'>";
        // 各行のデータを出力
        foreach ($products as $row) {
            $image_src = htmlspecialchars($row["image_url"] ?? "https://placehold.co/400x300/e2e8f0/64748b?text=No+Image");
            $product_title = htmlspecialchars($row["title"] ?? "不明な商品");
            $product_genre = htmlspecialchars($row["genre"] ?? "ジャンル不明");
            $product_url = htmlspecialchars($row["url"] ?? "#"); // URLがない場合のデフォルト

            echo "
            <div class='product-card bg-white rounded-lg overflow-hidden p-4 border border-gray-200 text-center'>
                <img src='{$image_src}' alt='{$product_title}' class='product-card-image' onerror=\"this.onerror=null;this.src='https://placehold.co/400x300/e2e8f0/64748b?text=画像エラー';\">
                <h2 class='text-xl font-semibold text-gray-900 mb-2'>{$product_title}</h2>
                <p class='text-gray-600 text-sm product-description-flex'>ジャンル: {$product_genre}</p>
                <a href='{$product_url}' target='_blank' rel='noopener noreferrer' class='product-button bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out transform hover:scale-105'>詳細を見る</a>
            </div>
            ";
        }
        echo "</div>";
    } else {
        echo "<p class='text-center text-gray-700 text-lg py-8'>商品が見つかりませんでした。データベースの `products` テーブルに `source_api` が 'duga' のデータがあるか確認してください。</p>";
    }
    ?>
</div>