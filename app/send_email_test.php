<?php
// Composerのオートロードファイルを読み込む
// Composerでインストールしたライブラリは、このファイルを含めることで利用できるようになります。
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ----------------------------------------------------
// !!! ここにあなたのGmail情報を入力してください !!!
// ----------------------------------------------------
$sender_email = 'bicstation@gmail.com'; // 例: your.email@gmail.com
$app_password = 'nkkdbufmmjnkfwvh'; // 先ほど生成したアプリパスワード
$recipient_email = 'master@nabejuku.com'; // 例: your.email@gmail.com または別のテスト用アドレス
$recipient_name = 'テストユーザー'; // 宛先の名前 (任意)
// ----------------------------------------------------

$mail = new PHPMailer(true); // 例外を有効にする

try {
    // SMTP設定
    $mail->isSMTP();                                            // SMTPを使用
    $mail->Host       = 'smtp.gmail.com';                       // GmailのSMTPサーバー
    $mail->SMTPAuth   = true;                                   // SMTP認証を有効にする
    $mail->Username   = $sender_email;                          // 送信元Gmailアドレス
    $mail->Password   = $app_password;                          // Gmailアプリパスワード
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            // SSL/TLS暗号化を有効 (SMTPSが推奨)
    $mail->Port       = 465;                                    // SMTPSのポート番号

    // 送信元情報
    $mail->setFrom($sender_email, 'My Web Project Mailer'); // 送信元メールアドレスと表示名

    // 宛先情報
    $mail->addAddress($recipient_email, $recipient_name);       // 宛先メールアドレスと名前

    // コンテンツ
    $mail->isHTML(true);                                        // HTML形式のメールを送信
    $mail->Subject = 'テストメール from Docker PHP';             // メール件名
    $mail->Body    = '<b>これはDockerコンテナのPHPから送信されたテストメールです。</b><br><br>'
                   . 'PHPMailerとGmail SMTPが正常に動作しています。'; // HTML形式の本文
    $mail->AltBody = 'これはDockerコンテナのPHPから送信されたテストメールです。PHPMailerとGmail SMTPが正常に動作しています。'; // テキスト形式の代替本文

    $mail->send();
    echo 'メールが正常に送信されました。';
} catch (Exception $e) {
    echo "メールの送信に失敗しました。Mailer Error: {$mail->ErrorInfo}";
}
?>