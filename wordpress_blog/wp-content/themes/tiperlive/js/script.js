document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded. Initializing script...');

    const myCustomSidebar = document.getElementById('myCustomSidebar'); // サイドバー本体 (Offcanvas要素)
    const myCustomSidebarToggleBtn = document.getElementById('myCustomSidebarToggleBtn'); // PC用トグルボタン
    const body = document.body; // body要素
    const header = document.querySelector('header'); // ヘッダー要素
    // const mainContentWrapper = document.getElementById('main-content-wrapper'); // HTMLから削除されたため、不要

    if (!myCustomSidebar) {
        console.error('Required element (myCustomSidebar) not found. Some functionality may not work.');
    }
    if (!myCustomSidebarToggleBtn) {
        console.warn('myCustomSidebarToggleBtn not found! This button is primarily for desktop mode.');
    }

    /**
     * ヘッダーの高さを取得し、CSS変数として設定する関数
     * @param {HTMLElement} headerElement - ヘッダー要素
     */
    function setHeaderHeightCssVar(headerElement) {
        if (headerElement) {
            const headerHeight = headerElement.offsetHeight;
            document.documentElement.style.setProperty('--header-height', `${headerHeight}px`);
            console.log(`CSS variable --header-height set to: ${headerHeight}px`);
        }
    }

    /**
     * サイドバーの状態に応じてPC用トグルボタンのアイコンを更新する関数
     * @param {HTMLElement} buttonElement - 更新するボタン要素 (myCustomSidebarToggleBtn)
     * @param {boolean} isCollapsed - サイドバーが閉じている状態か (true = 閉じている/collapsed)
     */
    function updateSidebarToggleButtonIcon(buttonElement, isCollapsed) {
        if (buttonElement) {
            const icon = buttonElement.querySelector('i');
            if (icon) {
                if (isCollapsed) {
                    // サイドバーが閉じている (collapsed) なら、開くアイコン (fa-bars)
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    // サイドバーが開いている (not collapsed) なら、閉じるアイコン (fa-times)
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            }
        }
    }

    /**
     * 初期ロード時とリサイズ時にサイドバーの状態とレイアウトを設定する関数
     * (主にPCビューでのサイドバーの高さ調整と、collapsedクラスの制御)
     */
    function adjustSidebarForDesktop() {
        if (window.innerWidth >= 768) {
            // PCサイズの場合
            console.log('Desktop mode detected.');

            // サイドバーの高さ調整 (headerの下からfooterの上まで)
            // footerの高さも考慮する場合、--footer-heightもCSS変数で取得するか、JSで計算する必要がある
            // 現状はheaderの下からviewportの最後までを仮定
            if (myCustomSidebar && header) {
                const headerHeight = header.offsetHeight;
                // サイドバーの高さを100vhからヘッダーの高さを引いた値に設定
                // footerもstickyなどで固定される場合は、footerの高さも引く必要があります。
                myCustomSidebar.style.height = `calc(100vh - ${headerHeight}px)`;
                console.log(`Sidebar height set to: ${myCustomSidebar.style.height}`);
            }

            // PC用トグルボタンのアイコン状態を現在のbodyのcollapsedクラスに基づいて設定
            if (!body.classList.contains('sidebar-collapsed')) {
                updateSidebarToggleButtonIcon(myCustomSidebarToggleBtn, false); // 閉じるアイコン
            } else {
                updateSidebarToggleButtonIcon(myCustomSidebarToggleBtn, true); // 開くアイコン
            }

            // スマホ用Offcanvasが開いていたら閉じる
            const offcanvasInstance = bootstrap.Offcanvas.getInstance(myCustomSidebar);
            if (offcanvasInstance && offcanvasInstance._isShown) {
                offcanvasInstance.hide();
                console.log('Mobile Offcanvas hidden as switched to desktop.');
            }

        } else {
            // スマホサイズの場合
            console.log('Mobile mode detected.');
            // サイドバーのheightをリセット（Offcanvasがheightを管理するため）
            if (myCustomSidebar) {
                myCustomSidebar.style.height = ''; // カスタムで設定した高さをリセット
            }
            // body に 'sidebar-collapsed' クラスが付いていてもPC用なので削除しておく
            body.classList.remove('sidebar-collapsed');
            console.log('Mobile mode. Sidebar hidden (by Offcanvas default behavior).');

            // スマホ用Offcanvasトグルボタンのアイコンを初期状態 (fa-bars) にリセット
            const mobileToggleBtn = document.querySelector('button.d-md-none[data-bs-toggle="offcanvas"]');
            if (mobileToggleBtn) {
                updateSidebarToggleButtonIcon(mobileToggleBtn, true); // スマホでは常に隠れているので、開くアイコン (fa-bars)
            }
        }
    }

    // ページロード時とウィンドウのリサイズ時にヘッダーの高さを設定し、サイドバーの状態を調整
    setHeaderHeightCssVar(header); // DOMContentLoaded時
    adjustSidebarForDesktop(); // DOMContentLoaded時

    window.addEventListener('resize', () => {
        setHeaderHeightCssVar(header); // リサイズ時
        adjustSidebarForDesktop(); // リサイズ時
    });

    // PC用トグルボタンのクリックイベントリスナー
    if (myCustomSidebarToggleBtn) {
        myCustomSidebarToggleBtn.addEventListener('click', function() {
            console.log('PC Sidebar toggle button clicked!');
            // body に 'sidebar-collapsed' クラスをトグルする
            body.classList.toggle('sidebar-collapsed');

            // アイコンを切り替える (現在のbodyの状態に基づいて)
            updateSidebarToggleButtonIcon(myCustomSidebarToggleBtn, body.classList.contains('sidebar-collapsed'));

            console.log('Sidebar state toggled. Current body class:', body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'shown');
        });
    }

    // Bootstrap Offcanvasのイベントリスナー（スマホ用）
    if (myCustomSidebar) {
        myCustomSidebar.addEventListener('shown.bs.offcanvas', function() {
            console.log('Bootstrap Offcanvas: shown.bs.offcanvas event fired.');
            // Offcanvasが開かれたら、PC用のcollapsedクラスは関係ないので、bodyから削除しておく（念のため）
            body.classList.remove('sidebar-collapsed');
        });
        myCustomSidebar.addEventListener('hidden.bs.offcanvas', function() {
            console.log('Bootstrap Offcanvas: hidden.bs.offcanvas event fired.');
            // Offcanvasが閉じられたら、PC用のcollapsedクラスは関係ないので、bodyから削除しておく（念のため）
            body.classList.remove('sidebar-collapsed');
        });
    }
});


// my-custom-theme/js/script.js

// PC用トグルボタンのクリックイベントリスナー
if (myCustomSidebarToggleBtn) {
    myCustomSidebarToggleBtn.addEventListener('click', function() {
        console.log('PC Sidebar toggle button clicked!');
        
        // 【追加】クリック前のbodyクラスを確認
        console.log('Before toggle: body classes are', body.className);

        // body に 'sidebar-collapsed' クラスをトグルする
        body.classList.toggle('sidebar-collapsed');

        // 【追加】クリック後のbodyクラスを確認
        console.log('After toggle: body classes are', body.className);

        // アイコンを切り替える (現在のbodyの状態に基づいて)
        updateSidebarToggleButtonIcon(myCustomSidebarToggleBtn, body.classList.contains('sidebar-collapsed'));

        console.log('Sidebar state toggled. Current body class:', body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'shown');
    });
}