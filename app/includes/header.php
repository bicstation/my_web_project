<header class="py-3 bg-primary text-white">
    <div class="container-fluid d-flex justify-content-between align-items-center">

        <button class="btn btn-outline-light d-md-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
            <i class="fas fa-bars"></i>
        </button>

        <button class="btn btn-outline-light me-3 d-none d-md-inline-flex" id="sidebarToggleBtn" type="button">
            <i class="fas fa-bars"></i> 
        </button>

        <h3 class="my-0 me-auto"><a href="#" class="text-white text-decoration-none">My Awesome Site</a></h3>

        <nav class="navbar navbar-expand-md navbar-dark p-0">
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#"><i class="fas fa-home me-1"></i>ホーム</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-box me-1"></i>サービス</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cube me-1"></i>製品
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#">製品A</a></li>
                            <li><a class="dropdown-item" href="#">製品B</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#">その他</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-question-circle me-1"></i>よくある質問</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-envelope me-1"></i>お問い合わせ</a>
                    </li>
                </ul>
                <form class="d-none d-md-inline-flex ms-3">
                    <div class="input-group">
                        <input class="form-control" type="search" placeholder="サイト内検索..." aria-label="Search">
                        <button class="btn btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <a href="#" class="btn btn-outline-light ms-3"><i class="fas fa-sign-in-alt me-1"></i>ログイン</a>
            </div>
        </nav>
    </div>
</header>