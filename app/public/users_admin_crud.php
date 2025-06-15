<?php
// C:\project\my_web_project\app\public\users_admin_crud.php

// 必須: グローバルスコープからロガーとPDOインスタンスにアクセスできるようにする
global $pdo, $logger;

// CSRFトークンを取得
$csrfToken = App\Core\Session::generateCsrfToken();

// ユーザー管理ページにアクセスするには管理者権限が必要
if (!App\Core\Session::isLoggedIn() || App\Core\Session::getUserRole() !== 'admin') {
    App\Core\Session::set('flash_message', '<div class="alert alert-danger">このページにアクセスする権限がありません。</div>');
    header('Location: index.php?page=home');
    exit();
}

$message = '';
$users = [];

// フォームが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';

    // CSRFトークン検証
    if (!App\Core\Session::verifyCsrfToken($submittedCsrfToken)) {
        App\Core\Session::set('flash_message', '<div class="alert alert-danger">不正なリクエストです。ページを再読み込みしてください。</div>');
        $logger->error("CSRFトークン検証失敗: users_admin_crud - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
        header('Location: index.php?page=users_admin');
        exit();
    }

    try {
        switch ($action) {
            case 'add':
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';

                if (empty($username) || empty($email) || empty($password)) {
                    $message = '<div class="alert alert-danger">ユーザー名、メール、パスワードは必須です。</div>';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = '<div class="alert alert-danger">無効なメールアドレス形式です。</div>';
                } else {
                    // ユーザー名またはメールが既に存在するかチェック
                    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
                    $stmt_check->execute([':username' => $username, ':email' => $email]);
                    if ($stmt_check->fetch()) {
                        $message = '<div class="alert alert-danger">そのユーザー名またはメールアドレスは既に登録されています。</div>';
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)");
                        if ($stmt->execute([':username' => $username, ':email' => $email, ':password_hash' => $passwordHash, ':role' => $role])) {
                            $message = '<div class="alert alert-success">ユーザーが追加されました。</div>';
                            $logger->info("ユーザー追加成功: ID - " . $pdo->lastInsertId() . ", Username - " . $username . ", Role - " . $role);
                        } else {
                            $message = '<div class="alert alert-danger">ユーザーの追加に失敗しました。</div>';
                            $logger->error("ユーザー追加失敗: " . json_encode($stmt->errorInfo())); // ここを修正
                        }
                    }
                }
                break;

            case 'edit':
                $id = $_POST['id'] ?? 0;
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $role = $_POST['role'] ?? 'user';
                $password = $_POST['password'] ?? ''; // パスワードはオプション

                if (empty($id) || empty($username) || empty($email)) {
                    $message = '<div class="alert alert-danger">ユーザーID、ユーザー名、メールは必須です。</div>';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = '<div class="alert alert-danger">無効なメールアドレス形式です。</div>';
                } else {
                    // 他のユーザーが同じユーザー名またはメールアドレスを使用していないかチェック (自分自身は除く)
                    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id LIMIT 1");
                    $stmt_check->execute([':username' => $username, ':email' => $email, ':id' => $id]);
                    if ($stmt_check->fetch()) {
                        $message = '<div class="alert alert-danger">そのユーザー名またはメールアドレスは既に他のユーザーに使用されています。</div>';
                    } else {
                        $sql = "UPDATE users SET username = :username, email = :email, role = :role";
                        $params = [
                            ':username' => $username,
                            ':email' => $email,
                            ':role' => $role,
                            ':id' => $id
                        ];

                        if (!empty($password)) {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $sql .= ", password_hash = :password_hash";
                            $params[':password_hash'] = $passwordHash;
                        }
                        $sql .= " WHERE id = :id";
                        $stmt = $pdo->prepare($sql);

                        if ($stmt->execute($params)) {
                            $message = '<div class="alert alert-success">ユーザーが更新されました。</div>';
                            $logger->info("ユーザー更新成功: ID - " . $id . ", Username - " . $username . ", Role - " . $role);
                        } else {
                            $message = '<div class="alert alert-danger">ユーザーの更新に失敗しました。</div>';
                            $logger->error("ユーザー更新失敗: " . json_encode($stmt->errorInfo())); // ここを修正
                        }
                    }
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? 0;
                if (!empty($id)) {
                    // 削除対象のユーザーが自分自身ではないことを確認
                    if ($id == App\Core\Session::getUserId()) {
                        $message = '<div class="alert alert-danger">自分自身のアカウントは削除できません。</div>';
                        $logger->warning("自分自身のアカウント削除試行: User ID - " . App\Core\Session::getUserId());
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                        if ($stmt->execute([':id' => $id])) {
                            $message = '<div class="alert alert-success">ユーザーが削除されました。</div>';
                            $logger->info("ユーザー削除成功: ID - " . $id);
                        } else {
                            $message = '<div class="alert alert-danger">ユーザーの削除に失敗しました。</div>';
                            $logger->error("ユーザー削除失敗: " . json_encode($stmt->errorInfo())); // ここを修正
                        }
                    }
                }
                break;
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">データベースエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $logger->error("ユーザー管理操作データベースエラー: " . $e->getMessage()); // ここを修正
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">予期せぬエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $logger->error("ユーザー管理操作予期せぬエラー: " . $e->getMessage()); // ここを修正
    }
}

// ユーザーリストの取得
try {
    $stmt = $pdo->query("SELECT id, username, email, role FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">ユーザーリストの取得に失敗しました: ' . htmlspecialchars($e->getMessage()) . '</div>';
    $logger->error("ユーザーリスト取得データベースエラー: " . $e->getMessage()); // ここを修正
    $users = []; // エラー時は空にする
}

?>

<div class="container-fluid mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded shadow-sm">
            <li class="breadcrumb-item"><a href="/"><i class="fas fa-home me-1"></i>ホーム</a></li>
            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-users-cog me-1"></i>ユーザー管理</li>
        </ol>
    </nav>

    <h1 class="mb-4 text-center">ユーザー管理</h1>

    <?php echo $message; ?>

    <!-- ユーザー追加フォーム -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>ユーザー追加</h5>
            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#addUserForm" aria-expanded="false" aria-controls="addUserForm">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="collapse show" id="addUserForm">
            <div class="card-body">
                <form action="index.php?page=users_admin" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="mb-3">
                        <label for="addUsername" class="form-label">ユーザー名</label>
                        <input type="text" class="form-control" id="addUsername" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="addEmail" class="form-label">メールアドレス</label>
                        <input type="email" class="form-control" id="addEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="addPassword" class="form-label">パスワード</label>
                        <input type="password" class="form-control" id="addPassword" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="addRole" class="form-label">役割</label>
                        <select class="form-select" id="addRole" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>ユーザー追加</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ユーザーリスト -->
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>既存ユーザー</h5>
        </div>
        <div class="card-body">
            <?php if (empty($users)) : ?>
                <div class="alert alert-warning">ユーザーが登録されていません。</div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>ユーザー名</th>
                                <th>メールアドレス</th>
                                <th>役割</th>
                                <th>アクション</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning me-2" data-bs-toggle="modal" data-bs-target="#editUserModal" data-id="<?php echo htmlspecialchars($user['id']); ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-email="<?php echo htmlspecialchars($user['email']); ?>" data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                            <i class="fas fa-edit"></i> 編集
                                        </button>
                                        <form action="index.php?page=users_admin" method="POST" class="d-inline" onsubmit="return confirm('本当にこのユーザーを削除しますか？');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" <?php echo ($user['id'] == App\Core\Session::getUserId()) ? 'disabled title="自分自身は削除できません"' : ''; ?>>
                                                <i class="fas fa-trash-alt"></i> 削除
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ユーザー編集モーダル -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-edit me-2"></i>ユーザー編集</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?page=users_admin" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" id="editUserId" name="id">
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">ユーザー名</label>
                        <input type="text" class="form-control" id="editUsername" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">メールアドレス</label>
                        <input type="email" class="form-control" id="editEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPassword" class="form-label">新しいパスワード (変更しない場合は空欄)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="editRole" class="form-label">役割</label>
                        <select class="form-select" id="editRole" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-2"></i>変更を保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // 編集モーダルにデータをセットするためのJavaScript
    document.addEventListener('DOMContentLoaded', function() {
        var editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget; // モーダルをトリガーしたボタン
            var id = button.getAttribute('data-id');
            var username = button.getAttribute('data-username');
            var email = button.getAttribute('data-email');
            var role = button.getAttribute('data-role');

            var modalId = editUserModal.querySelector('#editUserId');
            var modalUsername = editUserModal.querySelector('#editUsername');
            var modalEmail = editUserModal.querySelector('#editEmail');
            var modalRole = editUserModal.querySelector('#editRole');
            var modalPassword = editUserModal.querySelector('#editPassword'); // パスワードフィールドをクリア

            modalId.value = id;
            modalUsername.value = username;
            modalEmail.value = email;
            modalRole.value = role;
            modalPassword.value = ''; // パスワードフィールドは常にクリアしておく
        });
    });
</script>
