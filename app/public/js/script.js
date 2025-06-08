document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded. Initializing script...');

    const sidebarOffcanvas = document.getElementById('sidebarOffcanvas');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const mainContentWrapper = document.getElementById('main-content-wrapper');

    let offcanvasInstance = null; // Offcanvasインスタンスを保持する変数

    // 要素の存在チェック
    if (!sidebarOffcanvas || !mainContentWrapper) {
        console.error('Required elements (sidebarOffcanvas or mainContentWrapper) not found.');
        return; // 必須要素がない場合は処理を中断
    }

    // Offcanvasインスタンスの初期化
    // PCモードではbackdropなしで、スマホモードではbackdropありで動作させるため、
    // ここではbackdrop: false で初期化し、必要に応じてbackdropを有効にする
    offcanvasInstance = new bootstrap.Offcanvas(sidebarOffcanvas, {
        backdrop: false // PCモードでの使用を考慮し、backdropをデフォルトで無効化
    });

    // 初期ロード時のサイドバーの状態を設定する関数
    function setInitialSidebarState() {
        if (window.innerWidth >= 768) {
            // PCサイズの場合
            offcanvasInstance.show(); // サイドバーを表示
            mainContentWrapper.classList.remove('sidebar-hidden'); // コンテンツを広げるクラスを削除
            // PC用トグルボタンのアイコンを閉じるアイコンに設定
            if (sidebarToggleBtn) {
                const icon = sidebarToggleBtn.querySelector('i');
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
            console.log('Initial load: Desktop mode. Sidebar shown.');
        } else {
            // スマホサイズの場合
            offcanvasInstance.hide(); // サイドバーを非表示
            mainContentWrapper.classList.remove('sidebar-hidden'); // 隠すクラスを削除
            console.log('Initial load: Mobile mode. Sidebar hidden.');
        }
    }

    // ページロード時にサイドバーの状態を初期化
    setInitialSidebarState();

    // PC用トグルボタンのクリックイベントリスナー
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function() {
            console.log('PC Sidebar toggle button clicked!');
            // mainContentWrapper の 'sidebar-hidden' クラスを切り替える
            mainContentWrapper.classList.toggle('sidebar-hidden');

            // サイドバーの表示/非表示を切り替え
            if (mainContentWrapper.classList.contains('sidebar-hidden')) {
                offcanvasInstance.hide(); // サイドバーを非表示
                console.log('Sidebar hidden by toggle (PC).');
            } else {
                offcanvasInstance.show(); // サイドバーを表示
                console.log('Sidebar shown by toggle (PC).');
            }

            // アイコンを切り替える
            const icon = sidebarToggleBtn.querySelector('i');
            if (mainContentWrapper.classList.contains('sidebar-hidden')) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars'); // 開くアイコン
            } else {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times'); // 閉じるアイコン
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
            // OffcanvasがPCモードで表示されていたら非表示にし、backdropを有効にする
            if (sidebarOffcanvas.classList.contains('show')) {
                offcanvasInstance.hide();
            }
            // backdropを有効にする設定（Bootstrap 5.3では動的にbackdropを制御するのが複雑な場合があるため、
            // HTMLのdata-bs-backdrop="true"を推奨します。ここではCSSで対応します）
            
            mainContentWrapper.classList.remove('sidebar-hidden'); // 隠すクラスを削除
            console.log('Switched to Mobile mode. Sidebar hidden.');

            // スマホ用トグルボタンのアイコンをリセット
            const mobileToggleBtn = document.querySelector('button.d-md-none');
            if (mobileToggleBtn) {
                const mobileIcon = mobileToggleBtn.querySelector('i');
                mobileIcon.classList.remove('fa-times');
                mobileIcon.classList.add('fa-bars');
            }

        } else {
            // PCサイズになったら
            // Offcanvasを強制的に表示状態にする
            offcanvasInstance.show();
            mainContentWrapper.classList.remove('sidebar-hidden'); // 隠れている場合は解除
            
            // PC用トグルボタンのアイコンを閉じるアイコンに更新
            if (sidebarToggleBtn) {
                const icon = sidebarToggleBtn.querySelector('i');
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
            console.log('Switched to Desktop mode. Sidebar shown.');
        }
    });

    // Bootstrap Offcanvasのイベントリスナー（デバッグ用）
    sidebarOffcanvas.addEventListener('shown.bs.offcanvas', function() {
        console.log('Bootstrap Offcanvas: shown.bs.offcanvas event fired.');
    });
    sidebarOffcanvas.addEventListener('hidden.bs.offcanvas', function() {
        console.log('Bootstrap Offcanvas: hidden.bs.offcanvas event fired.');
    });
});