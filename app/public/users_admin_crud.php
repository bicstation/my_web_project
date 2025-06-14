<?php
// C:\project\my_web_project\app\public\users_admin_crud.php
// ユーザー管理画面のコンテンツ

// このファイルは index.php から include_once されることを想定しています。
// そのため、Composerのオートローダーや.envのロード、セッションの開始は
// 既に index.php または init.php で行われているはずです。
// ここでは、必要なクラスのuse宣言と、このページ固有のロジックを記述します。

use App\Core\Database;
use App\Core\Logger;
// use PDOException; // PDOExceptionはPHPのグローバルクラスであるため、このuseステートメントは不要です。

// もし "Class 'App\Core\Logger' not found" エラーが続く場合、
// 一時的に以下の行のコメントを解除してテストしてみてください。
// 問題が解決する場合、Composerのオートロード設定または実行環境に問題がある可能性が高いです。
require_once __DIR__ . '/../src/Core/Logger.php';
require_once __DIR__ . '/../src/Core/Database.php';


// データベース設定は init.php を経由して index.php でロードされているため、
// $_ENV から直接利用可能です。
$dbConfig = [
    'host'    => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname'  => $_ENV['DB_NAME'] ?? 'web_project_db',
    'user'    => $_ENV['DB_USER'] ?? 'root',
    'pass'    => $_ENV['DB_PASS'] ?? 'password',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];

$message = ''; // ユーザーへのメッセージ (成功/エラー)
$users = [];   // データベースから取得したユーザーデータ
$editUser = null; // 編集中のユーザーデータ

try {
    // ロガーのインスタンス化 (このページ専用のログファイル)
    $logger = new Logger('users_admin_crud.log');
    $logger->log("ユーザー管理画面 (users_admin_crud.php) へのアクセス処理を開始します。");

    // データベース接続の確立
    $database = new Database($dbConfig, $logger);
    $pdo = $database->getConnection(); // PDOインスタンスを取得

    // -----------------------------------------------------
    // POSTリクエストの処理 (新規作成/更新/削除)
    // -----------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_user') {
            // ユーザー追加処理
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user'; // デフォルトは 'user'

            if (empty($username) || empty($email) || empty($password)) {
                $message = "<div class='alert alert-danger'>ユーザー名、メールアドレス、パスワードは必須です。</div>";
                $logger->error("ユーザー追加失敗: 必須フィールドが空です。");
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "<div class='alert alert-danger'>有効なメールアドレスを入力してください。</div>";
                $logger->error("ユーザー追加失敗: 無効なメールアドレス '{$email}'。");
            } else {
                // ユーザー名またはメールアドレスが既に存在するかチェック
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
                $stmt_check->execute([':username' => $username, ':email' => $email]);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = "<div class='alert alert-danger'>そのユーザー名またはメールアドレスは既に使用されています。</div>";
                    $logger->warning("ユーザー追加失敗: 重複するユーザー名/メールアドレス ('{$username}' / '{$email}')。");
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)");
                    
                    if ($stmt_insert->execute([
                        ':username' => $username,
                        ':email' => $email,
                        ':password_hash' => $hashed_password,
                        ':role' => $role
                    ])) {
                        $message = "<div class='alert alert-success'>ユーザー「" . htmlspecialchars($username) . "」が正常に追加されました。</div>";
                        $logger->log("ユーザー「{$username}」が正常に追加されました。");
                    } else {
                        $errorInfo = $stmt_insert->errorInfo();
                        $message = "<div class='alert alert-danger'>ユーザーの追加に失敗しました: " . htmlspecialchars($errorInfo[2]) . "</div>";
                        $logger->error("ユーザー追加失敗: " . $errorInfo[2]);
                    }
                }
            }
        } elseif ($action === 'edit_user') {
            // ユーザー編集処理
            $user_id = (int)($_POST['user_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? ''; // パスワードは任意
            $role = $_POST['role'] ?? 'user';

            if ($user_id <= 0 || empty($username) || empty($email)) {
                $message = "<div class='alert alert-danger'>ユーザーID、ユーザー名、メールアドレスは必須です。</div>";
                $logger->error("ユーザー編集失敗: 必須フィールドが空、または無効なユーザーID '{$user_id}'。");
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "<div class='alert alert-danger'>有効なメールアドレスを入力してください。</div>";
                $logger->error("ユーザー編集失敗: 無効なメールアドレス '{$email}'。");
            } else {
                // 他のユーザーが同じユーザー名またはメールアドレスを使用していないかチェック (自分自身を除く)
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
                $stmt_check->execute([':username' => $username, ':email' => $email, ':user_id' => $user_id]);
                if ($stmt_check->fetchColumn() > 0) {
                    $message = "<div class='alert alert-danger'>そのユーザー名またはメールアドレスは既に他のユーザーに使用されています。</div>";
                    $logger->warning("ユーザー編集失敗: 重複するユーザー名/メールアドレス (ID: {$user_id}, ユーザー名: '{$username}', メールアドレス: '{$email}')。");
                } else {
                    $sql = "UPDATE users SET username = :username, email = :email, role = :role, updated_at = NOW()";
                    $params = [
                        ':username' => $username,
                        ':email' => $email,
                        ':role' => $role,
                        ':user_id' => $user_id
                    ];

                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $sql .= ", password_hash = :password_hash";
                        $params[':password_hash'] = $hashed_password;
                    }
                    $sql .= " WHERE id = :user_id";

                    $stmt_update = $pdo->prepare($sql);
                    if ($stmt_update->execute($params)) {
                        $message = "<div class='alert alert-success'>ユーザー「" . htmlspecialchars($username) . "」が正常に更新されました。</div>";
                        $logger->log("ユーザー「{$username}」が正常に更新されました (ID: {$user_id})。");
                    } else {
                        $errorInfo = $stmt_update->errorInfo();
                        $message = "<div class='alert alert-danger'>ユーザーの更新に失敗しました: " . htmlspecialchars($errorInfo[2]) . "</div>";
                        $logger->error("ユーザー更新失敗 (ID: {$user_id}): " . $errorInfo[2]);
                    }
                }
            }
        } elseif ($action === 'delete_user') {
            // ユーザー削除処理
            $user_id = (int)($_POST['user_id'] ?? 0);

            if ($user_id <= 0) {
                $message = "<div class='alert alert-danger'>無効なユーザーIDです。</div>";
                $logger->error("ユーザー削除失敗: 無効なユーザーID '{$user_id}'。");
            } else {
                $stmt_delete = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
                if ($stmt_delete->execute([':user_id' => $user_id])) {
                    $message = "<div class='alert alert-success'>ユーザー (ID: " . htmlspecialchars($user_id) . ") が正常に削除されました。</div>";
                    $logger->log("ユーザー (ID: {$user_id}) が正常に削除されました。");
                } else {
                    $errorInfo = $stmt_delete->errorInfo();
                    $message = "<div class='alert alert-danger'>ユーザーの削除に失敗しました: " . htmlspecialchars($errorInfo[2]) . "</div>";
                    $logger->error("ユーザー削除失敗 (ID: {$user_id}): " . $errorInfo[2]);
                }
            }
        }
        // POST処理後、GETリクエストにリダイレクトしてフォームの二重送信を防ぐ
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=users_admin");
        exit();
    }

    // -----------------------------------------------------
    // GETリクエストの処理 (ユーザー一覧の表示、編集フォームのデータロード)
    // -----------------------------------------------------
    // ユーザー一覧の取得
    $stmt_users = $pdo->query("SELECT id, username, email, role, created_at, updated_at FROM users ORDER BY created_at DESC");
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
    $logger->log(count($users) . " 件のユーザーデータを取得しました。");

    // 編集モードの場合、指定されたユーザーIDのデータをロード
    if (isset($_GET['edit_id']) && (int)$_GET['edit_id'] > 0) {
        $edit_id = (int)$_GET['edit_id'];
        $stmt_edit = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = :id");
        $stmt_edit->execute([':id' => $edit_id]);
        $editUser = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if ($editUser) {
            $logger->log("ユーザーID {$edit_id} の編集データをロードしました。");
        } else {
            $message = "<div class='alert alert-warning'>指定されたユーザーが見つかりませんでした。</div>";
            $logger->warning("編集対象ユーザーID {$edit_id} が見つかりませんでした。");
        }
    }

} catch (PDOException $e) {
    // データベース関連のエラーをキャッチ
    $message = "<div class='alert alert-danger'>データベースエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("Users Admin CRUD DB error: " . $e->getMessage());
    if (isset($logger)) {
        $logger->error("Users Admin CRUD DB error: " . $e->getMessage());
    }
} catch (Exception $e) {
    // その他のアプリケーションエラーをキャッチ
    $message = "<div class='alert alert-danger'>アプリケーションエラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("Users Admin CRUD application error: " . $e->getMessage());
    if (isset($logger)) {
        $logger->error("Users Admin CRUD application error: " . $e->getMessage());
    }
}

// ここからはHTML出力部分
?>

<div class="container-fluid bg-white p-4 rounded shadow-sm">
    <h1 class="mb-4"><i class="fas fa-users-cog me-2"></i>ユーザー管理</h1>

    <?php if (!empty($message)): ?>
        <?= $message ?>
    <?php endif; ?>

    <!-- 新規ユーザー追加/編集フォーム -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <?php if ($editUser): ?>
                    <i class="fas fa-edit me-2"></i>ユーザー編集 (ID: <?= htmlspecialchars($editUser['id']) ?>)
                <?php else: ?>
                    <i class="fas fa-user-plus me-2"></i>新規ユーザー追加
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <input type="hidden" name="action" value="<?= $editUser ? 'edit_user' : 'add_user' ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($editUser['id']) ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="username" class="form-label">ユーザー名</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">メールアドレス</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">パスワード (<?= $editUser ? '変更する場合のみ入力' : '必須' ?>)</label>
                    <input type="password" class="form-control" id="password" name="password" <?= $editUser ? '' : 'required' ?>>
                    <?php if ($editUser): ?>
                        <small class="form-text text-muted">パスワードは変更する場合のみ入力してください。空の場合は変更されません。</small>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">役割</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="user" <?= (($editUser['role'] ?? '') === 'user') ? 'selected' : '' ?>>ユーザー</option>
                        <option value="admin" <?= (($editUser['role'] ?? '') === 'admin') ? 'selected' : '' ?>>管理者</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <?php if ($editUser): ?>
                        <i class="fas fa-sync-alt me-2"></i>ユーザー更新
                    <?php else: ?>
                        <i class="fas fa-user-plus me-2"></i>ユーザー追加
                    <?php endif; ?>
                </button>
                <?php if ($editUser): ?>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>?page=users_admin" class="btn btn-secondary ms-2"><i class="fas fa-times me-2"></i>キャンセル</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ユーザー一覧 -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>登録済みユーザー一覧</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>ユーザー名</th>
                                <th>メールアドレス</th>
                                <th>役割</th>
                                <th>登録日</th>
                                <th>更新日</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['role']) ?></td>
                                    <td><?= htmlspecialchars($user['created_at']) ?></td>
                                    <td><?= htmlspecialchars($user['updated_at']) ?></td>
                                    <td>
                                        <a href="<?= $_SERVER['PHP_SELF'] ?>?page=users_admin&edit_id=<?= htmlspecialchars($user['id']) ?>" class="btn btn-sm btn-info me-1"><i class="fas fa-edit"></i> 編集</a>
                                        <form action="" method="POST" class="d-inline" onsubmit="return confirm('本当にこのユーザーを削除しますか？');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i> 削除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="alert alert-info">現在、登録されているユーザーはいません。</p>
            <?php endif; ?>
        </div>
    </div>
</div>
