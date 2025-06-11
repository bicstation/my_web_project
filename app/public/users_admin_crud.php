<?php
// C:\doc\my_web_project\app\public\users_admin_crud.php
// tipers.live のメインコンテンツエリアに組み込むCRUD機能
// このファイルは、すでに認証チェックが行われた後、index.phpからインクルードされることを想定しています。

// エラー報告を有効にする (開発用)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 環境変数からデータベース接続情報を取得
// .envファイルから設定されているDB_HOST, DB_NAME, DB_USER, DB_PASSWORDを使用
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');

// データベース接続
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// 接続エラーの確認
if ($conn->connect_error) {
    die("データベース接続エラー: " . $conn->connect_error);
}

$message = ""; // 成功・失敗メッセージを格納する変数

// -----------------------------------------------------
// データ追加 (Create) の処理
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);

    // 入力検証
    if (empty($name) || empty($email)) {
        $message = "<div class='alert alert-warning'>名前とEメールは必須です。</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-warning'>有効なEメールアドレスを入力してください。</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $email);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>新しいユーザーが追加されました。</div>";
        } else {
            if ($conn->errno == 1062) { // 1062はDuplicate entryのエラーコード
                $message = "<div class='alert alert-danger'>このEメールは既に登録されています。</div>";
            } else {
                $message = "<div class='alert alert-danger'>ユーザーの追加に失敗しました: " . $stmt->error . "</div>";
            }
        }
        $stmt->close();
    }
}

// -----------------------------------------------------
// データ更新 (Update) の処理
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $id = (int)$_POST['id'];
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);

    if (empty($id) || empty($name) || empty($email)) {
        $message = "<div class='alert alert-warning'>ID、名前、Eメールは必須です。</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-warning'>有効なEメールアドレスを入力してください。</div>";
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $email, $id); // s: string, i: integer

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>ユーザー情報が更新されました。</div>";
        } else {
            if ($conn->errno == 1062) {
                $message = "<div class='alert alert-danger'>このEメールは既に登録されています。</div>";
            } else {
                $message = "<div class='alert alert-danger'>ユーザーの更新に失敗しました: " . $stmt->error . "</div>";
            }
        }
        $stmt->close();
    }
}

// -----------------------------------------------------
// データ削除 (Delete) の処理
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $id = (int)$_POST['id'];

    if (empty($id)) {
        $message = "<div class='alert alert-warning'>削除するユーザーのIDが指定されていません。</div>";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id); // i: integer

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>ユーザーが削除されました。</div>";
        } else {
            $message = "<div class='alert alert-danger'>ユーザーの削除に失敗しました: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}
?>

<div class="container-fluid bg-white p-4 rounded shadow-sm mt-3">
    <h1>ユーザー管理</h1>

    <?php
    // メッセージ表示エリア
    if (!empty($message)) {
        echo $message;
    }

    // -----------------------------------------------------
    // データ表示 (Read) の処理
    // -----------------------------------------------------
    $sql = "SELECT id, name, email, created_at FROM users ORDER BY id DESC"; // 新しいデータが上に来るように
    $result = $conn->query($sql);

    if ($result) {
        if ($result->num_rows > 0) {
            echo "<table class='table table-bordered table-striped'>";
            echo "<thead><tr><th>ID</th><th>名前</th><th>Eメール</th><th>作成日時</th><th>操作</th></tr></thead>";
            echo "<tbody>";
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row["id"]). "</td>";
                echo "<td>" . htmlspecialchars($row["name"]). "</td>"; // XSS対策
                echo "<td>" . htmlspecialchars($row["email"]). "</td>"; // XSS対策
                echo "<td>" . htmlspecialchars($row["created_at"]). "</td>";
                echo "<td>";
                echo "<button class='btn btn-sm btn-info me-2 edit-btn' data-id='" . htmlspecialchars($row['id']) . "' data-name='" . htmlspecialchars($row['name']) . "' data-email='" . htmlspecialchars($row['email']) . "'>編集</button>";

                echo "<form method='POST' action='' class='d-inline-block' onsubmit='return confirm(\"本当に削除しますか？\");'>";
                echo "    <input type='hidden' name='action' value='delete_user'>";
                echo "    <input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>";
                echo "    <button type='submit' class='btn btn-sm btn-danger'>削除</button>";
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p class='alert alert-info'>データが見つかりませんでした。</p>";
        }
    } else {
        echo "<p class='alert alert-danger'>テーブルからデータを取得できませんでした: " . $conn->error . "</p>";
    }

    // -----------------------------------------------------
    // データ追加フォーム (Create Form)
    // -----------------------------------------------------
    ?>
    <div class='form-section'>
        <h2>新しいユーザーを追加</h2>
        <form method='POST' action=''>
            <input type='hidden' name='action' value='add_user'>
            <div class='mb-3'>
                <label for='add_name' class='form-label'>名前</label>
                <input type='text' class='form-control' id='add_name' name='name' required>
            </div>
            <div class='mb-3'>
                <label for='add_email' class='form-label'>Eメール</label>";
                <input type='email' class='form-control' id='add_email' name='email' required>
            </div>
            <button type='submit' class='btn btn-primary'>追加</button>
        </form>
    </div>

    <?php
    // -----------------------------------------------------
    // データ更新フォーム (Update Form) - デフォルトでは非表示
    // -----------------------------------------------------
    ?>
    <div class='form-section' id='edit-form-section' style='display: none;'>
        <h2>ユーザー情報を編集</h2>
        <form method='POST' action=''>
            <input type='hidden' name='action' value='update_user'>
            <input type='hidden' name='id' id='edit_id'>
            <div class='mb-3'>
                <label for='edit_name' class='form-label'>名前</label>
                <input type='text' class='form-control' id='edit_name' name='name' required>
            </div>
            <div class='mb-3'>
                <label for='edit_email' class='form-label'>Eメール</label>
                <input type='email' class='form-control' id='edit_email' name='email' required>
            </div>
            <button type='submit' class='btn btn-success'>更新</button>
            <button type='button' class='btn btn-secondary ms-2' id='cancel-edit'>キャンセル</button>
        </form>
    </div>
</div><!-- .container-fluid -->

<?php
// 接続を閉じる
$conn->close();
?>

<!-- JavaScript for Edit button to populate form -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-btn');
    const editFormSection = document.getElementById('edit-form-section');
    const editIdInput = document.getElementById('edit_id');
    const editNameInput = document.getElementById('edit_name');
    const editEmailInput = document.getElementById('edit_email');
    const cancelEditButton = document.getElementById('cancel-edit');

    console.log('CRUD script loaded in tipers.live template.');
    console.log('editFormSection:', editFormSection);
    console.log('editIdInput:', editIdInput);
    console.log('cancelEditButton:', cancelEditButton);

    if (cancelEditButton) {
        cancelEditButton.addEventListener('click', function() {
            console.log('キャンセルボタンがクリックされました！');
            if (editFormSection) {
                editFormSection.style.display = 'none';
            }
            editIdInput.value = '';
            editNameInput.value = '';
            editEmailInput.value = '';
        });
    } else {
        console.error("エラー: 'cancel-edit' IDを持つキャンセルボタンが見つかりません。");
    }

    editButtons.forEach(button => {
        if (button) { // defensive check, though forEach implies existence if in list
            button.addEventListener('click', function() {
                console.log('編集ボタンがクリックされました！');
                const id = this.dataset.id;
                const name = this.dataset.name;
                const email = this.dataset.email;

                editIdInput.value = id;
                editNameInput.value = name;
                editEmailInput.value = email;
                if (editFormSection) {
                    editFormSection.style.display = 'block';
                    window.scrollTo({ top: editFormSection.offsetTop, behavior: 'smooth' });
                } else {
                    console.error("エラー: 'edit-form-section' IDを持つ編集フォームセクションが見つかりません。");
                }
                console.log('編集フォーム表示を試みました。');
            });
        }
    });
});
</script>
