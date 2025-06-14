<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage ?? '') === 'home' ? 'active' : ''; ?>" aria-current="page" href="/"><i class="fas fa-home me-1"></i>ホーム</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cogs me-1"></i>サービス
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-wrench me-2"></i>サービスA</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-tools me-2"></i>サービスB</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-code me-2"></i>サービスC</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="fas fa-box me-1"></i>製品</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="fas fa-question-circle me-1"></i>よくある質問</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="fas fa-envelope me-1"></i>お問い合わせ</a>
                </li>
            </ul>
        </div>
        <form class="d-flex d-md-none mt-2 mt-md-0 w-100">
            <div class="input-group">
                <input class="form-control form-control-sm" type="search" placeholder="サイト内検索..." aria-label="Search">
                <button class="btn btn-outline-light btn-sm" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
</nav>