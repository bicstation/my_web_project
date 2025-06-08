document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded. Initializing script...');

    const sidebarOffcanvas = document.getElementById('sidebarOffcanvas');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn'); // PC用トグルボタン
    const body = document.body; // body要素を取得
    // const mainContentArea = document.getElementById('main-content-area'); // main-content-areaはJSからは直接操作せず、CSSでbodyクラスと連携させる

    // 要素の存在チェック
    // sidebarOffcanvas はOffcanvasの初期化には必要だが、PC時のJS制御には直接使わない
    // mainContentArea はCSSで制御するため、JSでの取得は必須ではないが、ログのために残しておく
    if (!sidebarOffcanvas) {
        console.error('Required element (sidebarOffcanvas) not found.');
        return; // 必須要素がない場合は処理を中断
    }

    // Bootstrap Offcanvasインスタンスの初期化
    // PCモードではbackdropなしで、スマホモードではbackdropありで動作させるため、
    // ここではbackdrop: false で初期化し、必要に応じてbackdropを有効にする
    // ただし、PCモードでoffcanvasInstance.show()を呼び出すのは避ける
    // offcanvasInstance = new bootstrap.Offcanvas(sidebarOffcanvas, {
    //     backdrop: false // PCモードでの使用を考慮し、backdropをデフォルトで無効化
    // });
    // => この初期化は、スマホ用のOffcanvasがHTMLのdata-bs-toggleによって適切に動作するため、
    // PCでのカスタム制御のためには不要。むしろ競合の原因になりうるため、コメントアウトする。
    // OffcanvasのJSは、data-bs-toggle属性を持つ要素がクリックされたときに自動的にインスタンスを生成・制御します。


    // 初期ロード時のサイドバーの状態を設定する関数
    function setInitialSidebarState() {
        if (window.innerWidth >= 768) {
            // PCサイズの場合：サイドバーを常に表示 (CSSで制御)
            body.classList.remove('sidebar-collapsed'); // 'sidebar-collapsed' クラスを削除してサイドバーを表示状態にする
            if (sidebarToggleBtn) {
                // PC用トグルボタンのアイコンを閉じるアイコンに設定 (サイドバーが表示されている状態)
                const icon = sidebarToggleBtn.querySelector('i');
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
            console.log('Initial load: Desktop mode. Sidebar shown (by CSS).');
        } else {
            // スマホサイズの場合：サイドバーは非表示 (Offcanvasのデフォルト)
            // body に 'sidebar-collapsed' クラスが付いていても関係ないので削除しておく
            body.classList.remove('sidebar-collapsed'); 
            console.log('Initial load: Mobile mode. Sidebar hidden (by Offcanvas default).');
        }
    }

    // ページロード時にサイドバーの状態を初期化
    setInitialSidebarState();

    // PC用トグルボタンのクリックイベントリスナー
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function() {
            console.log('PC Sidebar toggle button clicked!');
            // body に 'sidebar-collapsed' クラスをトグルする
            body.classList.toggle('sidebar-collapsed');

            // アイコンを切り替える
            const icon = sidebarToggleBtn.querySelector('i');
            if (body.classList.contains('sidebar-collapsed')) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars'); // 開くアイコン
                console.log('Sidebar hidden by toggle (PC).');
            } else {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times'); // 閉じるアイコン
                console.log('Sidebar shown by toggle (PC).');
            }
        });
    } else {
        console.warn('sidebarToggleBtn not found! This button is for desktop mode.');
    }

    // ウィンドウのリサイズイベントリスナー
    window.addEventListener('resize', function() {
        console.log('Window resized. Current width:', window.innerWidth);
        if (window.innerWidth < 768) {
            // スマホサイズになったら
            // PCで表示されていたサイドバーを隠すクラスを削除 (スマホでは常に非表示がデフォルト)
            body.classList.remove('sidebar-collapsed'); 
            console.log('Switched to Mobile mode.');

            // スマホ用トグルボタンのアイコンをリセット
            const mobileToggleBtn = document.querySelector('button.d-md-none[data-bs-toggle="offcanvas"]');
            if (mobileToggleBtn) {
                const mobileIcon = mobileToggleBtn.querySelector('i');
                mobileIcon.classList.remove('fa-times');
                mobileIcon.classList.add('fa-bars');
            }

        } else {
            // PCサイズになったら
            // サイドバーを常に表示状態にする（隠すクラスを削除）
            body.classList.remove('sidebar-collapsed');
            
            // PC用トグルボタンのアイコンを閉じるアイコンに更新
            if (sidebarToggleBtn) {
                const icon = sidebarToggleBtn.querySelector('i');
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
            console.log('Switched to Desktop mode. Sidebar shown (by CSS).');
        }
    });

    // Bootstrap Offcanvasのイベントリスナー（デバッグ用）
    // これらのイベントは、スマホでのOffcanvas開閉時にのみ発火します
    sidebarOffcanvas.addEventListener('shown.bs.offcanvas', function() {
        console.log('Bootstrap Offcanvas: shown.bs.offcanvas event fired.');
        // スマホでOffcanvasが開かれたら、PC用の collapsed クラスは不要なので削除
        // body.classList.remove('sidebar-collapsed'); // 不要だが念のため
    });
    sidebarOffcanvas.addEventListener('hidden.bs.offcanvas', function() {
        console.log('Bootstrap Offcanvas: hidden.bs.offcanvas event fired.');
        // スマホでOffcanvasが閉じられたら、PC用の collapsed クラスは不要なので削除
        // body.classList.remove('sidebar-collapsed'); // 不要だが念のため
    });
});