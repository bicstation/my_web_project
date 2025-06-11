<?php
// C:\doc\my_web_project\app\admin\index.php

// エラー報告を有効にする (開発用)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 環境変数からデータベース接続情報を取得
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
    if (empty($name) || empty(htmlentities($email))) { // Added htmlentities for email check for safety
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

echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "    <meta charset='UTF-8'>";
echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "    <title>管理パネル - データ一覧</title>";
echo "    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "    <style>";
echo "        body { padding: 20px; background-color: #f8f9fa; }";
echo "        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }";
echo "        h1 { color: #007bff; margin-bottom: 25px; }";
echo "        .table th { background-color: #e9ecef; }";
echo "        .form-section { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }";
echo "        .table td:last-child { white-space: nowrap; } /* 操作ボタンが改行されないように */";
echo "    </style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "    <h1>MySQLデータ一覧</h1>";

// メッセージ表示エリア
if (!empty($message)) { // $message変数を表示
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
            echo "<td>" . $row["id"]. "</td>";
            echo "<td>" . htmlspecialchars($row["name"]). "</td>"; // XSS対策
            echo "<td>" . htmlspecialchars($row["email"]). "</td>"; // XSS対策
            echo "<td>" . $row["created_at"]. "</td>";
            echo "<td>";
            // 更新ボタン (モーダルまたは別ページで編集フォームを表示することを想定)
            // ここでは簡易的に、隠しフォームでIDを渡し、JavaScriptでモーダルを開くなどが考えられます。
            // 今回は、Editフォームを直接テーブルの下に表示するようにします。
            echo "<button class='btn btn-sm btn-info me-2 edit-btn' data-id='" . $row['id'] . "' data-name='" . htmlspecialchars($row['name']) . "' data-email='" . htmlspecialchars($row['email']) . "'>編集</button>";

            // 削除フォーム
            // confirm() は開発環境のみ推奨されます。本番環境ではカスタムモーダルUIを使用してください。
            echo "<form method='POST' action='' class='d-inline-block' onsubmit='return confirm(\"本当に削除しますか？\");'>";
            echo "    <input type='hidden' name='action' value='delete_user'>";
            echo "    <input type='hidden' name='id' value='" . $row['id'] . "'>";
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
    echo "<div class='form-section'>";
    echo "    <h2>新しいユーザーを追加</h2>";
    echo "    <form method='POST' action=''>";
    echo "        <input type='hidden' name='action' value='add_user'>";
    echo "        <div class='mb-3'>";
    echo "            <label for='add_name' class='form-label'>名前</label>";
    echo "            <input type='text' class='form-control' id='add_name' name='name' required>";
    echo "        </div>";
    echo "        <div class='mb-3'>";
    echo "            <label for='add_email' class='form-label'>Eメール</label>";
    echo "            <input type='email' class='form-control' id='add_email' name='email' required>";
    echo "        </div>";
    echo "        <button type='submit' class='btn btn-primary'>追加</button>";
    echo "    </form>";
    echo "</div>";

    // -----------------------------------------------------
    // データ更新フォーム (Update Form) - デフォルトでは非表示
    // -----------------------------------------------------
    echo "<div class='form-section' id='edit-form-section' style='display: none;'>";
    echo "    <h2>ユーザー情報を編集</h2>";
    echo "    <form method='POST' action=''>";
    echo "        <input type='hidden' name='action' value='update_user'>";
    echo "        <input type='hidden' name='id' id='edit_id'>";
    echo "        <div class='mb-3'>";
    echo "            <label for='edit_name' class='form-label'>名前</label>";
    echo "            <input type='text' class='form-control' id='edit_name' name='name' required>";
    echo "        </div>";
    echo "        <div class='mb-3'>";
    echo "            <label for='edit_email' class='form-label'>Eメール</label>";
    echo "            <input type='email' class='form-control' id='edit_email' name='email' required>";
    echo "        </div>";
    echo "        <button type='submit' class='btn btn-success'>更新</button>";
    echo "        <button type='button' class='btn btn-secondary ms-2' id='cancel-edit'>キャンセル</button>";
    echo "    </form>";
    echo "</div>";

    // JavaScript for Edit button to populate form
    // HTML形式の <script> タグを直接記述
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-btn');
        const editFormSection = document.getElementById('edit-form-section');
        const editIdInput = document.getElementById('edit_id');
        const editNameInput = document.getElementById('edit_name');
        const editEmailInput = document.getElementById('edit_email');
        const cancelEditButton = document.getElementById('cancel-edit'); // This is the element that was null

        console.log('CRUD script loaded.'); // これがコンソールに出るか確認
        console.log('editFormSection:', editFormSection); // editFormSectionがnullでないか確認
        console.log('editIdInput:', editIdInput);     // editIdInputがnullでないか確認
        console.log('cancelEditButton:', cancelEditButton); // cancelEditButtonがnullでないか確認

        // cancelEditButtonが存在する場合のみイベントリスナーを追加
        if (cancelEditButton) {
            cancelEditButton.addEventListener('click', function() {
                console.log('キャンセルボタンがクリックされました！'); // これがコンソールに出るか確認
                if (editFormSection) { // editFormSectionが存在するか確認
                    editFormSection.style.display = 'none'; // 編集フォームを非表示
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
                    console.log('編集ボタンがクリックされました！'); // これがコンソールに出るか確認
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    const email = this.dataset.email;

                    editIdInput.value = id;
                    editNameInput.value = name;
                    editEmailInput.value = email;
                    if (editFormSection) { // editFormSectionが存在するか確認
                        editFormSection.style.display = 'block'; // 編集フォームを表示
                        window.scrollTo({ top: editFormSection.offsetTop, behavior: 'smooth' }); // フォームまでスクロール
                    } else {
                        console.error("エラー: 'edit-form-section' IDを持つ編集フォームセクションが見つかりません。");
                    }
                    console.log('編集フォーム表示を試みました。'); // これがコンソールに出るか確認
                });
            }
        });
    });
    </script>
    <?php
    // 接続を閉じる
    $conn->close();

    echo "</div>";
    echo "</body>";
    echo "</html>";
    ?>
